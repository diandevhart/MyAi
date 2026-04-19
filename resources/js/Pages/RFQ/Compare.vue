<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    comparison: Object,
});

const comp = computed(() => props.comparison);
const items = computed(() => comp.value?.items || []);
const suppliers = computed(() => comp.value?.suppliers || []);

function lowestPrice(item) {
    let min = Infinity;
    for (const s of suppliers.value) {
        const q = item.quotes?.[s.id];
        if (q?.unit_price && Number(q.unit_price) < min) min = Number(q.unit_price);
    }
    return min === Infinity ? null : min;
}

function highestPrice(item) {
    let max = -1;
    for (const s of suppliers.value) {
        const q = item.quotes?.[s.id];
        if (q?.unit_price && Number(q.unit_price) > max) max = Number(q.unit_price);
    }
    return max === -1 ? null : max;
}

function cellBg(item, supplierId) {
    const q = item.quotes?.[supplierId];
    if (!q?.unit_price) return '';
    const price = Number(q.unit_price);
    const lo = lowestPrice(item);
    const hi = highestPrice(item);
    if (lo === hi) return '';
    if (price === lo) return 'bg-emerald-500/20';
    if (price === hi) return 'bg-red-500/20';
    return '';
}

function supplierTotal(supplierId) {
    return items.value.reduce((sum, item) => {
        const q = item.quotes?.[supplierId];
        return sum + Number(q?.total_price || 0);
    }, 0);
}

function supplierRfqId(supplierId) {
    for (const item of items.value) {
        const q = item.quotes?.[supplierId];
        if (q?.supplier_quote_request_id) return q.supplier_quote_request_id;
    }
    return null;
}

async function awardSupplier(supplierId) {
    const rfqId = supplierRfqId(supplierId);
    if (!rfqId) {
        toast.error('No quote found for this supplier');
        return;
    }
    const sup = suppliers.value.find(s => s.id === supplierId);
    if (!confirm('Award to ' + (sup?.name || 'this supplier') + '?')) return;
    try {
        await axios.post(route('rfq.select-winner', rfqId));
        toast.success('Supplier awarded');
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to award');
    }
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center gap-4 mb-6">
            <button @click="router.back()" class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                <i class="pi pi-arrow-left"></i>
            </button>
            <div>
                <h1 class="text-2xl font-bold text-slate-100">Quote Comparison</h1>
                <p v-if="comp?.internal_request_id" class="text-sm text-slate-400 mt-0.5">
                    Internal Request <span class="font-mono text-cyan-400">#{{ comp.internal_request_id }}</span>
                </p>
            </div>
        </div>

        <!-- Comparison Table -->
        <div class="rounded-xl border border-slate-700 overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-slate-800 border-b border-slate-700">
                        <th class="text-left px-5 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider sticky left-0 bg-slate-800 z-10" style="min-width: 200px;">
                            Item
                        </th>
                        <th
                            v-for="s in suppliers"
                            :key="s.id"
                            class="text-center px-5 py-3 text-xs font-semibold text-slate-400 uppercase tracking-wider"
                            style="min-width: 180px;"
                        >
                            <div>{{ s.name }}</div>
                            <div v-if="s.rating" class="text-[10px] text-slate-500 mt-0.5">★ {{ Number(s.rating).toFixed(1) }}</div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="(item, idx) in items"
                        :key="idx"
                        class="border-b border-slate-700/50"
                    >
                        <!-- Item column -->
                        <td class="px-5 py-3 sticky left-0 bg-slate-900 z-10">
                            <div class="text-slate-200 font-medium">{{ item.name }}</div>
                            <div class="text-xs text-slate-500 mt-0.5">Qty: {{ item.quantity }}</div>
                        </td>
                        <!-- Supplier columns -->
                        <td
                            v-for="s in suppliers"
                            :key="s.id"
                            class="px-5 py-3 text-center"
                            :class="[cellBg(item, s.id), 'bg-slate-900']"
                        >
                            <template v-if="item.quotes?.[s.id]">
                                <div class="text-slate-200 font-medium">
                                    R {{ Number(item.quotes[s.id].unit_price || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                </div>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    Total: R {{ Number(item.quotes[s.id].total_price || 0).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                </div>
                                <div v-if="item.quotes[s.id].lead_time_days" class="text-xs text-slate-500 mt-0.5">
                                    {{ item.quotes[s.id].lead_time_days }} days
                                </div>
                            </template>
                            <span v-else class="text-slate-600">—</span>
                        </td>
                    </tr>

                    <!-- Grand Total row -->
                    <tr class="bg-slate-800 border-t-2 border-slate-600">
                        <td class="px-5 py-4 sticky left-0 bg-slate-800 z-10">
                            <span class="text-sm font-bold text-slate-200 uppercase">Grand Total</span>
                        </td>
                        <td
                            v-for="s in suppliers"
                            :key="s.id"
                            class="px-5 py-4 text-center bg-slate-800"
                        >
                            <div class="text-lg font-bold text-slate-100">
                                R {{ supplierTotal(s.id).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Award Buttons -->
        <div class="mt-4 flex gap-4 justify-center">
            <button
                v-for="s in suppliers"
                :key="s.id"
                @click="awardSupplier(s.id)"
                class="flex items-center gap-2 rounded-lg bg-emerald-500 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-400 transition-colors"
            >
                <i class="pi pi-check text-xs"></i> Award {{ s.name }}
            </button>
        </div>
    </div>
</template>
