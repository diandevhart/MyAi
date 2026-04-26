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
    kit: { type: Object, required: true },
});

// ─── Edit Dialog ───────────────────────────────────────
const editVisible = ref(false);
const editLoading = ref(false);
const editForm = reactive({
    name: '',
    description: '',
    kit_code: '',
    is_active: true,
});

function openEdit() {
    editForm.name = props.kit.name;
    editForm.description = props.kit.description || '';
    editForm.kit_code = props.kit.kit_code || '';
    editForm.is_active = props.kit.is_active;
    editVisible.value = true;
}

async function submitEdit() {
    editLoading.value = true;
    try {
        await axios.put(route('kits.update', { id: props.kit.id }), editForm);
        toast({ type: 'success', message: 'Kit updated.' });
        editVisible.value = false;
        router.reload();
    } catch (e) {
        toast({ type: 'error', message: e.response?.data?.message || 'Failed to update kit.' });
    } finally {
        editLoading.value = false;
    }
}

// ─── Delete ────────────────────────────────────────────
const deleteLoading = ref(false);

async function deleteKit() {
    if (!confirm(`Delete kit "${props.kit.name}"? This action cannot be undone.`)) return;
    deleteLoading.value = true;
    try {
        await axios.delete(route('kits.destroy', { id: props.kit.id }));
        toast({ type: 'success', message: 'Kit deleted.' });
        router.visit(route('kits.index'));
    } catch (e) {
        toast({ type: 'error', message: e.response?.data?.message || 'Failed to delete kit.' });
    } finally {
        deleteLoading.value = false;
    }
}

// ─── Kit components ────────────────────────────────────
const components = computed(() => props.kit.inventory_equipment || []);

// ─── Kit instances (warehouse_inventories that belong to this kit) ──
const instances = computed(() => props.kit.warehouse_inventories || []);

// ─── Condition badge ───────────────────────────────────
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
    return { label: status.name || code, class: map[code] || 'bg-slate-700 text-slate-400' };
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <div class="flex items-center gap-3">
                    <button
                        @click="router.visit(route('kits.index'))"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                    >
                        <i class="pi pi-arrow-left text-sm"></i>
                    </button>
                    <h1 class="text-2xl font-bold text-slate-100">{{ kit.name }}</h1>
                    <span v-if="kit.kit_code" class="rounded bg-violet-500/20 px-2 py-0.5 text-xs font-medium text-violet-400">
                        {{ kit.kit_code }}
                    </span>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-semibold"
                        :class="kit.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                    >
                        {{ kit.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <p v-if="kit.description" class="mt-1 ml-11 text-sm text-slate-400">{{ kit.description }}</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    @click="openEdit"
                    class="inline-flex items-center gap-2 rounded-lg border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-300 transition-colors hover:bg-slate-700"
                >
                    <i class="pi pi-pencil text-sm"></i>
                    Edit
                </button>
                <button
                    @click="deleteKit"
                    :disabled="deleteLoading"
                    class="inline-flex items-center gap-2 rounded-lg border border-red-500/30 bg-red-500/10 px-4 py-2 text-sm text-red-400 transition-colors hover:bg-red-500/20"
                >
                    <i class="pi pi-trash text-sm"></i>
                    Delete
                </button>
            </div>
        </div>

        <!-- Kit Info Card -->
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-5">
            <div class="grid grid-cols-2 gap-x-8 gap-y-3 md:grid-cols-4">
                <div>
                    <div class="text-xs text-slate-500">Warehouse</div>
                    <div class="mt-0.5 text-sm font-medium text-slate-200">{{ kit.warehouse?.name || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Category</div>
                    <div class="mt-0.5 text-sm font-medium text-slate-200">{{ kit.group_requirement?.name || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Components</div>
                    <div class="mt-0.5 text-sm font-bold text-violet-400">{{ kit.component_count ?? components.length }}</div>
                </div>
                <div>
                    <div class="text-xs text-slate-500">Instances</div>
                    <div class="mt-0.5 text-sm font-bold text-cyan-400">{{ instances.length }}</div>
                </div>
            </div>
        </div>

        <!-- Components DataTable -->
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
            <div class="border-b border-slate-700 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-100">Components</h2>
            </div>
            <DataTable
                :value="components"
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
                    <div class="py-6 text-center text-slate-500">No components defined.</div>
                </template>
                <Column field="name" header="Equipment Name" sortable>
                    <template #body="{ data }">
                        <a
                            :href="route('equipment.show', { id: data.id })"
                            class="font-medium text-cyan-400 hover:text-cyan-300 transition-colors"
                        >
                            {{ data.name }}
                        </a>
                    </template>
                </Column>
                <Column field="part_number" header="Part Number" sortable>
                    <template #body="{ data }">
                        <span class="text-sm text-slate-400">{{ data.part_number }}</span>
                    </template>
                </Column>
                <Column header="Type">
                    <template #body="{ data }">
                        <span
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            :class="{
                                'bg-cyan-600 text-white': data.type === 'item',
                                'bg-emerald-600 text-white': data.type === 'ppe',
                                'bg-violet-600 text-white': data.type === 'kit_component',
                            }"
                        >
                            {{ data.type }}
                        </span>
                    </template>
                </Column>
                <Column header="Qty Required">
                    <template #body="{ data }">
                        <span class="font-bold text-slate-100">{{ data.pivot?.quantity ?? 1 }}</span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Kit Instances DataTable -->
        <div class="rounded-xl border border-slate-700 bg-slate-800 overflow-hidden">
            <div class="border-b border-slate-700 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-100">Kit Instances</h2>
            </div>
            <DataTable
                :value="instances"
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
                    <div class="py-6 text-center text-slate-500">No kit instances registered.</div>
                </template>
                <Column field="serial_number" header="Serial Number">
                    <template #body="{ data }">
                        <span class="font-medium text-cyan-400">{{ data.serial_number || '—' }}</span>
                    </template>
                </Column>
                <Column header="Warehouse">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-300">{{ data.warehouse?.name || '—' }}</span>
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
                <Column field="created_at" header="Created">
                    <template #body="{ data }">
                        <span class="text-sm text-slate-400">
                            {{ data.created_at ? new Date(data.created_at).toLocaleDateString() : '—' }}
                        </span>
                    </template>
                </Column>
            </DataTable>
        </div>

        <!-- Edit Dialog -->
        <Dialog
            v-model:visible="editVisible"
            header="Edit Kit"
            modal
            :style="{ width: '500px' }"
            :pt="{
                root: { class: '!bg-slate-800 !border-slate-700' },
                header: { class: '!bg-slate-800 !text-slate-100 !border-b !border-slate-700' },
                content: { class: '!bg-slate-800' },
            }"
        >
            <form @submit.prevent="submitEdit" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name *</label>
                    <input
                        v-model="editForm.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                    />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Kit Code</label>
                        <input
                            v-model="editForm.kit_code"
                            type="text"
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                        />
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input v-model="editForm.is_active" type="checkbox" class="rounded border-slate-700 bg-slate-900 text-cyan-500 focus:ring-cyan-500" />
                            <span class="text-sm text-slate-300">Active</span>
                        </label>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea
                        v-model="editForm.description"
                        rows="2"
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                    ></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="editVisible = false"
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="editLoading"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 disabled:opacity-50"
                    >
                        <i v-if="editLoading" class="pi pi-spin pi-spinner mr-1"></i>
                        Save
                    </button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
