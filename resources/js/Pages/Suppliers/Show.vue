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
    supplier: Object,
});

const s = computed(() => props.supplier);

function ratingColor(rating) {
    if (rating >= 4.0) return 'bg-emerald-500/20 text-emerald-400';
    if (rating >= 3.0) return 'bg-amber-500/20 text-amber-400';
    return 'bg-red-500/20 text-red-400';
}

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

function defectColor(rate) {
    if (rate < 5) return 'text-emerald-400';
    if (rate < 15) return 'text-amber-400';
    return 'text-red-400';
}

const editingRating = ref(false);
const ratingInput = ref(Number(props.supplier.rating || 0));

async function saveRating() {
    try {
        await axios.put(route('suppliers.update', s.value.id), { rating: ratingInput.value });
        toast.success('Rating updated');
        editingRating.value = false;
        router.reload();
    } catch (e) {
        toast.error('Failed to update rating');
    }
}

function goToRfq(event) {
    router.visit(route('rfq.show', event.data.id));
}

const defectRate = computed(() => props.supplier.defect_rate ?? 0);
const totalOrders = computed(() => props.supplier.supplier_quote_requests?.length || 0);
const avgLeadTime = computed(() => {
    const items = (props.supplier.supplier_quote_requests || [])
        .flatMap(r => r.items || [])
        .filter(i => i.lead_time_days);
    if (!items.length) return '—';
    return Math.round(items.reduce((sum, i) => sum + i.lead_time_days, 0) / items.length) + ' days';
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div class="flex items-center gap-4">
                <button @click="router.visit(route('suppliers.index'))" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                    <i class="pi pi-arrow-left"></i>
                </button>
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-100">{{ s.name }}</h1>
                        <span v-if="s.rating" :class="['inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold', ratingColor(s.rating)]">
                            <i class="pi pi-star-fill text-[10px]"></i>
                            {{ Number(s.rating).toFixed(1) }}
                        </span>
                        <span :class="[
                            'inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            s.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-600/30 text-slate-500'
                        ]">
                            {{ s.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </div>
                    <p class="text-sm text-slate-500 mt-0.5">{{ [s.city, s.country].filter(Boolean).join(', ') }}</p>
                </div>
            </div>
        </div>

        <!-- Contact Info Card -->
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Contact Information</h3>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <span class="text-xs text-slate-500">Contact Person</span>
                    <p class="text-sm text-slate-200">{{ s.contact_person || '—' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Email</span>
                    <p class="text-sm"><a v-if="s.email" :href="'mailto:' + s.email" class="text-cyan-400 hover:underline">{{ s.email }}</a><span v-else class="text-slate-400">—</span></p>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Phone</span>
                    <p class="text-sm text-slate-200">{{ s.phone || '—' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Payment Terms</span>
                    <p class="text-sm text-slate-200">{{ s.payment_terms || '—' }}</p>
                </div>
                <div class="col-span-2">
                    <span class="text-xs text-slate-500">Address</span>
                    <p class="text-sm text-slate-200">{{ s.address || '—' }}</p>
                </div>
                <div>
                    <span class="text-xs text-slate-500">Tax Number</span>
                    <p class="text-sm text-slate-200">{{ s.tax_number || '—' }}</p>
                </div>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            <!-- Left: Order History (60%) -->
            <div class="lg:col-span-3">
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Order History</h3>
                    <DataTable
                        :value="s.supplier_quote_requests || []"
                        :paginator="(s.supplier_quote_requests || []).length > 10"
                        :rows="10"
                        :rowHover="true"
                        @row-click="goToRfq"
                        class="[&_.p-datatable-thead>tr>th]:!bg-slate-800 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-800 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700 [&_.p-datatable-tbody>tr:hover>td]:!bg-slate-700 [&_.p-paginator]:!bg-slate-800 [&_.p-paginator]:!border-slate-700 cursor-pointer"
                    >
                        <Column field="rfq_number" header="RFQ Number" sortable>
                            <template #body="{ data }">
                                <span class="font-mono text-cyan-400 text-sm">{{ data.rfq_number }}</span>
                            </template>
                        </Column>
                        <Column field="status" header="Status" sortable>
                            <template #body="{ data }">
                                <span :class="['inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', statusColor(data.status)]">
                                    {{ data.status }}
                                </span>
                            </template>
                        </Column>
                        <Column header="Items">
                            <template #body="{ data }">{{ data.items?.length || 0 }}</template>
                        </Column>
                        <Column header="Total Value" sortable>
                            <template #body="{ data }">
                                <span v-if="data.items?.some(i => i.total_price)">
                                    R {{ data.items.reduce((s, i) => s + Number(i.total_price || 0), 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                </span>
                                <span v-else class="text-slate-500">—</span>
                            </template>
                        </Column>
                        <Column field="sent_at" header="Date" sortable>
                            <template #body="{ data }">
                                <span class="text-xs text-slate-400">{{ data.sent_at ? new Date(data.sent_at).toLocaleDateString() : '—' }}</span>
                            </template>
                        </Column>
                    </DataTable>
                    <div v-if="!(s.supplier_quote_requests || []).length" class="text-center py-8 text-slate-500 text-sm">
                        No order history yet.
                    </div>
                </div>
            </div>

            <!-- Right: Performance (40%) -->
            <div class="lg:col-span-2">
                <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
                    <h3 class="text-sm font-semibold text-slate-400 uppercase tracking-wider mb-4">Performance</h3>

                    <div class="space-y-5">
                        <!-- Defect Rate -->
                        <div>
                            <span class="text-xs text-slate-500">Defect Rate</span>
                            <p :class="['text-3xl font-bold mt-1', defectColor(defectRate)]">
                                {{ defectRate.toFixed(1) }}%
                            </p>
                        </div>

                        <!-- Total Orders -->
                        <div>
                            <span class="text-xs text-slate-500">Total Orders</span>
                            <p class="text-3xl font-bold text-slate-100 mt-1">{{ totalOrders }}</p>
                        </div>

                        <!-- Avg Lead Time -->
                        <div>
                            <span class="text-xs text-slate-500">Average Lead Time</span>
                            <p class="text-3xl font-bold text-slate-100 mt-1">{{ avgLeadTime }}</p>
                        </div>

                        <!-- Rating -->
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-slate-500">Rating</span>
                                <button v-if="!editingRating" @click="editingRating = true" class="text-xs text-cyan-400 hover:text-cyan-300">Edit</button>
                            </div>
                            <div v-if="editingRating" class="flex items-center gap-2 mt-2">
                                <InputText v-model.number="ratingInput" type="number" min="0" max="5" step="0.1" class="!w-20 !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg text-sm" />
                                <button @click="saveRating" class="rounded-lg bg-cyan-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-400">Save</button>
                                <button @click="editingRating = false" class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs text-slate-400 hover:bg-slate-700">Cancel</button>
                            </div>
                            <div v-else class="flex items-center gap-2 mt-2">
                                <div class="flex">
                                    <i v-for="i in 5" :key="i" class="pi text-lg" :class="i <= Math.round(s.rating || 0) ? 'pi-star-fill text-amber-400' : 'pi-star text-slate-600'"></i>
                                </div>
                                <span class="text-sm text-slate-300">{{ s.rating ? Number(s.rating).toFixed(1) : 'N/A' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
