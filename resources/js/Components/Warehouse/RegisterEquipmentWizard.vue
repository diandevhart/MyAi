<script setup>
import { ref, reactive, watch, computed } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import DatePicker from 'primevue/datepicker';
import Textarea from 'primevue/textarea';
import axios from 'axios';
import { toast } from 'vue-toastflow';

const props = defineProps({
    visible: { type: Boolean, default: false },
    warehouseId: { type: Number, required: true },
});

const emit = defineEmits(['update:visible', 'close', 'registered']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (val) => emit('update:visible', val),
});

// ─── Wizard State ──────────────────────────────────────
const currentStep = ref(1);
const submitting = ref(false);

const steps = [
    { num: 1, label: 'Select Equipment' },
    { num: 2, label: 'Enter Details' },
    { num: 3, label: 'Review' },
    { num: 4, label: 'Done' },
];

// ─── Step 1: Equipment Selection ───────────────────────
const browseMode = ref('browse'); // 'browse' | 'search'
const cataloguePath = ref([]); // breadcrumb of { id, name }
const catalogueChildren = ref([]);
const catalogueLoading = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const selectedEquipment = ref(null);

async function loadRootNodes() {
    catalogueLoading.value = true;
    try {
        const { data } = await axios.get(route('catalogue.search', { q: '' }));
        // Load root-level groups instead
        const res = await axios.get(route('catalogue.index'));
        // For Inertia pages we get props, so use a search for empty or fetch children of null
        // Better: use children endpoint with a known root or search
        await loadChildren(null);
    } catch {
        // Fallback: load via search
    } finally {
        catalogueLoading.value = false;
    }
}

async function loadChildren(parentId) {
    catalogueLoading.value = true;
    try {
        if (parentId === null) {
            // Fetch root nodes — use search with empty to get all, then filter root
            const { data } = await axios.get(route('catalogue.search', { q: ' ' }));
            // Filter to root level (level 0)
            catalogueChildren.value = data.filter(n => n.level === 0);
            cataloguePath.value = [];
        } else {
            const { data } = await axios.get(route('catalogue.children', { id: parentId }));
            catalogueChildren.value = data;
        }
    } catch {
        toast({ type: 'error', message: 'Failed to load catalogue.' });
    } finally {
        catalogueLoading.value = false;
    }
}

function navigateInto(node) {
    if (node.type === 'group' || node.has_children) {
        cataloguePath.value.push({ id: node.id, name: node.name });
        loadChildren(node.id);
    } else {
        selectEquipmentFromCatalogue(node);
    }
}

function navigateBreadcrumb(index) {
    if (index < 0) {
        loadChildren(null);
    } else {
        const target = cataloguePath.value[index];
        cataloguePath.value = cataloguePath.value.slice(0, index + 1);
        loadChildren(target.id);
    }
}

function goBack() {
    if (cataloguePath.value.length <= 1) {
        loadChildren(null);
    } else {
        cataloguePath.value.pop();
        const parent = cataloguePath.value[cataloguePath.value.length - 1];
        loadChildren(parent.id);
    }
}

let searchTimeout = null;
function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(async () => {
        if (!searchQuery.value.trim()) {
            searchResults.value = [];
            return;
        }
        searchLoading.value = true;
        try {
            const { data } = await axios.get(route('catalogue.search', { q: searchQuery.value }));
            searchResults.value = data;
        } catch {
            toast({ type: 'error', message: 'Search failed.' });
        } finally {
            searchLoading.value = false;
        }
    }, 350);
}

function selectEquipmentFromCatalogue(node) {
    // node might be a catalogue group node — we need the actual equipment
    // For items, the node itself represents the group_requirement linked to equipment
    // We store the node info and will need equipment data
    selectedEquipment.value = {
        id: node.id,
        name: node.name,
        part_number: node.part_number || '',
        type: node.type,
        breadcrumb: node.breadcrumb || cataloguePath.value.map(p => p.name).join(' > '),
        equipment_reqs: node.equipment_reqs || [],
    };
    currentStep.value = 2;
}

function selectFromSearch(node) {
    selectedEquipment.value = {
        id: node.id,
        name: node.name,
        part_number: node.part_number || '',
        type: node.type,
        breadcrumb: node.breadcrumb || '',
        equipment_reqs: node.equipment_reqs || [],
    };
    currentStep.value = 2;
}

// ─── Step 2: Details Form ──────────────────────────────
const form = reactive({
    quantity: 1,
    serial_numbers: [''],
    inventory_status_id: 1,
    notes: '',
    dynamic_fields: {},
});

const conditionOptions = [
    { id: 1, code: 'A', name: 'New' },
    { id: 2, code: 'B', name: 'Good' },
    { id: 3, code: 'C', name: 'Fair' },
    { id: 4, code: 'D', name: 'Poor' },
    { id: 5, code: 'E', name: 'Quarantined' },
    { id: 6, code: 'X', name: 'Lost/Stolen' },
];

const selectedCondition = computed(() =>
    conditionOptions.find(c => c.id === form.inventory_status_id)
);

watch(() => form.quantity, (newVal) => {
    const count = Math.max(1, newVal || 1);
    while (form.serial_numbers.length < count) form.serial_numbers.push('');
    while (form.serial_numbers.length > count) form.serial_numbers.pop();
});

// ─── Step 3 → 4: Submit ────────────────────────────────
const submitResult = reactive({ success: false, message: '', count: 0 });

async function submitRegistration() {
    submitting.value = true;
    try {
        const body = {
            inventory_equipment_id: selectedEquipment.value.id,
            quantity: form.quantity,
            serial_numbers: form.serial_numbers.filter(s => s.trim()),
            inventory_status_id: form.inventory_status_id,
            notes: form.notes,
            dynamic_fields: form.dynamic_fields,
        };

        const { data } = await axios.post(
            route('inventory.store-equipment', { warehouseId: props.warehouseId }),
            body
        );

        submitResult.success = true;
        submitResult.message = data.message || `${form.quantity} item(s) registered successfully.`;
        submitResult.count = form.quantity;
        currentStep.value = 4;
    } catch (err) {
        const msg = err.response?.data?.message || 'Registration failed.';
        toast({ type: 'error', message: msg });
    } finally {
        submitting.value = false;
    }
}

function closeWizard() {
    if (submitResult.success) {
        emit('registered');
    }
    emit('close');
    emit('update:visible', false);
    resetWizard();
}

function resetWizard() {
    currentStep.value = 1;
    selectedEquipment.value = null;
    browseMode.value = 'browse';
    searchQuery.value = '';
    searchResults.value = [];
    form.quantity = 1;
    form.serial_numbers = [''];
    form.inventory_status_id = 1;
    form.notes = '';
    form.dynamic_fields = {};
    submitResult.success = false;
    submitResult.message = '';
    submitResult.count = 0;
}

// Load root on dialog open
watch(() => props.visible, (val) => {
    if (val) {
        loadChildren(null);
    }
});
</script>

<template>
    <Dialog
        v-model:visible="dialogVisible"
        header="Register Equipment"
        :modal="true"
        :closable="true"
        :maximizable="true"
        :style="{ width: '800px' }"
        @hide="closeWizard"
        :pt="{
            root: { class: '!bg-slate-800 !border-slate-700' },
            header: { class: '!bg-slate-900 !text-slate-100 !border-b !border-slate-700' },
            content: { class: '!bg-slate-800 !text-slate-100 !p-0' },
        }"
    >
        <div class="p-5">
            <!-- Step Indicator -->
            <div class="mb-6 flex items-center justify-center">
                <template v-for="(step, idx) in steps" :key="step.num">
                    <div class="flex items-center">
                        <!-- Circle -->
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold transition-colors"
                            :class="{
                                'bg-emerald-500 text-white': currentStep > step.num,
                                'bg-cyan-400 text-slate-950': currentStep === step.num,
                                'bg-slate-700 text-slate-400': currentStep < step.num,
                            }"
                        >
                            <i v-if="currentStep > step.num" class="pi pi-check text-xs"></i>
                            <span v-else>{{ step.num }}</span>
                        </div>
                        <span
                            class="ml-2 hidden text-xs font-medium sm:inline"
                            :class="{
                                'text-emerald-400': currentStep > step.num,
                                'text-cyan-400': currentStep === step.num,
                                'text-slate-500': currentStep < step.num,
                            }"
                        >
                            {{ step.label }}
                        </span>
                    </div>
                    <!-- Line -->
                    <div
                        v-if="idx < steps.length - 1"
                        class="mx-3 h-0.5 w-10 rounded"
                        :class="{
                            'bg-emerald-500': currentStep > step.num,
                            'bg-cyan-400': currentStep === step.num,
                            'bg-slate-700': currentStep < step.num,
                        }"
                    ></div>
                </template>
            </div>

            <!-- STEP 1: Select Equipment -->
            <div v-if="currentStep === 1">
                <!-- Mode toggle -->
                <div class="mb-4 flex gap-2">
                    <button
                        @click="browseMode = 'browse'"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="browseMode === 'browse'
                            ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/50'
                            : 'bg-slate-900 text-slate-400 border border-slate-700 hover:text-slate-300'"
                    >
                        <i class="pi pi-folder mr-2"></i>Browse Catalogue
                    </button>
                    <button
                        @click="browseMode = 'search'"
                        class="rounded-lg px-4 py-2 text-sm font-medium transition-colors"
                        :class="browseMode === 'search'
                            ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/50'
                            : 'bg-slate-900 text-slate-400 border border-slate-700 hover:text-slate-300'"
                    >
                        <i class="pi pi-search mr-2"></i>Search
                    </button>
                </div>

                <!-- Browse mode -->
                <div v-if="browseMode === 'browse'">
                    <!-- Breadcrumb -->
                    <div class="mb-3 flex items-center gap-1 text-sm">
                        <button @click="navigateBreadcrumb(-1)" class="text-cyan-400 hover:text-cyan-300">
                            <i class="pi pi-home text-xs"></i> Root
                        </button>
                        <template v-for="(crumb, idx) in cataloguePath" :key="crumb.id">
                            <span class="text-slate-600">/</span>
                            <button
                                @click="navigateBreadcrumb(idx)"
                                class="text-cyan-400 hover:text-cyan-300"
                            >
                                {{ crumb.name }}
                            </button>
                        </template>
                    </div>

                    <!-- Back button -->
                    <button
                        v-if="cataloguePath.length > 0"
                        @click="goBack"
                        class="mb-3 inline-flex items-center gap-2 rounded-lg bg-slate-900 px-3 py-1.5 text-xs text-slate-400 hover:text-slate-200 transition-colors"
                    >
                        <i class="pi pi-arrow-left text-xs"></i> Back
                    </button>

                    <!-- Loading -->
                    <div v-if="catalogueLoading" class="flex items-center justify-center py-8">
                        <i class="pi pi-spin pi-spinner text-lg text-cyan-400"></i>
                    </div>

                    <!-- Children list -->
                    <div v-else class="max-h-80 space-y-2 overflow-y-auto">
                        <div
                            v-for="node in catalogueChildren"
                            :key="node.id"
                            @click="navigateInto(node)"
                            class="flex cursor-pointer items-center justify-between rounded-lg bg-slate-900 p-3 transition-all hover:bg-slate-800 hover:border-cyan-500/30 border border-transparent"
                        >
                            <div class="flex items-center gap-3">
                                <i
                                    :class="node.has_children || node.type === 'group' ? 'pi pi-folder text-amber-400' : 'pi pi-box text-cyan-400'"
                                    class="text-base"
                                ></i>
                                <div>
                                    <div class="text-sm font-medium text-slate-100">{{ node.name }}</div>
                                    <div v-if="node.part_number" class="text-xs text-slate-400">{{ node.part_number }}</div>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="node.children_count || node.item_count"
                                    class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-400"
                                >
                                    {{ node.children_count || node.item_count || 0 }}
                                </span>
                                <i v-if="node.has_children || node.type === 'group'" class="pi pi-chevron-right text-xs text-slate-500"></i>
                            </div>
                        </div>
                        <div v-if="catalogueChildren.length === 0" class="py-6 text-center text-sm text-slate-500">
                            No items found at this level.
                        </div>
                    </div>
                </div>

                <!-- Search mode -->
                <div v-if="browseMode === 'search'">
                    <div class="relative mb-4">
                        <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                        <input
                            v-model="searchQuery"
                            @input="onSearch"
                            type="text"
                            placeholder="Search equipment by name..."
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 py-2.5 pl-10 pr-4 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                        />
                    </div>

                    <div v-if="searchLoading" class="flex items-center justify-center py-8">
                        <i class="pi pi-spin pi-spinner text-lg text-cyan-400"></i>
                    </div>

                    <div v-else class="max-h-80 space-y-2 overflow-y-auto">
                        <div
                            v-for="result in searchResults"
                            :key="result.id"
                            @click="selectFromSearch(result)"
                            class="cursor-pointer rounded-lg bg-slate-900 p-3 transition-all hover:bg-slate-800 border border-transparent hover:border-cyan-500/30"
                        >
                            <div class="flex items-center gap-3">
                                <i class="pi pi-box text-cyan-400"></i>
                                <div>
                                    <div class="text-sm font-medium text-slate-100">{{ result.name }}</div>
                                    <div class="text-xs text-slate-500">{{ result.breadcrumb }}</div>
                                </div>
                            </div>
                        </div>
                        <div v-if="searchResults.length === 0 && searchQuery" class="py-6 text-center text-sm text-slate-500">
                            No results found.
                        </div>
                    </div>
                </div>
            </div>

            <!-- STEP 2: Enter Details -->
            <div v-if="currentStep === 2">
                <!-- Selected equipment -->
                <div class="mb-4 rounded-lg bg-slate-900 p-3">
                    <div class="text-sm font-medium text-slate-100">{{ selectedEquipment?.name }}</div>
                    <div v-if="selectedEquipment?.part_number" class="text-xs text-slate-400">
                        {{ selectedEquipment.part_number }}
                    </div>
                    <div v-if="selectedEquipment?.breadcrumb" class="mt-1 text-xs text-slate-500">
                        {{ selectedEquipment.breadcrumb }}
                    </div>
                </div>

                <div class="space-y-4">
                    <!-- Quantity -->
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Quantity</label>
                        <InputNumber
                            v-model="form.quantity"
                            :min="1"
                            :max="999"
                            class="w-full"
                            :pt="{
                                root: { class: '!bg-slate-900' },
                                pcInputText: { root: { class: '!bg-slate-900 !border-slate-700 !text-slate-100' } },
                            }"
                        />
                    </div>

                    <!-- Serial Numbers -->
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">
                            Serial Number{{ form.quantity > 1 ? 's' : '' }}
                        </label>
                        <div class="space-y-2">
                            <div v-for="(_, idx) in form.serial_numbers" :key="idx" class="flex items-center gap-2">
                                <span v-if="form.quantity > 1" class="text-xs text-slate-500 w-6">{{ idx + 1 }}.</span>
                                <InputText
                                    v-model="form.serial_numbers[idx]"
                                    :placeholder="'Serial number' + (form.quantity > 1 ? ` #${idx + 1}` : '')"
                                    class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Condition -->
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Condition</label>
                        <Select
                            v-model="form.inventory_status_id"
                            :options="conditionOptions"
                            optionLabel="name"
                            optionValue="id"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>

                    <!-- Dynamic Fields -->
                    <template v-if="selectedEquipment?.equipment_reqs?.length">
                        <div v-for="field in selectedEquipment.equipment_reqs" :key="field.id">
                            <label class="mb-1 block text-sm text-slate-400">{{ field.field_name }}</label>

                            <InputText
                                v-if="field.field_type === 'text'"
                                v-model="form.dynamic_fields[field.id]"
                                class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            />
                            <InputNumber
                                v-else-if="field.field_type === 'number'"
                                v-model="form.dynamic_fields[field.id]"
                                class="w-full"
                                :pt="{
                                    pcInputText: { root: { class: '!bg-slate-900 !border-slate-700 !text-slate-100' } },
                                }"
                            />
                            <DatePicker
                                v-else-if="field.field_type === 'date'"
                                v-model="form.dynamic_fields[field.id]"
                                class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            />
                            <Select
                                v-else-if="field.field_type === 'select'"
                                v-model="form.dynamic_fields[field.id]"
                                :options="field.options || []"
                                class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            />
                        </div>
                    </template>

                    <!-- Notes -->
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Notes (optional)</label>
                        <Textarea
                            v-model="form.notes"
                            rows="3"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            placeholder="Any additional notes..."
                        />
                    </div>
                </div>

                <!-- Navigation -->
                <div class="mt-6 flex justify-between">
                    <button
                        @click="currentStep = 1"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-600 transition-colors"
                    >
                        <i class="pi pi-arrow-left mr-2 text-xs"></i>Back
                    </button>
                    <button
                        @click="currentStep = 3"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors"
                    >
                        Review<i class="pi pi-arrow-right ml-2 text-xs"></i>
                    </button>
                </div>
            </div>

            <!-- STEP 3: Review -->
            <div v-if="currentStep === 3">
                <div class="rounded-xl border border-slate-700 bg-slate-900 p-5 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-400">Equipment</span>
                        <span class="text-sm font-medium text-slate-100">{{ selectedEquipment?.name }}</span>
                    </div>
                    <div v-if="selectedEquipment?.part_number" class="flex justify-between">
                        <span class="text-sm text-slate-400">Part Number</span>
                        <span class="text-sm text-cyan-400">{{ selectedEquipment.part_number }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-400">Quantity</span>
                        <span class="text-sm font-medium text-slate-100">{{ form.quantity }} item(s)</span>
                    </div>
                    <div v-if="form.serial_numbers.some(s => s.trim())" class="flex justify-between">
                        <span class="text-sm text-slate-400">Serial Numbers</span>
                        <div class="text-right">
                            <div v-for="(sn, idx) in form.serial_numbers.filter(s => s.trim())" :key="idx" class="text-sm text-slate-100">
                                {{ sn }}
                            </div>
                        </div>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-slate-400">Condition</span>
                        <span class="text-sm text-slate-100">{{ selectedCondition?.name }} ({{ selectedCondition?.code }})</span>
                    </div>

                    <!-- Dynamic field values -->
                    <template v-if="selectedEquipment?.equipment_reqs?.length">
                        <div v-for="field in selectedEquipment.equipment_reqs" :key="field.id" class="flex justify-between">
                            <span class="text-sm text-slate-400">{{ field.field_name }}</span>
                            <span class="text-sm text-slate-100">{{ form.dynamic_fields[field.id] || '—' }}</span>
                        </div>
                    </template>

                    <div v-if="form.notes" class="flex justify-between">
                        <span class="text-sm text-slate-400">Notes</span>
                        <span class="text-sm text-slate-100">{{ form.notes }}</span>
                    </div>

                    <div class="border-t border-slate-700 pt-3">
                        <div class="flex items-center gap-2 rounded-lg bg-amber-500/10 p-3">
                            <i class="pi pi-info-circle text-amber-400"></i>
                            <span class="text-xs text-amber-300">Items will be placed in QUARANTINE for inspection.</span>
                        </div>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="mt-6 flex justify-between">
                    <button
                        @click="currentStep = 2"
                        class="rounded-lg bg-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-600 transition-colors"
                    >
                        <i class="pi pi-arrow-left mr-2 text-xs"></i>Back
                    </button>
                    <button
                        @click="submitRegistration"
                        :disabled="submitting"
                        class="rounded-lg bg-cyan-500 px-5 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors disabled:opacity-50"
                    >
                        <i v-if="submitting" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                        {{ submitting ? 'Registering...' : 'Confirm & Register' }}
                    </button>
                </div>
            </div>

            <!-- STEP 4: Done -->
            <div v-if="currentStep === 4">
                <div class="flex flex-col items-center justify-center py-8">
                    <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/20">
                        <i class="pi pi-check text-3xl text-emerald-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-100">Registration Complete</h3>
                    <p class="mt-2 text-sm text-slate-400">{{ submitResult.message }}</p>
                    <button
                        @click="closeWizard"
                        class="mt-6 rounded-lg bg-cyan-500 px-6 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </Dialog>
</template>
