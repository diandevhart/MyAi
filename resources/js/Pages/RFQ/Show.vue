<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import InputText from 'primevue/inputtext';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rfq: Object,
});

const rfq = computed(() => props.rfq);
const items = computed(() => rfq.value.items || []);
const files = computed(() => rfq.value.files || []);

function statusColor(status) {
    const map = {
        draft: 'bg-slate-600/30 text-slate-400',
        sent: 'bg-blue-500/20 text-blue-400',
        quoted: 'bg-amber-500/20 text-amber-400',
        awarded: 'bg-emerald-500/20 text-emerald-400',
        cancelled: 'bg-red-500/20 text-red-400',
    };
    return map[status] || 'bg-slate-600/30 text-slate-400';
}

// Editable pricing (when status = sent)
const editableItems = ref(
    (props.rfq.items || []).map(item => ({
        id: item.id,
        unit_price: item.unit_price || '',
        lead_time_days: item.lead_time_days || '',
        notes: item.notes || '',
    }))
);

const isEditable = computed(() => rfq.value.status === 'sent');

function getItemTotal(idx) {
    const qty = items.value[idx]?.quantity || 0;
    const price = Number(editableItems.value[idx]?.unit_price) || 0;
    return (qty * price).toFixed(2);
}

const grandTotal = computed(() => {
    return items.value.reduce((sum, item, idx) => {
        const qty = item.quantity || 0;
        const price = Number(editableItems.value[idx]?.unit_price) || Number(item.unit_price) || 0;
        return sum + qty * price;
    }, 0);
});

const saving = ref(false);
async function saveQuote() {
    saving.value = true;
    try {
        await axios.post(route('rfq.store-quote', rfq.value.id), {
            items: editableItems.value.map(ei => ({
                supplier_quote_request_item_id: ei.id,
                unit_price: ei.unit_price,
                lead_time_days: ei.lead_time_days,
                notes: ei.notes,
            })),
        });
        toast.success('Quote saved');
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to save quote');
    } finally {
        saving.value = false;
    }
}

async function awardSupplier() {
    if (!confirm('Award this RFQ to ' + rfq.value.supplier?.name + '?')) return;
    try {
        await axios.post(route('rfq.select-winner', rfq.value.id));
        toast.success('Supplier awarded');
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to award');
    }
}

// File upload
const fileInput = ref(null);
const poInput = ref(null);

async function uploadFile() {
    const file = fileInput.value?.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    try {
        await axios.post(route('rfq.upload-file', rfq.value.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('File uploaded');
        fileInput.value.value = '';
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Upload failed');
    }
}

async function uploadPO() {
    const file = poInput.value?.files[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    try {
        await axios.post(route('rfq.upload-po', rfq.value.id), formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        toast.success('PO uploaded — equipment auto-created');
        poInput.value.value = '';
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Upload failed');
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button @click="router.visit(route('rfq.pipeline'))" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-cyan-400 font-mono">{{ rfq.rfq_number }}</h1>
                        <span :class="['inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', statusColor(rfq.status)]">
                            {{ rfq.status }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-400 mt-0.5">Supplier: <span class="text-slate-200">{{ rfq.supplier?.name || '—' }}</span></p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <button
                    v-if="rfq.internal_rfq_request_id"
                    @click="router.visit(route('rfq.compare', rfq.internal_rfq_request_id))"
                    class="flex items-center gap-2 rounded-lg bg-violet-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-400 transition-colors"
                >
                    <i class="pi pi-chart-bar text-xs"></i> Compare Quotes
                </button>
                <button
                    v-if="rfq.status === 'quoted'"
                    @click="awardSupplier"
                    class="flex items-center gap-2 rounded-lg bg-emerald-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-400 transition-colors"
                >
                    <i class="pi pi-check text-xs"></i> Award to this Supplier
                </button>
            </div>
        </div>

        <!-- Info Bar -->
        <div class="mb-6 flex gap-6 text-sm">
            <div>
                <span class="text-slate-500">Sent:</span>
                <span class="ml-1 text-slate-300">{{ rfq.sent_at ? new Date(rfq.sent_at).toLocaleDateString() : '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">Due:</span>
                <span class="ml-1 text-slate-300">{{ rfq.due_date ? new Date(rfq.due_date).toLocaleDateString() : '—' }}</span>
            </div>
            <div v-if="rfq.internal_rfq_request">
                <span class="text-slate-500">Urgency:</span>
                <span class="ml-1 text-slate-300 capitalize">{{ rfq.internal_rfq_request.urgency }}</span>
            </div>
            <div>
                <span class="text-slate-500">Grand Total:</span>
                <span class="ml-1 text-slate-100 font-semibold">R {{ grandTotal.toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}</span>
            </div>
        </div>

        <!-- Line Items -->
        <div class="rounded-xl border border-slate-700 overflow-hidden mb-6">
            <div class="flex items-center justify-between bg-slate-800 px-5 py-3 border-b border-slate-700">
                <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider">Line Items</h3>
                <button
                    v-if="isEditable"
                    @click="saveQuote"
                    :disabled="saving"
                    class="flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors disabled:opacity-50"
                >
                    <i class="pi pi-save text-xs"></i> {{ saving ? 'Saving...' : 'Save Quote' }}
                </button>
            </div>
            <DataTable
                :value="items"
                class="[&_.p-datatable-thead>tr>th]:!bg-slate-800 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-900 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700"
            >
                <Column header="Item Name" style="min-width: 200px">
                    <template #body="{ data }">
                        <span class="text-slate-200">{{ data.inventory_equipment?.name || data.new_item_name || 'Unknown' }}</span>
                        <span v-if="data.new_item_name && !data.inventory_equipment_id" class="ml-2 text-xs bg-violet-500/20 text-violet-400 rounded-full px-2 py-0.5">New</span>
                    </template>
                </Column>
                <Column field="quantity" header="Qty" style="width: 70px" />
                <Column header="Unit Price" style="width: 130px">
                    <template #body="{ data, index }">
                        <InputText
                            v-if="isEditable"
                            v-model="editableItems[index].unit_price"
                            type="number"
                            step="0.01"
                            class="!w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm"
                            placeholder="0.00"
                        />
                        <span v-else class="text-slate-300">
                            {{ data.unit_price ? 'R ' + Number(data.unit_price).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) : '—' }}
                        </span>
                    </template>
                </Column>
                <Column header="Total" style="width: 120px">
                    <template #body="{ data, index }">
                        <span v-if="isEditable" class="font-medium text-slate-200">
                            R {{ getItemTotal(index) }}
                        </span>
                        <span v-else class="text-slate-300">
                            {{ data.total_price ? 'R ' + Number(data.total_price).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) : '—' }}
                        </span>
                    </template>
                </Column>
                <Column header="Lead Time" style="width: 110px">
                    <template #body="{ data, index }">
                        <InputText
                            v-if="isEditable"
                            v-model="editableItems[index].lead_time_days"
                            type="number"
                            class="!w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm"
                            placeholder="days"
                        />
                        <span v-else class="text-slate-400 text-sm">{{ data.lead_time_days ? data.lead_time_days + ' days' : '—' }}</span>
                    </template>
                </Column>
                <Column header="Notes" style="min-width: 140px">
                    <template #body="{ data, index }">
                        <InputText
                            v-if="isEditable"
                            v-model="editableItems[index].notes"
                            class="!w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm"
                            placeholder="Notes..."
                        />
                        <span v-else class="text-slate-500 text-sm">{{ data.notes || '—' }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Files Section -->
        <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Files & Documents</h3>

            <div v-if="files.length" class="space-y-2 mb-4">
                <div v-for="file in files" :key="file.id" class="flex items-center justify-between rounded-lg bg-slate-900 border border-slate-700 p-3">
                    <div class="flex items-center gap-3">
                        <i class="pi pi-file text-slate-400"></i>
                        <div>
                            <span class="text-sm text-slate-200">{{ file.original_name || file.file_path?.split('/').pop() }}</span>
                            <span class="ml-2 text-xs text-slate-500 capitalize">{{ file.file_type || 'Document' }}</span>
                        </div>
                    </div>
                    <a v-if="file.file_path" :href="'/storage/' + file.file_path" target="_blank" class="text-xs text-cyan-400 hover:text-cyan-300">
                        <i class="pi pi-download"></i> Download
                    </a>
                </div>
            </div>
            <div v-else class="text-sm text-slate-500 mb-4">No files uploaded yet.</div>

            <div class="flex gap-3">
                <div>
                    <label class="flex items-center gap-2 rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors cursor-pointer">
                        <i class="pi pi-upload text-xs"></i> Upload File
                        <input ref="fileInput" type="file" class="hidden" @change="uploadFile" />
                    </label>
                </div>
                <div v-if="rfq.status === 'awarded'">
                    <label class="flex items-center gap-2 rounded-lg bg-emerald-500/20 border border-emerald-500/30 px-4 py-2 text-sm text-emerald-400 hover:bg-emerald-500/30 transition-colors cursor-pointer">
                        <i class="pi pi-file-check text-xs"></i> Upload PO
                        <input ref="poInput" type="file" class="hidden" @change="uploadPO" />
                    </label>
                </div>
            </div>
        </div>
    </div>
</template>
