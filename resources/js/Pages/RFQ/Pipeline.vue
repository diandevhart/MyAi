<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rfqs: Array,
    approvedInternalRequests: { type: Array, default: () => [] },
    suppliers: { type: Array, default: () => [] },
});

const activeTab = ref('all');
const tabs = [
    { key: 'all', label: 'All' },
    { key: 'draft', label: 'Draft' },
    { key: 'sent', label: 'Sent' },
    { key: 'quoted', label: 'Quoted' },
    { key: 'awarded', label: 'Awarded' },
    { key: 'cancelled', label: 'Cancelled' },
];

const filtered = computed(() => {
    if (activeTab.value === 'all') return props.rfqs;
    return props.rfqs.filter(r => r.status === activeTab.value);
});

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

function goToRfq(event) {
    router.visit(route('rfq.show', event.data.id));
}

// Create RFQ dialog
const showCreateDialog = ref(false);
const createForm = ref({
    internalRfqId: null,
    supplierIds: [],
});

function toggleSupplier(id) {
    const idx = createForm.value.supplierIds.indexOf(id);
    if (idx >= 0) createForm.value.supplierIds.splice(idx, 1);
    else createForm.value.supplierIds.push(id);
}

async function submitCreateRfq() {
    if (!createForm.value.internalRfqId) {
        toast.error('Select an internal request');
        return;
    }
    if (!createForm.value.supplierIds.length) {
        toast.error('Select at least one supplier');
        return;
    }
    try {
        await axios.post(route('rfq.create-from-internal', createForm.value.internalRfqId), {
            supplier_ids: createForm.value.supplierIds,
        });
        toast.success('RFQ(s) created');
        showCreateDialog.value = false;
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to create RFQ');
    }
}

function openCreateDialog() {
    createForm.value = { internalRfqId: null, supplierIds: [] };
    showCreateDialog.value = true;
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-100">RFQ Pipeline</h1>
            <button @click="openCreateDialog" class="flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">
                <i class="pi pi-plus text-xs"></i> Create RFQ
            </button>
        </div>

        <!-- Filter Tabs -->
        <div class="mb-5 flex gap-1 border-b border-slate-700">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="activeTab = tab.key"
                class="px-4 py-2.5 text-sm font-medium transition-colors"
                :class="activeTab === tab.key
                    ? 'text-cyan-400 border-b-2 border-cyan-400 bg-cyan-500/10'
                    : 'text-slate-400 hover:text-slate-200 border-b-2 border-transparent'"
            >
                {{ tab.label }}
                <span class="ml-1.5 text-xs opacity-60">({{ tab.key === 'all' ? props.rfqs.length : props.rfqs.filter(r => r.status === tab.key).length }})</span>
            </button>
        </div>

        <!-- DataTable -->
        <div class="rounded-xl border border-slate-700 overflow-hidden">
            <DataTable
                :value="filtered"
                :paginator="true"
                :rows="15"
                :rowHover="true"
                @row-click="goToRfq"
                class="[&_.p-datatable-thead>tr>th]:!bg-slate-800 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-900 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700 [&_.p-datatable-tbody>tr:hover>td]:!bg-slate-800 [&_.p-paginator]:!bg-slate-900 [&_.p-paginator]:!border-slate-700 [&_.p-paginator]:!text-slate-400 cursor-pointer"
                sortMode="single"
                removableSort
            >
                <Column field="rfq_number" header="RFQ Number" sortable>
                    <template #body="{ data }">
                        <span class="font-mono text-cyan-400 text-sm">{{ data.rfq_number }}</span>
                    </template>
                </Column>
                <Column header="Supplier" sortable sortField="supplier.name">
                    <template #body="{ data }">
                        <span class="text-slate-200">{{ data.supplier?.name || '—' }}</span>
                    </template>
                </Column>
                <Column header="Internal Ref">
                    <template #body="{ data }">
                        <span v-if="data.internal_rfq_request" class="text-xs text-slate-400 font-mono">#{{ data.internal_rfq_request.id }}</span>
                        <span v-else class="text-slate-600">—</span>
                    </template>
                </Column>
                <Column header="Items" style="width: 70px">
                    <template #body="{ data }">{{ data.items_count ?? data.items?.length ?? 0 }}</template>
                </Column>
                <Column field="status" header="Status" sortable>
                    <template #body="{ data }">
                        <span :class="['inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', statusColor(data.status)]">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column field="sent_at" header="Sent Date" sortable>
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ data.sent_at ? new Date(data.sent_at).toLocaleDateString() : '—' }}</span>
                    </template>
                </Column>
                <Column field="due_date" header="Due Date" sortable>
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ data.due_date ? new Date(data.due_date).toLocaleDateString() : '—' }}</span>
                    </template>
                </Column>
                <Column header="Actions" style="width: 100px">
                    <template #body="{ data }">
                        <button @click.stop="router.visit(route('rfq.show', data.id))" class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-600 transition-colors">
                            View
                        </button>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Create RFQ Dialog -->
        <Dialog
            v-model:visible="showCreateDialog"
            header="Create RFQ from Internal Request"
            modal
            :style="{ width: '540px' }"
            :pt="{ root: { class: '!bg-slate-800 !border-slate-700' }, header: { class: '!bg-slate-800 !text-slate-100 !border-slate-700' }, content: { class: '!bg-slate-800 !text-slate-300' } }"
        >
            <div class="space-y-5">
                <!-- Select Internal Request -->
                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">Approved Internal Request *</label>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <div
                            v-for="req in approvedInternalRequests"
                            :key="req.id"
                            @click="createForm.internalRfqId = req.id"
                            class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                            :class="createForm.internalRfqId === req.id
                                ? 'border-cyan-500 bg-cyan-500/10'
                                : 'border-slate-700 bg-slate-900 hover:border-slate-600'"
                        >
                            <div class="flex h-5 w-5 items-center justify-center rounded-full border-2"
                                :class="createForm.internalRfqId === req.id ? 'border-cyan-400 bg-cyan-400' : 'border-slate-600'">
                                <i v-if="createForm.internalRfqId === req.id" class="pi pi-check text-[10px] text-slate-900"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-sm text-slate-200 font-mono">#{{ req.id }}</span>
                                <span class="ml-2 text-xs text-slate-400">{{ req.items?.length || 0 }} items</span>
                                <span class="ml-2 text-xs text-slate-500">{{ req.warehouse?.name }}</span>
                            </div>
                        </div>
                        <div v-if="!approvedInternalRequests.length" class="text-center py-4 text-sm text-slate-500">
                            No approved internal requests available.
                        </div>
                    </div>
                </div>

                <!-- Select Suppliers -->
                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">Select Suppliers *</label>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        <div
                            v-for="sup in suppliers"
                            :key="sup.id"
                            @click="toggleSupplier(sup.id)"
                            class="flex items-center gap-3 rounded-lg border p-3 cursor-pointer transition-colors"
                            :class="createForm.supplierIds.includes(sup.id)
                                ? 'border-violet-500 bg-violet-500/10'
                                : 'border-slate-700 bg-slate-900 hover:border-slate-600'"
                        >
                            <div class="flex h-5 w-5 items-center justify-center rounded border"
                                :class="createForm.supplierIds.includes(sup.id) ? 'border-violet-400 bg-violet-400' : 'border-slate-600'">
                                <i v-if="createForm.supplierIds.includes(sup.id)" class="pi pi-check text-[10px] text-white"></i>
                            </div>
                            <div class="flex-1">
                                <span class="text-sm text-slate-200">{{ sup.name }}</span>
                                <span v-if="sup.rating" class="ml-2 text-xs text-slate-500">★ {{ Number(sup.rating).toFixed(1) }}</span>
                            </div>
                        </div>
                        <div v-if="!suppliers.length" class="text-center py-4 text-sm text-slate-500">
                            No suppliers available.
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <button @click="showCreateDialog = false" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors">Cancel</button>
                    <button @click="submitCreateRfq" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">
                        Create RFQ{{ createForm.supplierIds.length > 1 ? 's' : '' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
