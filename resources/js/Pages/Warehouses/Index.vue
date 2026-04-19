<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import InputText from 'primevue/inputtext';
import Textarea from 'primevue/textarea';
import axios from 'axios';
import { toast } from 'vue-toastflow';

defineOptions({ layout: AppLayout });

const props = defineProps({
    warehouses: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search || '');
const activeType = ref(props.filters.warehouse_type || '');
const showAddDialog = ref(false);

const form = ref({
    name: '',
    code: '',
    warehouse_type: 'warehouse',
    address: '',
    city: '',
    province: '',
    country: '',
    capacity: null,
    contact_name: '',
    contact_phone: '',
    contact_email: '',
});

const submitting = ref(false);

const warehouseTypes = [
    { label: 'Warehouse', value: 'warehouse' },
    { label: 'Rig Store', value: 'rig_store' },
    { label: 'Container Yard', value: 'container_yard' },
];

const filterPills = [
    { label: 'All', value: '' },
    { label: 'Warehouse', value: 'warehouse' },
    { label: 'Rig Store', value: 'rig_store' },
    { label: 'Container Yard', value: 'container_yard' },
];

let searchTimeout = null;

function onSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applyFilters();
    }, 400);
}

function setType(type) {
    activeType.value = type;
    applyFilters();
}

function applyFilters() {
    router.get(
        route('warehouses.index'),
        {
            search: search.value || undefined,
            warehouse_type: activeType.value || undefined,
        },
        { preserveState: true, replace: true }
    );
}

function openWarehouse(warehouse) {
    router.visit(route('warehouses.show', { id: warehouse.id }));
}

async function submitCreate() {
    submitting.value = true;
    try {
        await axios.post(route('warehouses.store'), form.value);
        toast({ type: 'success', message: 'Warehouse created successfully.' });
        showAddDialog.value = false;
        resetForm();
        router.reload();
    } catch (err) {
        const msg = err.response?.data?.message || 'Failed to create warehouse.';
        toast({ type: 'error', message: msg });
    } finally {
        submitting.value = false;
    }
}

function resetForm() {
    form.value = {
        name: '',
        code: '',
        warehouse_type: 'warehouse',
        address: '',
        city: '',
        province: '',
        country: '',
        capacity: null,
        contact_name: '',
        contact_phone: '',
        contact_email: '',
    };
}

const warehouseList = computed(() => {
    return props.warehouses?.data || props.warehouses || [];
});
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-100">Warehouses</h1>
                <p class="mt-1 text-sm text-slate-400">Manage your storage locations</p>
            </div>
            <button
                @click="showAddDialog = true"
                class="inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 transition-colors hover:bg-cyan-400"
            >
                <i class="pi pi-plus text-sm"></i>
                Add Warehouse
            </button>
        </div>

        <!-- Search -->
        <div class="relative mb-4">
            <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
            <input
                v-model="search"
                @input="onSearch"
                type="text"
                placeholder="Search warehouses by name, code, or location..."
                class="w-full rounded-lg border border-slate-700 bg-slate-800 py-2.5 pl-10 pr-4 text-sm text-slate-100 placeholder-slate-500 outline-none transition-colors focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
            />
        </div>

        <!-- Filter pills -->
        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="pill in filterPills"
                :key="pill.value"
                @click="setType(pill.value)"
                class="rounded-lg border px-3 py-1.5 text-xs font-medium transition-all"
                :class="[
                    activeType === pill.value
                        ? 'border-cyan-500/50 bg-cyan-500/20 text-cyan-400'
                        : 'border-slate-700 bg-slate-800 text-slate-400 hover:border-slate-600 hover:text-slate-300',
                ]"
            >
                {{ pill.label }}
            </button>
        </div>

        <!-- Card Grid -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="wh in warehouseList"
                :key="wh.id"
                @click="openWarehouse(wh)"
                class="cursor-pointer rounded-xl border border-slate-700 bg-slate-800 p-5 transition-all hover:border-cyan-500/50"
            >
                <!-- Card header -->
                <div class="mb-3 flex items-start justify-between">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-100">{{ wh.name }}</h3>
                        <span class="mt-1 inline-block rounded bg-cyan-500/20 px-2 py-0.5 text-xs text-cyan-400">
                            {{ wh.code }}
                        </span>
                    </div>
                    <span class="rounded bg-slate-700 px-2 py-0.5 text-xs text-slate-300">
                        {{ wh.warehouse_type?.replace('_', ' ') }}
                    </span>
                </div>

                <!-- Location -->
                <p v-if="wh.city || wh.province" class="mb-4 text-sm text-slate-400">
                    <i class="pi pi-map-marker mr-1 text-xs"></i>
                    {{ [wh.city, wh.province].filter(Boolean).join(', ') }}
                </p>

                <!-- Stats row -->
                <div class="grid grid-cols-3 gap-3 border-t border-slate-700 pt-3">
                    <div class="text-center">
                        <div class="text-lg font-bold text-slate-100">{{ wh.item_count ?? 0 }}</div>
                        <div class="text-xs text-slate-500">Total Items</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-bold text-emerald-400">{{ wh.available_count ?? 0 }}</div>
                        <div class="text-xs text-slate-500">Available</div>
                    </div>
                    <div class="text-center relative">
                        <div class="text-lg font-bold text-red-400">{{ wh.low_stock_count ?? 0 }}</div>
                        <div class="text-xs text-slate-500">Alerts</div>
                        <span
                            v-if="(wh.low_stock_count ?? 0) > 0"
                            class="absolute -top-1 right-2 h-2 w-2 rounded-full bg-red-500 animate-pulse"
                        ></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="warehouseList.length === 0" class="mt-12 text-center">
            <i class="pi pi-building text-4xl text-slate-600 mb-3"></i>
            <p class="text-slate-400">No warehouses found.</p>
        </div>

        <!-- Add Warehouse Dialog -->
        <Dialog
            v-model:visible="showAddDialog"
            header="Add Warehouse"
            :modal="true"
            :closable="true"
            class="w-full max-w-lg"
            :pt="{
                root: { class: '!bg-slate-800 !border-slate-700' },
                header: { class: '!bg-slate-800 !text-slate-100 !border-b !border-slate-700' },
                content: { class: '!bg-slate-800 !text-slate-100' },
                footer: { class: '!bg-slate-800 !border-t !border-slate-700' },
            }"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-slate-400">Name *</label>
                        <InputText
                            v-model="form.name"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            required
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Code *</label>
                        <InputText
                            v-model="form.code"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                            required
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Type *</label>
                        <Select
                            v-model="form.warehouse_type"
                            :options="warehouseTypes"
                            optionLabel="label"
                            optionValue="value"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-slate-400">Address</label>
                        <InputText
                            v-model="form.address"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">City</label>
                        <InputText
                            v-model="form.city"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Province</label>
                        <InputText
                            v-model="form.province"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Country</label>
                        <InputText
                            v-model="form.country"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Capacity</label>
                        <InputText
                            v-model.number="form.capacity"
                            type="number"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Contact Name</label>
                        <InputText
                            v-model="form.contact_name"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm text-slate-400">Contact Phone</label>
                        <InputText
                            v-model="form.contact_phone"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                    <div class="col-span-2">
                        <label class="mb-1 block text-sm text-slate-400">Contact Email</label>
                        <InputText
                            v-model="form.contact_email"
                            type="email"
                            class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        />
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button
                        type="button"
                        @click="showAddDialog = false"
                        class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="submitting"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors disabled:opacity-50"
                    >
                        {{ submitting ? 'Creating...' : 'Create Warehouse' }}
                    </button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
