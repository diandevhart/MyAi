<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Textarea from 'primevue/textarea';
import axios from 'axios';
import { toast } from 'vue-toastflow';

const props = defineProps({
    visible: { type: Boolean, default: false },
    warehouseId: { type: Number, required: true },
});

const emit = defineEmits(['update:visible', 'close', 'approved']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val),
});

const quarantineItems = ref([]);
const selectedItems = ref([]);
const loading = ref(false);
const submitting = ref(false);

// Single item being inspected
const inspecting = ref(null);
const inspectionNotes = ref('');
const inspectingSubmitting = ref(false);

async function loadQuarantineItems() {
    loading.value = true;
    try {
        const { data } = await axios.get(
            route('warehouses.stat-detail', { id: props.warehouseId, statType: 'quarantine' })
        );
        quarantineItems.value = data.items || data;
    } catch {
        toast({ type: 'error', message: 'Failed to load quarantine items.' });
    } finally {
        loading.value = false;
    }
}

// ─── Single Approve ────────────────────────────────────
function openInspection(item) {
    inspecting.value = item;
    inspectionNotes.value = '';
}

function cancelInspection() {
    inspecting.value = null;
    inspectionNotes.value = '';
}

async function approveSingle() {
    if (!inspecting.value) return;
    inspectingSubmitting.value = true;
    try {
        const { data } = await axios.post(
            route('inventory.approve-quarantine', { id: inspecting.value.id }),
            { notes: inspectionNotes.value }
        );
        toast({ type: 'success', message: data.message || 'Item approved.' });
        inspecting.value = null;
        inspectionNotes.value = '';
        emit('approved');
        await loadQuarantineItems();
    } catch (err) {
        const msg = err.response?.data?.message || 'Approval failed.';
        toast({ type: 'error', message: msg });
    } finally {
        inspectingSubmitting.value = false;
    }
}

// ─── Bulk Approve ──────────────────────────────────────
async function approveBulk() {
    if (selectedItems.value.length === 0) return;
    submitting.value = true;
    try {
        const ids = selectedItems.value.map(i => i.id);
        const { data } = await axios.post(
            route('inventory.approve-quarantine-bulk'),
            { ids }
        );
        toast({ type: 'success', message: data.message || `${ids.length} item(s) approved.` });
        selectedItems.value = [];
        emit('approved');
        await loadQuarantineItems();
    } catch (err) {
        const msg = err.response?.data?.message || 'Bulk approval failed.';
        toast({ type: 'error', message: msg });
    } finally {
        submitting.value = false;
    }
}

function closePanel() {
    emit('close');
    emit('update:visible', false);
    selectedItems.value = [];
    inspecting.value = null;
}

watch(() => props.visible, (val) => {
    if (val) loadQuarantineItems();
});
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        header="Quarantine Approval"
        :modal="true"
        :closable="true"
        :style="{ width: '900px' }"
        @hide="closePanel"
        :pt="{
            root: { class: '!bg-slate-800 !border-slate-700' },
            header: { class: '!bg-slate-900 !text-slate-100 !border-b !border-slate-700' },
            content: { class: '!bg-slate-800 !text-slate-100 !p-0' },
        }"
    >
        <div class="p-5">
            <!-- Inspection sub-panel -->
            <div v-if="inspecting" class="mb-4 rounded-xl border border-amber-500/30 bg-amber-500/5 p-4 space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-semibold text-amber-400">
                        <i class="pi pi-search mr-2"></i>Inspecting: {{ inspecting.name }}
                    </h4>
                    <button @click="cancelInspection" class="text-xs text-slate-400 hover:text-slate-200">
                        <i class="pi pi-times"></i>
                    </button>
                </div>
                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div>
                        <span class="block text-xs text-slate-500">Part Number</span>
                        <span class="text-cyan-400">{{ inspecting.part_number }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500">Serial Number</span>
                        <span class="text-slate-200">{{ inspecting.serial_number || '—' }}</span>
                    </div>
                    <div>
                        <span class="block text-xs text-slate-500">Arrived</span>
                        <span class="text-slate-200">{{ inspecting.date || inspecting.received_at || '—' }}</span>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Inspection Notes</label>
                    <Textarea
                        v-model="inspectionNotes"
                        rows="2"
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        placeholder="Optional notes..."
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <button
                        @click="cancelInspection"
                        class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="approveSingle"
                        :disabled="inspectingSubmitting"
                        class="rounded-lg bg-emerald-500 px-4 py-1.5 text-xs font-semibold text-white hover:bg-emerald-400 transition-colors disabled:opacity-50"
                    >
                        <i v-if="inspectingSubmitting" class="pi pi-spin pi-spinner mr-1 text-xs"></i>
                        Approve Item
                    </button>
                </div>
            </div>

            <!-- Quarantine table -->
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm text-slate-400">
                    Items in quarantine awaiting inspection and approval.
                </p>
                <span v-if="selectedItems.length" class="rounded bg-amber-500/20 px-2 py-0.5 text-xs text-amber-400">
                    {{ selectedItems.length }} selected
                </span>
            </div>

            <DataTable
                v-model:selection="selectedItems"
                :value="quarantineItems"
                :loading="loading"
                :rows="10"
                :paginator="quarantineItems.length > 10"
                dataKey="id"
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
                <template #empty>
                    <div class="py-6 text-center text-slate-500">
                        <i class="pi pi-check-circle text-2xl mb-2 block text-emerald-400"></i>
                        No items in quarantine.
                    </div>
                </template>
                <Column selectionMode="multiple" headerStyle="width: 3rem" />
                <Column field="name" header="Item Name" sortable>
                    <template #body="{ data }">
                        <span class="font-medium text-slate-100">{{ data.name }}</span>
                    </template>
                </Column>
                <Column field="part_number" header="Part Number" sortable>
                    <template #body="{ data }">
                        <span class="text-cyan-400">{{ data.part_number }}</span>
                    </template>
                </Column>
                <Column field="serial_number" header="Serial Number" />
                <Column field="date" header="Quarantined">
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ data.date || data.quarantined_at || '—' }}</span>
                    </template>
                </Column>
                <Column header="Action" headerStyle="width: 6rem">
                    <template #body="{ data }">
                        <button
                            @click.stop="openInspection(data)"
                            class="rounded bg-amber-500/20 px-2 py-1 text-xs text-amber-400 hover:bg-amber-500/30 transition-colors"
                        >
                            <i class="pi pi-search mr-1 text-xs"></i>Inspect
                        </button>
                    </template>
                </Column>
            </DataTable>

            <div class="mt-4 flex justify-between">
                <button
                    @click="closePanel"
                    class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors"
                >
                    Close
                </button>
                <button
                    @click="approveBulk"
                    :disabled="selectedItems.length === 0 || submitting"
                    class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400 transition-colors disabled:opacity-50"
                >
                    <i v-if="submitting" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                    {{ submitting ? 'Approving...' : `Approve Selected (${selectedItems.length})` }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
