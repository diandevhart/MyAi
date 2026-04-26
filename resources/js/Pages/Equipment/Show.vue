<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import axios from 'axios';
import { toast } from 'vue-toastflow';

defineOptions({ layout: AppLayout });

const props = defineProps({
    equipment: { type: Object, required: true },
    stockLevel: { type: Object, default: () => ({}) },
    physicalItems: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1, per_page: 25, total: 0 }) },
});

// ─── Stock overview mini-stats ─────────────────────────
const stockCards = computed(() => [
    { label: 'Total', value: props.stockLevel?.total ?? 0, color: 'cyan' },
    { label: 'Available', value: props.stockLevel?.available ?? 0, color: 'emerald' },
    { label: 'In Use', value: props.stockLevel?.in_use ?? 0, color: 'violet' },
    { label: 'In Transit', value: props.stockLevel?.in_transit ?? 0, color: 'blue' },
    { label: 'Quarantine', value: props.stockLevel?.in_quarantine ?? 0, color: 'amber' },
    { label: 'Missing', value: props.stockLevel?.missing ?? 0, color: 'red' },
]);

const colorMap = {
    cyan: 'bg-cyan-400/20 text-cyan-400',
    emerald: 'bg-emerald-400/20 text-emerald-400',
    violet: 'bg-violet-400/20 text-violet-400',
    blue: 'bg-blue-400/20 text-blue-400',
    amber: 'bg-amber-400/20 text-amber-400',
    red: 'bg-red-400/20 text-red-400',
};

// ─── Stock by Location ─────────────────────────────────
const byWarehouse = computed(() => {
    const map = props.stockLevel?.by_warehouse || {};
    return Object.entries(map).map(([id, net]) => ({ warehouse_id: id, net }));
});

// ─── Movement History Dialog ───────────────────────────
const historyVisible = ref(false);
const historyLoading = ref(false);
const historyData = ref([]);
const historyItemLabel = ref('');

async function openHistory(item) {
    historyItemLabel.value = item.serial_number || `Item #${item.id}`;
    historyVisible.value = true;
    historyLoading.value = true;
    historyData.value = [];

    try {
        const { data } = await axios.get(route('inventory.history', { id: item.id }));
        historyData.value = data.data || [];
    } catch {
        toast({ type: 'error', message: 'Failed to load movement history.' });
    } finally {
        historyLoading.value = false;
    }
}

// ─── Condition badge helper ────────────────────────────
function conditionBadge(status) {
    if (!status) return { label: '—', class: 'bg-slate-700 text-slate-400' };
    const code = status.code || '';
    const map = {
        A: 'bg-emerald-500/20 text-emerald-400',
        B: 'bg-blue-500/20 text-blue-400',
        C: 'bg-amber-500/20 text-amber-400',
        D: 'bg-orange-500/20 text-orange-400',
        E: 'bg-red-500/20 text-red-400',
        X: 'bg-red-600/20 text-red-500',
    };
    return {
        label: status.name || code,
        class: map[code] || 'bg-slate-700 text-slate-400',
    };
}

// ─── Pagination ────────────────────────────────────────
function onPageChange(event) {
    const page = event.page + 1;
    router.get(route('equipment.show', { id: props.equipment.id }), { page }, { preserveState: true, preserveScroll: true });
}

// ─── Equipment info fields ─────────────────────────────
const infoFields = computed(() => {
    const eq = props.equipment;
    return [
        { label: 'Part Number', value: eq.part_number },
        { label: 'Type', value: eq.type },
        { label: 'Category', value: eq.group_requirement?.name },
        { label: 'Manufacturer', value: eq.manufacturer },
        { label: 'Model Number', value: eq.model_number },
        { label: 'Unit of Measure', value: eq.unit_of_measure },
        { label: 'Weight', value: eq.weight ? `${eq.weight} kg` : null },
        { label: 'Dimensions', value: eq.dimensions },
        { label: 'Cost Price', value: eq.cost_price ? `£${Number(eq.cost_price).toFixed(2)}` : null },
        { label: 'Lead Time', value: eq.lead_time_days ? `${eq.lead_time_days} days` : null },
        { label: 'Serialized', value: eq.is_serialized ? 'Yes' : 'No' },
        { label: 'Requires Inspection', value: eq.requires_inspection ? `Every ${eq.inspection_interval_days} days` : 'No' },
    ].filter(f => f.value);
});

// ─── Custom field requirements ─────────────────────────
const equipmentReqs = computed(() => props.equipment.equipment_reqs || []);
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <button
                        @click="router.visit(route('equipment.index'))"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                    >
                        <i class="pi pi-arrow-left text-sm"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-slate-100">{{ equipment.name }}</h1>
                    <span class="rounded bg-cyan-500/20 px-2 py-0.5 text-xs font-medium text-cyan-400">
                        {{ equipment.part_number }}
                    </span>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-bold uppercase"
                        :class="{
                            'bg-cyan-600 text-white': equipment.type === 'item',
                            'bg-emerald-600 text-white': equipment.type === 'ppe',
                            'bg-violet-600 text-white': equipment.type === 'kit_component',
                        }"
                    >
                        {{ equipment.type }}
                    </span>
                    <span
                        v-if="!equipment.is_active"
                        class="rounded bg-red-500/20 px-2 py-0.5 text-xs font-medium text-red-400"
                    >
                        Inactive
                    </span>
                </div>
                <p v-if="equipment.description" class="mt-1 ml-11 text-sm text-slate-400">
                    {{ equipment.description }}
                </p>
            </div>
        </div>

        <!-- Equipment Info Card -->
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wide text-slate-500">Equipment Details</h2>
            <div class="grid grid-cols-2 gap-x-8 gap-y-3 md:grid-cols-3 lg:grid-cols-4">
                <div v-for="field in infoFields" :key="field.label">
                    <div class="text-xs text-slate-500">{{ field.label }}</div>
                    <div class="mt-0.5 text-sm font-medium text-slate-200">{{ field.value }}</div>
                </div>
            </div>

            <!-- Custom field requirements -->
            <div v-if="equipmentReqs.length > 0" class="mt-4 border-t border-slate-700 pt-4">
                <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Custom Fields</h3>
                <div class="flex flex-wrap gap-2">
                    <span
                        v-for="req in equipmentReqs"
                        :key="req.id"
                        class="rounded bg-slate-700 px-2 py-1 text-xs text-slate-300"
                    >
                        {{ req.field_name }}
                        <span v-if="req.is_required" class="ml-1 text-red-400">*</span>
                    </span>
                </div>
            </div>
        </div>

        <!-- Stock Overview (6 mini stats) -->
        <div class="mb-6 grid grid-cols-3 gap-3 md:grid-cols-6">
            <div
                v-for="card in stockCards"
                :key="card.label"
                class="rounded-xl border border-slate-700 bg-slate-800 p-4 text-center"
            >
                <div class="text-2xl font-bold" :class="colorMap[card.color]?.split(' ')[1] || 'text-slate-100'">
                    {{ card.value }}
                </div>
                <div class="mt-1 text-xs text-slate-500">{{ card.label }}</div>
            </div>
        </div>

        <!-- Stock by Condition -->
        <div v-if="stockLevel?.by_condition?.length" class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-5">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-500">Stock by Condition</h2>
            <div class="flex flex-wrap gap-3">
                <div
                    v-for="cond in stockLevel.by_condition"
                    :key="cond.code"
                    class="flex items-center gap-2 rounded-lg border border-slate-700 px-3 py-2"
                >
                    <span
                        class="rounded px-2 py-0.5 text-xs font-semibold"
                        :class="conditionBadge({ code: cond.code, name: cond.name }).class"
                    >
                        {{ cond.code }}
                    </span>
                    <span class="text-sm text-slate-300">{{ cond.name }}</span>
                    <span class="ml-1 text-sm font-bold text-slate-100">{{ cond.count }}</span>
                </div>
            </div>
        </div>

        <!-- Physical Items DataTable -->
        <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-700 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-100">Physical Items</h2>
                <span class="text-sm text-slate-400">{{ physicalItems?.total ?? 0 }} items</span>
            </div>

            <DataTable
                :value="physicalItems?.data || []"
                :rows="physicalItems?.per_page || 25"
                class="p-datatable-sm"
                :pt="{
                    root: { class: '!bg-slate-800 !border-0' },
                    headerRow: { class: '!bg-slate-900' },
                    column: {
                        headerCell: { class: '!bg-slate-900 !text-slate-400 !border-slate-700 !text-sm' },
                        bodyCell: { class: '!bg-slate-800 !text-slate-200 !border-slate-700' },
                    },
                }"
            >
                <template #empty>
                    <div class="py-6 text-center text-slate-500">No physical items registered.</div>
                </template>

                <Column field="serial_number" header="Serial Number" sortable>
                    <template #body="{ data }">
                        <span class="font-medium text-cyan-400">{{ data.serial_number || '—' }}</span>
                    </template>
                </Column>
                <Column header="Warehouse">
                    <template #body="{ data }">
                        <span v-if="data.warehouse" class="text-sm text-slate-300">{{ data.warehouse.name }}</span>
                        <span v-else-if="data.rig" class="text-sm text-violet-400">
                            <i class="pi pi-compass mr-1 text-xs"></i>{{ data.rig.name }}
                        </span>
                        <span v-else class="text-slate-500">—</span>
                    </template>
                </Column>
                <Column header="Condition">
                    <template #body="{ data }">
                        <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :class="conditionBadge(data.inventory_status).class"
                        >
                            {{ conditionBadge(data.inventory_status).label }}
                        </span>
                    </template>
                </Column>
                <Column field="created_at" header="Registered" sortable>
                    <template #body="{ data }">
                        <span class="text-sm text-slate-400">
                            {{ data.created_at ? new Date(data.created_at).toLocaleDateString() : '—' }}
                        </span>
                    </template>
                </Column>
                <Column field="next_inspection_date" header="Next Inspection">
                    <template #body="{ data }">
                        <span v-if="data.next_inspection_date" class="text-sm text-slate-400">
                            {{ new Date(data.next_inspection_date).toLocaleDateString() }}
                        </span>
                        <span v-else class="text-slate-600">—</span>
                    </template>
                </Column>
                <Column header="Actions" :style="{ width: '80px' }">
                    <template #body="{ data }">
                        <button
                            @click="openHistory(data)"
                            class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-700 hover:text-cyan-400 transition-colors"
                            title="Movement History"
                        >
                            <i class="pi pi-history text-sm"></i>
                        </button>
                    </template>
                </Column>
            </DataTable>

            <!-- Pagination -->
            <div v-if="physicalItems?.last_page > 1" class="border-t border-slate-700 px-5 py-3 flex items-center justify-between">
                <span class="text-sm text-slate-400">
                    Page {{ physicalItems.current_page }} of {{ physicalItems.last_page }}
                </span>
                <div class="flex gap-1">
                    <button
                        v-for="p in physicalItems.last_page"
                        :key="p"
                        @click="onPageChange({ page: p - 1 })"
                        class="rounded px-3 py-1 text-sm transition-colors"
                        :class="p === physicalItems.current_page
                            ? 'bg-cyan-500 text-slate-950 font-bold'
                            : 'text-slate-400 hover:bg-slate-700'"
                    >
                        {{ p }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Movement History Dialog -->
        <Dialog
            v-model:visible="historyVisible"
            :header="`Movement History — ${historyItemLabel}`"
            modal
            :style="{ width: '700px' }"
            :pt="{
                root: { class: '!bg-slate-800 !border-slate-700' },
                header: { class: '!bg-slate-800 !text-slate-100 !border-b !border-slate-700' },
                content: { class: '!bg-slate-800 !p-0' },
            }"
        >
            <div v-if="historyLoading" class="flex items-center justify-center py-8">
                <i class="pi pi-spin pi-spinner text-xl text-cyan-400"></i>
            </div>
            <div v-else-if="historyData.length === 0" class="py-8 text-center text-slate-500">
                No movement history found.
            </div>
            <div v-else class="max-h-[500px] overflow-y-auto">
                <!-- Timeline -->
                <div class="relative pl-8 py-4 pr-4">
                    <div class="absolute left-4 top-0 bottom-0 w-px bg-slate-700"></div>
                    <div
                        v-for="(entry, idx) in historyData"
                        :key="idx"
                        class="relative mb-4 last:mb-0"
                    >
                        <div class="absolute -left-4 top-1 flex h-5 w-5 items-center justify-center rounded-full bg-slate-800 border-2 border-cyan-500">
                            <div class="h-2 w-2 rounded-full bg-cyan-400"></div>
                        </div>
                        <div class="ml-4 rounded-lg border border-slate-700 bg-slate-900 p-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-slate-100">{{ entry.status_name }}</span>
                                <span class="text-xs text-slate-500">
                                    {{ entry.created_at ? new Date(entry.created_at).toLocaleString() : '' }}
                                </span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-3 text-xs text-slate-400">
                                <span v-if="entry.warehouse_name">
                                    <i class="pi pi-building mr-1"></i>{{ entry.warehouse_name }}
                                </span>
                                <span v-if="entry.user_name">
                                    <i class="pi pi-user mr-1"></i>{{ entry.user_name }}
                                </span>
                                <span v-if="entry.in">
                                    <span class="text-emerald-400">+{{ entry.in }}</span> in
                                </span>
                                <span v-if="entry.out">
                                    <span class="text-red-400">-{{ entry.out }}</span> out
                                </span>
                            </div>
                            <p v-if="entry.notes" class="mt-1 text-xs text-slate-500 italic">{{ entry.notes }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Dialog>
    </div>
</template>
