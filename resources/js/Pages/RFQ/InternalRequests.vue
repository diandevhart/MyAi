<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import Select from 'primevue/select';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    requests: Object,
    warehouses: { type: Array, default: () => [] },
    equipmentOptions: { type: Array, default: () => [] },
    canApproveInternalRequests: { type: Boolean, default: false },
    managedWarehouseIds: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    recentActivities: { type: Array, default: () => [] },
});

const requestList = computed(() => props.requests?.data || []);
// Per-row: can user approve this specific request?
function canApproveRow(row) {
    if (!props.canApproveInternalRequests) return false;
    // Empty managedWarehouseIds = global manager (manages all)
    if (!props.managedWarehouseIds.length) return true;
    return props.managedWarehouseIds.includes(row.warehouse_id);
}

const activeTab = ref(props.filters?.status || 'all');
const tabs = [
    { key: 'all', label: 'All' },
    { key: 'pending', label: 'Pending' },
    { key: 'approved', label: 'Approved' },
    { key: 'rejected', label: 'Rejected' },
    { key: 'draft', label: 'Draft' },
];

const expandedRows = ref({});

const filtered = computed(() => {
    return requestList.value;
});

function setTab(tabKey) {
    activeTab.value = tabKey;
    router.get(
        route('rfq.internal.index'),
        tabKey === 'all' ? {} : { status: tabKey },
        { preserveState: true, preserveScroll: true, replace: true }
    );
}

function statusColor(status) {
    const map = {
        draft: 'bg-slate-600/30 text-slate-400',
        pending: 'bg-amber-500/20 text-amber-400',
        approved: 'bg-emerald-500/20 text-emerald-400',
        rejected: 'bg-red-500/20 text-red-400',
    };
    return map[status] || 'bg-slate-600/30 text-slate-400';
}

function urgencyColor(urgency) {
    const map = {
        low: 'bg-slate-600/30 text-slate-400',
        medium: 'bg-blue-500/20 text-blue-400',
        high: 'bg-amber-500/20 text-amber-400',
        critical: 'bg-red-500/20 text-red-400',
    };
    return map[urgency] || 'bg-slate-600/30 text-slate-400';
}

// Reject dialog
const showRejectDialog = ref(false);
const rejectingId = ref(null);
const rejectionReason = ref('');

async function approveRequest(id) {
    try {
        await axios.post(route('rfq.internal.approve', id));
        toast.success('Request approved');
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to approve');
    }
}

function openReject(id) {
    rejectingId.value = id;
    rejectionReason.value = '';
    showRejectDialog.value = true;
}

async function confirmReject() {
    try {
        await axios.post(route('rfq.internal.reject', rejectingId.value), { rejection_reason: rejectionReason.value });
        toast.success('Request rejected');
        showRejectDialog.value = false;
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to reject');
    }
}

// New Request dialog
const showNewDialog = ref(false);
const newForm = ref({
    warehouse_id: null,
    urgency: 'medium',
    notes: '',
    items: [{ inventory_equipment_id: null, quantity: 1, new_item_name: '', new_item_description: '', new_item_estimated_budget: null }],
});

const warehouseOptions = computed(() =>
    props.warehouses.map(w => ({
        label: `${w.name}${w.code ? ` (${w.code})` : ''}`,
        value: w.id,
    }))
);

const equipmentSelectOptions = computed(() =>
    props.equipmentOptions.map(eq => ({
        label: `${eq.name}${eq.part_number ? ` [${eq.part_number}]` : ''}`,
        value: eq.id,
    }))
);

function addItem() {
    newForm.value.items.push({ inventory_equipment_id: null, quantity: 1, new_item_name: '', new_item_description: '', new_item_estimated_budget: null });
}

function removeItem(idx) {
    newForm.value.items.splice(idx, 1);
}

async function submitNewRequest() {
    if (!newForm.value.items.some(i => i.inventory_equipment_id || i.new_item_name?.trim())) {
        toast.error('Add at least one item with equipment or new item name');
        return;
    }

    try {
        await axios.post(route('rfq.internal.store'), newForm.value);
        toast.success('Request created');
        showNewDialog.value = false;
        router.reload();
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to create request');
    }
}

const urgencyOptions = [
    { label: 'Low', value: 'low' },
    { label: 'Medium', value: 'medium' },
    { label: 'High', value: 'high' },
    { label: 'Critical', value: 'critical' },
];

function formatAction(action) {
    const map = {
        internal_request_created: 'Internal request created',
        internal_request_approved: 'Internal request approved',
        internal_request_rejected: 'Internal request rejected',
        supplier_rfq_created: 'Supplier RFQ created',
        supplier_quote_submitted: 'Supplier quote submitted',
        supplier_rfq_awarded: 'Supplier RFQ awarded',
        user_access_updated: 'User access updated',
    };
    return map[action] || (action || '').replaceAll('_', ' ');
}

function formatActivityDetail(activity) {
    const m = activity?.metadata || {};

    if (activity?.action === 'user_access_updated') {
        const email = m.target_user_email ? `User: ${m.target_user_email}` : 'User access changed';
        const roles = Array.isArray(m.after_roles) ? m.after_roles.join(', ') : '';
        const permissions = Array.isArray(m.after_permissions) ? m.after_permissions.length : 0;
        return `${email}${roles ? ` | Roles: ${roles}` : ''}${permissions ? ` | Direct perms: ${permissions}` : ''}`;
    }

    if (activity?.subject_type === 'internal_rfq_request' && activity?.subject_id) {
        return `Request #${activity.subject_id}`;
    }

    if (activity?.subject_type === 'supplier_quote_request' && activity?.subject_id) {
        return `RFQ #${activity.subject_id}`;
    }

    return 'Procurement timeline event';
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-slate-100">Internal Requests</h1>
            <button @click="showNewDialog = true" class="flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">
                <i class="pi pi-plus text-xs"></i> New Request
            </button>
        </div>

        <!-- Recent Activity -->
        <div v-if="props.recentActivities.length" class="mb-5 rounded-xl border border-slate-700 bg-slate-800 p-4">
            <h3 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-400">Recent Procurement Activity</h3>
            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="activity in props.recentActivities.slice(0, 6)" :key="activity.id" class="rounded-lg bg-slate-900 px-3 py-2">
                    <div class="text-xs text-slate-300 capitalize">{{ formatAction(activity.action) }}</div>
                    <div class="mt-0.5 text-[11px] text-slate-400">{{ formatActivityDetail(activity) }}</div>
                    <div class="mt-1 text-[11px] text-slate-500">
                        {{ activity.user?.name || 'System' }} • {{ new Date(activity.created_at).toLocaleString() }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="mb-5 flex gap-1 border-b border-slate-700">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                @click="setTab(tab.key)"
                class="px-4 py-2.5 text-sm font-medium transition-colors"
                :class="activeTab === tab.key
                    ? 'text-cyan-400 border-b-2 border-cyan-400 bg-cyan-500/10'
                    : 'text-slate-400 hover:text-slate-200 border-b-2 border-transparent'"
            >
                {{ tab.label }}
                <span class="ml-1.5 text-xs opacity-60">({{ tab.key === 'all' ? requestList.length : requestList.filter(r => r.status === tab.key).length }})</span>
            </button>
        </div>

        <!-- DataTable -->
        <div class="rounded-xl border border-slate-700 overflow-hidden">
            <DataTable
                :value="filtered"
                :paginator="true"
                :rows="15"
                :rowHover="true"
                v-model:expandedRows="expandedRows"
                dataKey="id"
                class="[&_.p-datatable-thead>tr>th]:!bg-slate-800 [&_.p-datatable-thead>tr>th]:!text-slate-400 [&_.p-datatable-thead>tr>th]:!border-slate-700 [&_.p-datatable-tbody>tr>td]:!bg-slate-900 [&_.p-datatable-tbody>tr>td]:!text-slate-300 [&_.p-datatable-tbody>tr>td]:!border-slate-700 [&_.p-datatable-tbody>tr:hover>td]:!bg-slate-800 [&_.p-paginator]:!bg-slate-900 [&_.p-paginator]:!border-slate-700 [&_.p-paginator]:!text-slate-400"
                sortMode="single"
                removableSort
            >
                <Column expander style="width: 3rem" />
                <Column field="id" header="ID" sortable style="width: 70px">
                    <template #body="{ data }">
                        <span class="font-mono text-xs text-slate-400">#{{ data.id }}</span>
                    </template>
                </Column>
                <Column header="Requester" sortable sortField="requester.name">
                    <template #body="{ data }">
                        <span class="text-slate-200">{{ data.requester?.name || '—' }}</span>
                    </template>
                </Column>
                <Column header="Warehouse" sortable sortField="warehouse.name">
                    <template #body="{ data }">
                        <span class="text-slate-300">{{ data.warehouse?.name || '—' }}</span>
                    </template>
                </Column>
                <Column field="urgency" header="Urgency" sortable>
                    <template #body="{ data }">
                        <span :class="['inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', urgencyColor(data.urgency)]">
                            <span v-if="data.urgency === 'critical'" class="h-1.5 w-1.5 rounded-full bg-red-400 animate-pulse"></span>
                            {{ data.urgency }}
                        </span>
                    </template>
                </Column>
                <Column header="Items" style="width: 70px">
                    <template #body="{ data }">{{ data.items?.length || 0 }}</template>
                </Column>
                <Column field="status" header="Status" sortable>
                    <template #body="{ data }">
                        <span :class="['inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize', statusColor(data.status)]">
                            {{ data.status }}
                        </span>
                    </template>
                </Column>
                <Column field="created_at" header="Date" sortable>
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ new Date(data.created_at).toLocaleDateString() }}</span>
                    </template>
                </Column>
                <Column header="Actions" style="width: 160px">
                    <template #body="{ data }">
                        <div class="flex items-center gap-2">
                            <button v-if="canApproveRow(data) && data.status === 'pending'" @click="approveRequest(data.id)" class="rounded-lg bg-emerald-500/20 px-3 py-1.5 text-xs font-semibold text-emerald-400 hover:bg-emerald-500/30 transition-colors">
                                Approve
                            </button>
                            <button v-if="canApproveRow(data) && data.status === 'pending'" @click="openReject(data.id)" class="rounded-lg bg-red-500/20 px-3 py-1.5 text-xs font-semibold text-red-400 hover:bg-red-500/30 transition-colors">
                                Reject
                            </button>
                            <span v-if="!canApproveRow(data) && data.status === 'pending'" class="text-xs text-slate-500">View only</span>
                        </div>
                    </template>
                </Column>

                <!-- Expanded Row: Line Items -->
                <template #expansion="{ data }">
                    <div class="p-4 bg-slate-800/50">
                        <h4 class="text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Line Items</h4>
                        <div class="space-y-2">
                            <div v-for="item in (data.items || [])" :key="item.id" class="flex items-center gap-4 rounded-lg bg-slate-800 p-3 border border-slate-700">
                                <div class="flex-1">
                                    <span class="text-sm text-slate-200">
                                        {{ item.inventory_equipment?.name || item.new_item_name || 'Unknown Item' }}
                                    </span>
                                    <span v-if="item.new_item_name && !item.inventory_equipment_id" class="ml-2 text-xs bg-violet-500/20 text-violet-400 rounded-full px-2 py-0.5">New</span>
                                </div>
                                <div class="text-sm text-slate-400">Qty: {{ item.quantity }}</div>
                                <div v-if="item.new_item_estimated_budget" class="text-sm text-slate-400">
                                    Budget: R {{ Number(item.new_item_estimated_budget).toLocaleString('en-ZA', { minimumFractionDigits: 2 }) }}
                                </div>
                            </div>
                        </div>
                        <div v-if="data.rejection_reason" class="mt-3 rounded-lg bg-red-500/10 border border-red-500/20 p-3">
                            <span class="text-xs font-semibold text-red-400">Rejection Reason:</span>
                            <p class="text-sm text-red-300 mt-1">{{ data.rejection_reason }}</p>
                        </div>
                    </div>
                </template>
            </DataTable>
        </div>

        <!-- Reject Dialog -->
        <Dialog
            v-model:visible="showRejectDialog"
            header="Reject Request"
            modal
            :style="{ width: '440px' }"
            :pt="{ root: { class: '!bg-slate-800 !border-slate-700' }, header: { class: '!bg-slate-800 !text-slate-100 !border-slate-700' }, content: { class: '!bg-slate-800 !text-slate-300' } }"
        >
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-400">Rejection Reason</label>
                <Textarea v-model="rejectionReason" rows="3" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" placeholder="Provide a reason for rejection..." />
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <button @click="showRejectDialog = false" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors">Cancel</button>
                    <button @click="confirmReject" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-400 transition-colors">Reject</button>
                </div>
            </template>
        </Dialog>

        <!-- New Request Dialog -->
        <Dialog
            v-model:visible="showNewDialog"
            header="New Internal Request"
            modal
            :style="{ width: '640px' }"
            :pt="{ root: { class: '!bg-slate-800 !border-slate-700' }, header: { class: '!bg-slate-800 !text-slate-100 !border-slate-700' }, content: { class: '!bg-slate-800 !text-slate-300' } }"
        >
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Warehouse *</label>
                        <Select
                            v-model="newForm.warehouse_id"
                            :options="warehouseOptions"
                            optionLabel="label"
                            optionValue="value"
                            filter
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg"
                            placeholder="Select warehouse"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-400">Urgency</label>
                        <Select v-model="newForm.urgency" :options="urgencyOptions" optionLabel="label" optionValue="value" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Notes</label>
                    <Textarea v-model="newForm.notes" rows="2" class="w-full !bg-slate-900 !border-slate-700 !text-slate-100 rounded-lg" />
                </div>

                <!-- Items -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-xs font-medium text-slate-400">Items</label>
                        <button @click="addItem" class="text-xs text-cyan-400 hover:text-cyan-300">+ Add Item</button>
                    </div>
                    <div class="space-y-3">
                        <div v-for="(item, idx) in newForm.items" :key="idx" class="rounded-lg border border-slate-700 bg-slate-900 p-3">
                            <div class="grid grid-cols-3 gap-3">
                                <div class="col-span-3">
                                    <label class="mb-1 block text-[10px] text-slate-500">Existing Equipment (optional)</label>
                                    <Select
                                        v-model="item.inventory_equipment_id"
                                        :options="equipmentSelectOptions"
                                        optionLabel="label"
                                        optionValue="value"
                                        filter
                                        showClear
                                        class="w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm"
                                        placeholder="Select existing equipment"
                                    />
                                </div>
                                <div class="col-span-2">
                                    <label class="mb-1 block text-[10px] text-slate-500">Item Name (new)</label>
                                    <InputText v-model="item.new_item_name" :disabled="!!item.inventory_equipment_id" class="w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm" placeholder="New item name" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] text-slate-500">Quantity</label>
                                    <InputText v-model.number="item.quantity" type="number" min="1" class="w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm" />
                                </div>
                                <div class="col-span-3">
                                    <label class="mb-1 block text-[10px] text-slate-500">New Item Description</label>
                                    <InputText v-model="item.new_item_description" :disabled="!!item.inventory_equipment_id" class="w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm" placeholder="Optional description for new item" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-[10px] text-slate-500">Est. Budget</label>
                                    <InputText v-model.number="item.new_item_estimated_budget" type="number" step="0.01" class="w-full !bg-slate-800 !border-slate-600 !text-slate-100 rounded-lg text-sm" />
                                </div>
                                <div class="flex items-end">
                                    <button v-if="newForm.items.length > 1" @click="removeItem(idx)" class="rounded-lg text-red-400 hover:text-red-300 text-sm px-2 py-1.5">
                                        <i class="pi pi-trash"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end gap-3">
                    <button @click="showNewDialog = false" class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors">Cancel</button>
                    <button @click="submitNewRequest" class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors">Create Request</button>
                </div>
            </template>
        </Dialog>
    </div>
</template>
