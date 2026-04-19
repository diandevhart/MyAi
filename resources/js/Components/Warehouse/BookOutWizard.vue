<script setup>
import { ref, reactive, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Textarea from 'primevue/textarea';
import axios from 'axios';
import { toast } from 'vue-toastflow';

const props = defineProps({
    visible: { type: Boolean, default: false },
    warehouseId: { type: Number, required: true },
});

const emit = defineEmits(['update:visible', 'close', 'booked-out']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val),
});

const currentStep = ref(1);
const submitting = ref(false);

// ─── Step 1: Destination ───────────────────────────────
const destinationType = ref('');
const destinationId = ref(null);
const warehouses = ref([]);
const rigs = ref([]);
const loadingDestinations = ref(false);

async function loadDestinations() {
    loadingDestinations.value = true;
    try {
        // Load warehouses via stat detail or a simple GET
        const whRes = await axios.get(route('warehouses.index'), {
            headers: { 'X-Inertia': false, Accept: 'application/json' },
        });
        const whData = whRes.data?.warehouses?.data || whRes.data?.warehouses || [];
        warehouses.value = whData.filter(w => w.id !== props.warehouseId);
    } catch {
        // Silently handle — user can still try
    }
    loadingDestinations.value = false;
}

function selectDestType(type) {
    destinationType.value = type;
    destinationId.value = null;
}

const selectedDestinationName = computed(() => {
    if (destinationType.value === 'warehouse') {
        return warehouses.value.find(w => w.id === destinationId.value)?.name || '';
    }
    if (destinationType.value === 'rig') {
        return rigs.value.find(r => r.id === destinationId.value)?.rig_name || '';
    }
    return '';
});

const canProceedStep1 = computed(() => destinationType.value && destinationId.value);

// ─── Step 2: Select Items ──────────────────────────────
const availableItems = ref([]);
const selectedItems = ref([]);
const itemsLoading = ref(false);
const itemFilter = ref('');

async function loadAvailableItems() {
    itemsLoading.value = true;
    try {
        const { data } = await axios.get(
            route('warehouses.stat-detail', { id: props.warehouseId, statType: 'available' })
        );
        availableItems.value = data.items || data;
    } catch {
        toast({ type: 'error', message: 'Failed to load available items.' });
    } finally {
        itemsLoading.value = false;
    }
}

// ─── Step 3: Review & Submit ───────────────────────────
const notes = ref('');

async function submitBookOut() {
    submitting.value = true;
    try {
        const ids = selectedItems.value.map(i => i.id);
        const { data } = await axios.post(
            route('inventory.book-out', { warehouseId: props.warehouseId }),
            {
                items: ids,
                destination_type: destinationType.value,
                destination_id: destinationId.value,
                notes: notes.value,
            }
        );
        toast({ type: 'success', message: data.message || `${ids.length} item(s) booked out.` });
        emit('booked-out');
        closeWizard();
    } catch (err) {
        const msg = err.response?.data?.message || 'Book out failed.';
        toast({ type: 'error', message: msg });
    } finally {
        submitting.value = false;
    }
}

function closeWizard() {
    emit('close');
    emit('update:visible', false);
    resetWizard();
}

function resetWizard() {
    currentStep.value = 1;
    destinationType.value = '';
    destinationId.value = null;
    selectedItems.value = [];
    notes.value = '';
    itemFilter.value = '';
}

watch(() => props.visible, (val) => {
    if (val) {
        loadDestinations();
    }
});

function goToStep2() {
    currentStep.value = 2;
    loadAvailableItems();
}
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        header="Book Out Equipment"
        :modal="true"
        :closable="true"
        :style="{ width: '900px' }"
        @hide="closeWizard"
        :pt="{
            root: { class: '!bg-slate-800 !border-slate-700' },
            header: { class: '!bg-slate-900 !text-slate-100 !border-b !border-slate-700' },
            content: { class: '!bg-slate-800 !text-slate-100 !p-0' },
        }"
    >
        <div class="p-5">
            <!-- Step Indicator -->
            <div class="mb-6 flex items-center justify-center gap-3 text-sm">
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                        :class="currentStep >= 1 ? 'bg-cyan-400 text-slate-950' : 'bg-slate-700 text-slate-400'">1</div>
                    <span :class="currentStep >= 1 ? 'text-cyan-400' : 'text-slate-500'">Destination</span>
                </div>
                <div class="h-0.5 w-8 rounded" :class="currentStep >= 2 ? 'bg-cyan-400' : 'bg-slate-700'"></div>
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                        :class="currentStep >= 2 ? 'bg-cyan-400 text-slate-950' : 'bg-slate-700 text-slate-400'">2</div>
                    <span :class="currentStep >= 2 ? 'text-cyan-400' : 'text-slate-500'">Select Items</span>
                </div>
                <div class="h-0.5 w-8 rounded" :class="currentStep >= 3 ? 'bg-cyan-400' : 'bg-slate-700'"></div>
                <div class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold"
                        :class="currentStep >= 3 ? 'bg-cyan-400 text-slate-950' : 'bg-slate-700 text-slate-400'">3</div>
                    <span :class="currentStep >= 3 ? 'text-cyan-400' : 'text-slate-500'">Review</span>
                </div>
            </div>

            <!-- STEP 1: Destination -->
            <div v-if="currentStep === 1">
                <p class="mb-4 text-sm text-slate-400">Where are these items going?</p>

                <div class="mb-4 grid grid-cols-2 gap-4">
                    <!-- Warehouse card -->
                    <div
                        @click="selectDestType('warehouse')"
                        class="cursor-pointer rounded-xl border-2 bg-slate-900 p-5 text-center transition-all"
                        :class="destinationType === 'warehouse'
                            ? 'border-cyan-400 bg-cyan-500/10'
                            : 'border-slate-700 hover:border-cyan-500/50'"
                    >
                        <i class="pi pi-building text-3xl mb-2" :class="destinationType === 'warehouse' ? 'text-cyan-400' : 'text-slate-400'"></i>
                        <div class="text-sm font-medium" :class="destinationType === 'warehouse' ? 'text-cyan-400' : 'text-slate-300'">
                            Another Warehouse
                        </div>
                    </div>
                    <!-- Rig card -->
                    <div
                        @click="selectDestType('rig')"
                        class="cursor-pointer rounded-xl border-2 bg-slate-900 p-5 text-center transition-all"
                        :class="destinationType === 'rig'
                            ? 'border-cyan-400 bg-cyan-500/10'
                            : 'border-slate-700 hover:border-cyan-500/50'"
                    >
                        <i class="pi pi-compass text-3xl mb-2" :class="destinationType === 'rig' ? 'text-cyan-400' : 'text-slate-400'"></i>
                        <div class="text-sm font-medium" :class="destinationType === 'rig' ? 'text-cyan-400' : 'text-slate-300'">
                            Rig / Vessel
                        </div>
                    </div>
                </div>

                <!-- Destination selector -->
                <div v-if="destinationType === 'warehouse'" class="mb-4">
                    <label class="mb-1 block text-sm text-slate-400">Select Warehouse</label>
                    <Select
                        v-model="destinationId"
                        :options="warehouses"
                        optionLabel="name"
                        optionValue="id"
                        placeholder="Choose warehouse..."
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                    />
                </div>
                <div v-if="destinationType === 'rig'" class="mb-4">
                    <label class="mb-1 block text-sm text-slate-400">Select Rig / Vessel</label>
                    <Select
                        v-model="destinationId"
                        :options="rigs"
                        optionLabel="rig_name"
                        optionValue="id"
                        placeholder="Choose rig..."
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                    />
                </div>

                <div class="mt-6 flex justify-end">
                    <button
                        @click="goToStep2"
                        :disabled="!canProceedStep1"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors disabled:opacity-50"
                    >
                        Next<i class="pi pi-arrow-right ml-2 text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 2: Select Items -->
            <div v-if="currentStep === 2">
                <div class="mb-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="text-sm text-slate-400">
                            To: <span class="font-medium text-slate-100">{{ selectedDestinationName }}</span>
                        </span>
                    </div>
                    <span v-if="selectedItems.length" class="rounded bg-cyan-500/20 px-2 py-0.5 text-xs text-cyan-400">
                        {{ selectedItems.length }} selected
                    </span>
                </div>

                <!-- Filter -->
                <div class="relative mb-3">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input
                        v-model="itemFilter"
                        type="text"
                        placeholder="Filter items..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 py-2 pl-9 pr-3 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500"
                    />
                </div>

                <DataTable
                    v-model:selection="selectedItems"
                    :value="availableItems"
                    :loading="itemsLoading"
                    :rows="8"
                    :paginator="availableItems.length > 8"
                    :globalFilter="itemFilter"
                    :globalFilterFields="['name', 'part_number', 'serial_number']"
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
                    <Column selectionMode="multiple" headerStyle="width: 3rem" />
                    <Column field="name" header="Item Name" sortable />
                    <Column field="part_number" header="Part Number" sortable>
                        <template #body="{ data }">
                            <span class="text-cyan-400">{{ data.part_number }}</span>
                        </template>
                    </Column>
                    <Column field="serial_number" header="Serial Number" />
                    <Column field="condition" header="Condition" />
                    <Column field="date" header="Registered" />
                </DataTable>

                <div class="mt-4 flex justify-between">
                    <button
                        @click="currentStep = 1"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-600 transition-colors"
                    >
                        <i class="pi pi-arrow-left mr-2 text-xs"></i>Back
                    </button>
                    <button
                        @click="currentStep = 3"
                        :disabled="selectedItems.length === 0"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors disabled:opacity-50"
                    >
                        Review<i class="pi pi-arrow-right ml-2 text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Review -->
            <div v-if="currentStep === 3">
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-4">
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-400">Destination</span>
                        <span class="text-sm font-medium text-slate-100">
                            {{ selectedDestinationName }}
                            <span class="text-xs text-slate-500 ml-1">({{ destinationType }})</span>
                        </span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-400">Items</span>
                        <span class="text-sm font-medium text-slate-100">{{ selectedItems.length }} item(s)</span>
                    </div>

                    <div class="max-h-48 overflow-y-auto space-y-1">
                        <div v-for="item in selectedItems" :key="item.id"
                            class="flex items-center justify-between rounded bg-slate-800 px-3 py-2 text-sm"
                        >
                            <span class="text-slate-200">{{ item.name }}</span>
                            <span class="text-xs text-slate-400">{{ item.serial_number || item.part_number }}</span>
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Notes (optional)</label>
                        <Textarea
                            v-model="notes"
                            rows="2"
                            class="w-full !bg-slate-800 !border-slate-700 !text-slate-100"
                            placeholder="Shipping notes, references..."
                        />
                    </div>

                    <div class="flex items-center gap-2 rounded-lg bg-violet-500/10 p-3">
                        <i class="pi pi-info-circle text-violet-400"></i>
                        <span class="text-xs text-violet-300">Items will be marked as In Transit.</span>
                    </div>
                </div>

                <div class="mt-6 flex justify-between">
                    <button
                        @click="currentStep = 2"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-600 transition-colors"
                    >
                        <i class="pi pi-arrow-left mr-2 text-xs"></i>Back
                    </button>
                    <button
                        @click="submitBookOut"
                        :disabled="submitting"
                        class="rounded-lg bg-violet-500 px-5 py-2 text-sm font-semibold text-white hover:bg-violet-400 transition-colors disabled:opacity-50"
                    >
                        <i v-if="submitting" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                        {{ submitting ? 'Processing...' : 'Confirm Book Out' }}
                    </button>
                </div>
            </div>
        </div>
    </Dialog>
</template>
