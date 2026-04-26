<script setup>
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import axios from 'axios';
import { toast } from 'vue-toastflow';

defineOptions({ layout: AppLayout });

const props = defineProps({
    kits: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1, per_page: 18, total: 0 }) },
});

// ─── Create Kit Dialog ─────────────────────────────────
const createVisible = ref(false);
const createLoading = ref(false);
const form = reactive({
    name: '',
    description: '',
    kit_code: '',
    warehouse_id: '',
    components: [{ inventory_equipment_id: '', quantity: 1 }],
});

function addComponent() {
    form.components.push({ inventory_equipment_id: '', quantity: 1 });
}

function removeComponent(idx) {
    if (form.components.length > 1) {
        form.components.splice(idx, 1);
    }
}

function resetForm() {
    form.name = '';
    form.description = '';
    form.kit_code = '';
    form.warehouse_id = '';
    form.components = [{ inventory_equipment_id: '', quantity: 1 }];
}

async function submitCreate() {
    createLoading.value = true;
    try {
        await axios.post(route('kits.store'), {
            name: form.name,
            description: form.description,
            kit_code: form.kit_code,
            warehouse_id: form.warehouse_id,
            components: form.components.filter(c => c.inventory_equipment_id),
        });
        toast({ type: 'success', message: 'Kit created successfully.' });
        createVisible.value = false;
        resetForm();
        router.reload({ only: ['kits'] });
    } catch (e) {
        const msg = e.response?.data?.message || 'Failed to create kit.';
        toast({ type: 'error', message: msg });
    } finally {
        createLoading.value = false;
    }
}

// ─── Pagination ────────────────────────────────────────
function onPageChange(page) {
    router.get(route('kits.index'), { page }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-slate-100">Kits</h1>
                <p class="mt-1 text-sm text-slate-400">{{ kits?.total ?? 0 }} kits registered</p>
            </div>
            <button
                @click="createVisible = true"
                class="inline-flex items-center gap-2 rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 transition-colors hover:bg-cyan-400"
            >
                <i class="pi pi-plus text-sm"></i>
                Create Kit
            </button>
        </div>

        <!-- Kits Card Grid -->
        <div v-if="kits?.data?.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="kit in kits.data"
                :key="kit.id"
                @click="router.visit(route('kits.show', { id: kit.id }))"
                class="cursor-pointer rounded-xl border border-slate-700 bg-slate-800 p-5 transition-all hover:border-violet-500/50 hover:shadow-lg hover:shadow-violet-500/5"
            >
                <!-- Top -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-slate-100 truncate">{{ kit.name }}</h3>
                        <p v-if="kit.description" class="mt-0.5 text-xs text-slate-500 line-clamp-2">{{ kit.description }}</p>
                    </div>
                    <span v-if="kit.kit_code" class="ml-2 flex-shrink-0 rounded bg-violet-500/20 px-2 py-0.5 text-xs font-medium text-violet-400">
                        {{ kit.kit_code }}
                    </span>
                </div>

                <!-- Info -->
                <div class="flex items-center gap-3 text-xs text-slate-400 mb-3">
                    <span v-if="kit.warehouse">
                        <i class="pi pi-building mr-1"></i>{{ kit.warehouse.name }}
                    </span>
                    <span v-if="kit.group_requirement">
                        <i class="pi pi-sitemap mr-1"></i>{{ kit.group_requirement.name }}
                    </span>
                </div>

                <!-- Stats row -->
                <div class="flex items-center justify-between border-t border-slate-700 pt-3">
                    <div class="flex items-center gap-1 text-sm">
                        <i class="pi pi-th-large text-violet-400 text-xs"></i>
                        <span class="font-bold text-slate-100">{{ kit.component_count ?? 0 }}</span>
                        <span class="text-slate-500">components</span>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-semibold"
                        :class="kit.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                    >
                        {{ kit.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-16">
            <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-800">
                <i class="pi pi-th-large text-4xl text-slate-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-300">No kits created yet</h3>
            <p class="mt-2 text-sm text-slate-500">Create a kit to bundle equipment items together</p>
        </div>

        <!-- Pagination -->
        <div v-if="kits?.last_page > 1" class="mt-6 flex items-center justify-center gap-1">
            <button
                v-for="p in kits.last_page"
                :key="p"
                @click="onPageChange(p)"
                class="rounded px-3 py-1.5 text-sm transition-colors"
                :class="p === kits.current_page
                    ? 'bg-cyan-500 text-slate-950 font-bold'
                    : 'text-slate-400 hover:bg-slate-700'"
            >
                {{ p }}
            </button>
        </div>

        <!-- Create Kit Dialog -->
        <Dialog
            v-model:visible="createVisible"
            header="Create Kit"
            modal
            :style="{ width: '600px' }"
            :pt="{
                root: { class: '!bg-slate-800 !border-slate-700' },
                header: { class: '!bg-slate-800 !text-slate-100 !border-b !border-slate-700' },
                content: { class: '!bg-slate-800' },
            }"
        >
            <form @submit.prevent="submitCreate" class="space-y-4">
                <!-- Name -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Name *</label>
                    <input
                        v-model="form.name"
                        type="text"
                        required
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                        placeholder="e.g. Offshore PPE Kit"
                    />
                </div>

                <!-- Kit Code + Warehouse -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Kit Code</label>
                        <input
                            v-model="form.kit_code"
                            type="text"
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                            placeholder="e.g. KIT-001"
                        />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-300 mb-1">Warehouse ID *</label>
                        <input
                            v-model="form.warehouse_id"
                            type="number"
                            required
                            class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                            placeholder="Warehouse ID"
                        />
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1">Description</label>
                    <textarea
                        v-model="form.description"
                        rows="2"
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                        placeholder="Optional description..."
                    ></textarea>
                </div>

                <!-- Components -->
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-sm font-medium text-slate-300">Components *</label>
                        <button
                            type="button"
                            @click="addComponent"
                            class="text-xs text-cyan-400 hover:text-cyan-300 transition-colors"
                        >
                            <i class="pi pi-plus mr-1"></i>Add Row
                        </button>
                    </div>
                    <div class="space-y-2">
                        <div
                            v-for="(comp, idx) in form.components"
                            :key="idx"
                            class="flex items-center gap-2"
                        >
                            <input
                                v-model="comp.inventory_equipment_id"
                                type="number"
                                placeholder="Equipment ID"
                                class="flex-1 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                            />
                            <input
                                v-model.number="comp.quantity"
                                type="number"
                                min="1"
                                placeholder="Qty"
                                class="w-20 rounded-lg border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500 focus:ring-1 focus:ring-cyan-500"
                            />
                            <button
                                v-if="form.components.length > 1"
                                type="button"
                                @click="removeComponent(idx)"
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:bg-red-500/20 hover:text-red-400 transition-colors"
                            >
                                <i class="pi pi-trash text-sm"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="createVisible = false"
                        class="rounded-lg border border-slate-700 px-4 py-2 text-sm text-slate-300 transition-colors hover:bg-slate-700"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="createLoading"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 transition-colors hover:bg-cyan-400 disabled:opacity-50"
                    >
                        <i v-if="createLoading" class="pi pi-spin pi-spinner mr-1"></i>
                        Create Kit
                    </button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
