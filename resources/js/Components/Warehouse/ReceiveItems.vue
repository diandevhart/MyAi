<script setup>
import { ref, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import axios from 'axios';
import { toast } from 'vue-toastflow';

const props = defineProps({
    visible: { type: Boolean, default: false },
    warehouseId: { type: Number, required: true },
});

const emit = defineEmits(['update:visible', 'close', 'received']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val),
});

const inboundItems = ref([]);
const selectedItems = ref([]);
const loading = ref(false);
const submitting = ref(false);

async function loadInboundItems() {
    loading.value = true;
    try {
        const { data } = await axios.get(
            route('warehouses.stat-detail', { id: props.warehouseId, statType: 'inbound' })
        );
        inboundItems.value = data.items || data;
    } catch {
        toast({ type: 'error', message: 'Failed to load inbound items.' });
    } finally {
        loading.value = false;
    }
}

async function receiveSelected() {
    if (selectedItems.value.length === 0) return;
    submitting.value = true;
    try {
        const ids = selectedItems.value.map(i => i.id);
        const { data } = await axios.post(
            route('inventory.receive', { warehouseId: props.warehouseId }),
            { items: ids }
        );
        toast({ type: 'success', message: data.message || `${ids.length} item(s) received into quarantine.` });
        selectedItems.value = [];
        emit('received');
        // Refresh the list
        await loadInboundItems();
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to receive items.';
        toast({ type: 'error', message: msg });
    } finally {
        submitting.value = false;
    }
}

function closePanel() {
    emit('close');
    emit('update:visible', false);
    selectedItems.value = [];
}

watch(() => props.visible, (val) => {
    if (val) loadInboundItems();
});
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        header="Receive Inbound Items"
        :modal="true"
        :closable="true"
        :style="{ width: '800px' }"
        @hide="closePanel"
        :pt="{
            root: { class: '!bg-slate-800 !border-slate-700' },
            header: { class: '!bg-slate-900 !text-slate-100 !border-b !border-slate-700' },
            content: { class: '!bg-slate-800 !text-slate-100 !p-0' },
        }"
    >
        <div class="p-5">
            <div class="mb-4 flex items-center justify-between">
                <p class="text-sm text-slate-400">
                    Items in transit to this warehouse. Select items to receive.
                </p>
                <span v-if="selectedItems.length" class="rounded bg-emerald-500/20 px-2 py-0.5 text-xs text-emerald-400">
                    {{ selectedItems.length }} selected
                </span>
            </div>

            <DataTable
                v-model:selection="selectedItems"
                :value="inboundItems"
                :loading="loading"
                :rows="10"
                :paginator="inboundItems.length > 10"
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
                    <div class="py-6 text-center text-slate-500">No inbound items.</div>
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
                <Column field="from_warehouse" header="From">
                    <template #body="{ data }">
                        <span class="text-slate-300">{{ data.from_warehouse || data.location || '—' }}</span>
                    </template>
                </Column>
                <Column field="shipped_date" header="Shipped">
                    <template #body="{ data }">
                        <span class="text-xs text-slate-400">{{ data.shipped_date || data.date || '—' }}</span>
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
                    @click="receiveSelected"
                    :disabled="selectedItems.length === 0 || submitting"
                    class="rounded-lg bg-emerald-500 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-400 transition-colors disabled:opacity-50"
                >
                    <i v-if="submitting" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                    {{ submitting ? 'Receiving...' : `Receive Selected (${selectedItems.length})` }}
                </button>
            </div>
        </div>
    </Dialog>
</template>
