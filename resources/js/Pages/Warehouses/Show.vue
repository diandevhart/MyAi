<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Menu from 'primevue/menu';
import InputText from 'primevue/inputtext';
import axios from 'axios';
import { toast } from 'vue-toastflow';
import RegisterEquipmentWizard from '@/Components/Warehouse/RegisterEquipmentWizard.vue';
import BookOutWizard from '@/Components/Warehouse/BookOutWizard.vue';
import ReceiveItems from '@/Components/Warehouse/ReceiveItems.vue';
import QuarantinePanel from '@/Components/Warehouse/QuarantinePanel.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    warehouse: { type: Object, required: true },
    initialStats: { type: Object, default: () => ({}) },
});

// ─── Stats ─────────────────────────────────────────────
const stats = reactive({
    total_items: props.initialStats?.total_items ?? 0,
    available: props.initialStats?.available ?? 0,
    quarantine: props.initialStats?.quarantine ?? 0,
    in_use: props.initialStats?.in_use ?? 0,
    inbound: props.initialStats?.inbound ?? 0,
    outbound: props.initialStats?.outbound ?? 0,
    low_stock: props.initialStats?.low_stock ?? 0,
    inspections_due: props.initialStats?.inspections_due ?? 0,
});

const statCards = computed(() => [
    { key: 'total_items', label: 'Total Items', icon: 'pi pi-box', color: 'cyan', value: stats.total_items, statType: 'available' },
    { key: 'available', label: 'Available', icon: 'pi pi-check-circle', color: 'emerald', value: stats.available, statType: 'available' },
    { key: 'quarantine', label: 'In Quarantine', icon: 'pi pi-shield', color: 'amber', value: stats.quarantine, statType: 'quarantine' },
    { key: 'in_use', label: 'In Use', icon: 'pi pi-cog', color: 'violet', value: stats.in_use, statType: 'in_use' },
    { key: 'inbound', label: 'Inbound', icon: 'pi pi-arrow-down', color: 'blue', value: stats.inbound, statType: 'inbound' },
    { key: 'outbound', label: 'Outbound', icon: 'pi pi-arrow-up', color: 'orange', value: stats.outbound, statType: 'outbound' },
    { key: 'low_stock', label: 'Low Stock', icon: 'pi pi-exclamation-triangle', color: 'red', value: stats.low_stock, statType: 'low_stock' },
    { key: 'inspections_due', label: 'Inspections Due', icon: 'pi pi-calendar', color: 'yellow', value: stats.inspections_due, statType: 'inspections_due' },
]);

const colorMap = {
    cyan: { bg: 'bg-cyan-400/20', text: 'text-cyan-400', border: 'border-l-cyan-500' },
    emerald: { bg: 'bg-emerald-400/20', text: 'text-emerald-400', border: 'border-l-emerald-500' },
    amber: { bg: 'bg-amber-400/20', text: 'text-amber-400', border: 'border-l-amber-500' },
    violet: { bg: 'bg-violet-400/20', text: 'text-violet-400', border: 'border-l-violet-500' },
    blue: { bg: 'bg-blue-400/20', text: 'text-blue-400', border: 'border-l-blue-500' },
    orange: { bg: 'bg-orange-400/20', text: 'text-orange-400', border: 'border-l-orange-500' },
    red: { bg: 'bg-red-400/20', text: 'text-red-400', border: 'border-l-red-500' },
    yellow: { bg: 'bg-yellow-400/20', text: 'text-yellow-400', border: 'border-l-yellow-500' },
};

// ─── Stat Detail Drill-Down ────────────────────────────
const detailPanel = reactive({
    visible: false,
    loading: false,
    statType: '',
    label: '',
    items: [],
});

async function openStatDetail(card) {
    if (detailPanel.statType === card.statType && detailPanel.visible) {
        detailPanel.visible = false;
        return;
    }
    detailPanel.visible = true;
    detailPanel.loading = true;
    detailPanel.statType = card.statType;
    detailPanel.label = card.label;
    detailPanel.items = [];

    try {
        const { data } = await axios.get(
            route('warehouses.stat-detail', { id: props.warehouse.id, statType: card.statType })
        );
        detailPanel.items = data.items || data;
    } catch {
        toast({ type: 'error', message: 'Failed to load detail data.' });
    } finally {
        detailPanel.loading = false;
    }
}

function closeDetail() {
    detailPanel.visible = false;
}

// ─── Operational Metrics ───────────────────────────────
const metrics = reactive({
    dock_to_stock: '--',
    received_this_month: 0,
    pending_orders: 0,
    defect_rate: '--',
});

// ─── Stock Breakdown ───────────────────────────────────
const stockBreakdown = ref([]);
const stockLoading = ref(false);
const stockFilter = ref('');

async function loadStockBreakdown() {
    stockLoading.value = true;
    try {
        const { data } = await axios.get(
            route('warehouses.stats', { id: props.warehouse.id })
        );
        // Update stats from response
        if (data.total_items !== undefined) stats.total_items = data.total_items;
        if (data.available !== undefined) stats.available = data.available;
        if (data.quarantine !== undefined) stats.quarantine = data.quarantine;
        if (data.in_use !== undefined) stats.in_use = data.in_use;
        if (data.inbound !== undefined) stats.inbound = data.inbound;
        if (data.outbound !== undefined) stats.outbound = data.outbound;
        if (data.low_stock !== undefined) stats.low_stock = data.low_stock;
        if (data.inspections_due !== undefined) stats.inspections_due = data.inspections_due;
        if (data.received_this_month !== undefined) metrics.received_this_month = data.received_this_month;
        if (data.pending_orders !== undefined) metrics.pending_orders = data.pending_orders;
        if (data.defect_rate !== undefined) metrics.defect_rate = data.defect_rate;
        if (data.stock_breakdown) stockBreakdown.value = data.stock_breakdown;
    } catch {
        toast({ type: 'error', message: 'Failed to load dashboard stats.' });
    } finally {
        stockLoading.value = false;
    }
}

// ─── Dialog Visibility ─────────────────────────────────
const registerVisible = ref(false);
const bookOutVisible = ref(false);
const receiveVisible = ref(false);
const quarantineVisible = ref(false);

function onWizardSuccess() {
    loadStockBreakdown();
}

// ─── Quick Actions Menu ────────────────────────────────
const quickActionsRef = ref(null);
const quickActionsItems = ref([
    { label: 'Receive Items', icon: 'pi pi-arrow-down', command: () => { receiveVisible.value = true; } },
    { label: 'Approve Quarantine', icon: 'pi pi-check', command: () => { quarantineVisible.value = true; } },
    { label: 'View Inspections', icon: 'pi pi-calendar', command: () => {} },
]);

function toggleQuickActions(event) {
    quickActionsRef.value.toggle(event);
}

// ─── Stock status helper ───────────────────────────────
function stockStatus(row) {
    const available = row.available ?? 0;
    const reorder = row.reorder_point ?? 0;
    if (reorder === 0) return { label: 'OK', class: 'bg-emerald-500/20 text-emerald-400' };
    if (available > reorder) return { label: 'OK', class: 'bg-emerald-500/20 text-emerald-400' };
    if (available === reorder) return { label: 'REORDER', class: 'bg-amber-500/20 text-amber-400' };
    return { label: 'LOW', class: 'bg-red-500/20 text-red-400' };
}

onMounted(() => {
    loadStockBreakdown();
});
</script>

<template>
    <div>
        <!-- Header Section -->
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-2xl font-bold text-slate-100">{{ warehouse.name }}</h1>
                        <span class="rounded bg-cyan-500/20 px-2 py-0.5 text-xs font-medium text-cyan-400">
                            {{ warehouse.code }}
                        </span>
                        <span class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-300">
                            {{ warehouse.warehouse_type?.replace('_', ' ') }}
                        </span>
                    </div>
                    <p v-if="warehouse.city || warehouse.province" class="mt-1 text-sm text-slate-400">
                        <i class="pi pi-map-marker mr-1 text-xs"></i>
                        {{ [warehouse.city, warehouse.province, warehouse.country].filter(Boolean).join(', ') }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="registerVisible = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 transition-colors hover:bg-cyan-400"
                >
                    <i class="pi pi-plus text-sm"></i>
                    Register Item
                </button>
                <button
                    @click="bookOutVisible = true"
                    class="inline-flex items-center gap-2 rounded-lg bg-violet-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-violet-400"
                >
                    <i class="pi pi-arrow-up-right text-sm"></i>
                    Book Out
                </button>
                <button
                    @click="toggleQuickActions"
                    class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300 transition-colors hover:bg-slate-700"
                >
                    <i class="pi pi-ellipsis-v text-sm"></i>
                </button>
                <Menu ref="quickActionsRef" :model="quickActionsItems" :popup="true" class="!bg-slate-800 !border-slate-700" />
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4 xl:grid-cols-4">
            <div
                v-for="card in statCards"
                :key="card.key"
                @click="openStatDetail(card)"
                class="relative cursor-pointer rounded-xl border border-slate-700 border-l-4 bg-slate-800 p-5 transition-all hover:border-slate-600"
                :class="colorMap[card.color].border"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-2xl font-bold text-slate-100">{{ card.value }}</div>
                        <div class="mt-1 text-sm text-slate-400">{{ card.label }}</div>
                    </div>
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-xl"
                        :class="colorMap[card.color].bg"
                    >
                        <i :class="[card.icon, colorMap[card.color].text]" class="text-lg"></i>
                    </div>
                </div>

                <!-- Pulse ring for low stock -->
                <span
                    v-if="card.key === 'low_stock' && card.value > 0"
                    class="absolute top-3 right-3 flex h-3 w-3"
                >
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex h-3 w-3 rounded-full bg-red-500"></span>
                </span>
            </div>
        </div>

        <!-- Stat Detail Drill-Down Panel -->
        <div
            v-if="detailPanel.visible"
            class="mb-6 rounded-xl border border-slate-700 bg-slate-800 overflow-hidden"
        >
            <div class="flex items-center justify-between border-b border-slate-700 px-5 py-3">
                <h3 class="text-sm font-semibold text-slate-100">
                    {{ detailPanel.label }} — Detail
                </h3>
                <button
                    @click="closeDetail"
                    class="flex h-7 w-7 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-700 hover:text-slate-100 transition-colors"
                >
                    <i class="pi pi-times text-sm"></i>
                </button>
            </div>
            <div class="p-0">
                <div v-if="detailPanel.loading" class="flex items-center justify-center py-8">
                    <i class="pi pi-spin pi-spinner text-xl text-cyan-400"></i>
                </div>
                <DataTable
                    v-else
                    :value="detailPanel.items"
                    :rows="10"
                    :paginator="detailPanel.items.length > 10"
                    class="p-datatable-sm"
                    :pt="{
                        root: { class: '!bg-slate-800 !border-0' },
                        headerRow: { class: '!bg-slate-900' },
                        column: {
                            headerCell: { class: '!bg-slate-900 !text-slate-400 !border-slate-700' },
                            bodyCell: { class: '!bg-slate-800 !text-slate-200 !border-slate-700' },
                        },
                    }"
                >
                    <Column field="name" header="Item Name" sortable />
                    <Column field="part_number" header="Part Number" sortable />
                    <Column field="serial_number" header="Serial Number" />
                    <Column field="status" header="Status">
                        <template #body="{ data }">
                            <span class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-300">
                                {{ data.status }}
                            </span>
                        </template>
                    </Column>
                    <Column field="location" header="Location" />
                    <Column field="date" header="Date" />
                </DataTable>
            </div>
        </div>

        <!-- Operational Metrics Row -->
        <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Dock-to-Stock</div>
                <div class="mt-2 text-xl font-bold text-slate-100">{{ metrics.dock_to_stock }}</div>
                <div class="mt-1 text-xs text-slate-400">avg days</div>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Received This Month</div>
                <div class="mt-2 text-xl font-bold text-emerald-400">{{ metrics.received_this_month }}</div>
                <div class="mt-1 text-xs text-slate-400">items</div>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Pending Orders</div>
                <div class="mt-2 text-xl font-bold text-amber-400">{{ metrics.pending_orders }}</div>
                <div class="mt-1 text-xs text-slate-400">RFQs</div>
            </div>
            <div class="rounded-xl border border-slate-700 bg-slate-800 p-4">
                <div class="text-xs font-medium uppercase tracking-wide text-slate-500">Supplier Defect Rate</div>
                <div class="mt-2 text-xl font-bold text-slate-100">
                    <span v-if="typeof metrics.defect_rate === 'number'">{{ metrics.defect_rate }}%</span>
                    <span v-else>{{ metrics.defect_rate }}</span>
                </div>
                <div class="mt-1 text-xs text-slate-400">this quarter</div>
            </div>
        </div>

        <!-- Stock Breakdown Section -->
        <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-700 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-100">Stock by Equipment Type</h2>
                <div class="relative w-64">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input
                        v-model="stockFilter"
                        type="text"
                        placeholder="Filter equipment..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 py-2 pl-9 pr-3 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                    />
                </div>
            </div>

            <DataTable
                :value="stockBreakdown"
                :rows="10"
                :paginator="stockBreakdown.length > 10"
                :loading="stockLoading"
                :globalFilterFields="['equipment_name', 'part_number']"
                :globalFilter="stockFilter"
                sortField="equipment_name"
                :sortOrder="1"
                class="p-datatable-sm"
                :pt="{
                    root: { class: '!bg-slate-800 !border-0' },
                    headerRow: { class: '!bg-slate-900' },
                    column: {
                        headerCell: { class: '!bg-slate-900 !text-slate-400 !border-slate-700 !text-sm' },
                        bodyCell: { class: '!bg-slate-800 !text-slate-200 !border-slate-700' },
                    },
                    paginator: {
                        root: { class: '!bg-slate-800 !border-slate-700 !text-slate-400' },
                    },
                }"
            >
                <template #empty>
                    <div class="py-6 text-center text-slate-500">No stock data available.</div>
                </template>
                <template #loading>
                    <div class="flex items-center justify-center py-6">
                        <i class="pi pi-spin pi-spinner text-lg text-cyan-400 mr-2"></i>
                        <span class="text-slate-400">Loading stock data...</span>
                    </div>
                </template>

                <Column field="equipment_name" header="Equipment Name" sortable>
                    <template #body="{ data }">
                        <span class="font-medium text-slate-100">{{ data.equipment_name }}</span>
                    </template>
                </Column>
                <Column field="part_number" header="Part Number" sortable>
                    <template #body="{ data }">
                        <span class="text-sm text-cyan-400">{{ data.part_number }}</span>
                    </template>
                </Column>
                <Column field="available" header="Available" sortable>
                    <template #body="{ data }">
                        <span class="font-bold text-emerald-400">{{ data.available ?? 0 }}</span>
                    </template>
                </Column>
                <Column field="quarantine" header="Quarantine" sortable>
                    <template #body="{ data }">
                        <span class="font-bold text-amber-400">{{ data.quarantine ?? 0 }}</span>
                    </template>
                </Column>
                <Column field="in_use" header="In Use" sortable>
                    <template #body="{ data }">
                        <span class="font-bold text-violet-400">{{ data.in_use ?? 0 }}</span>
                    </template>
                </Column>
                <Column field="total" header="Total" sortable>
                    <template #body="{ data }">
                        <span class="font-bold text-slate-100">{{ data.total ?? 0 }}</span>
                    </template>
                </Column>
                <Column field="reorder_point" header="Reorder Pt" sortable>
                    <template #body="{ data }">
                        <span class="text-sm text-slate-400">{{ data.reorder_point ?? '—' }}</span>
                    </template>
                </Column>
                <Column header="Status" :sortable="false">
                    <template #body="{ data }">
                        <span
                            class="rounded px-2 py-0.5 text-xs font-semibold"
                            :class="stockStatus(data).class"
                        >
                            {{ stockStatus(data).label }}
                        </span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Wizard / Panel Dialogs -->
        <RegisterEquipmentWizard
            v-model:visible="registerVisible"
            :warehouse-id="warehouse.id"
            @registered="onWizardSuccess"
            @close="registerVisible = false"
        />
        <BookOutWizard
            v-model:visible="bookOutVisible"
            :warehouse-id="warehouse.id"
            @booked-out="onWizardSuccess"
            @close="bookOutVisible = false"
        />
        <ReceiveItems
            v-model:visible="receiveVisible"
            :warehouse-id="warehouse.id"
            @received="onWizardSuccess"
            @close="receiveVisible = false"
        />
        <QuarantinePanel
            v-model:visible="quarantineVisible"
            :warehouse-id="warehouse.id"
            @approved="onWizardSuccess"
            @close="quarantineVisible = false"
        />
    </div>
</template>
