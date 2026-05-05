<script setup>
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import { toast } from 'vue-toastflow';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    users: { type: Array, default: () => [] },
    roles: { type: Array, default: () => [] },
    permissions: { type: Array, default: () => [] },
    roleTemplates: { type: Array, default: () => [] },
});

const savingUserId = ref(null);
const searchQuery = ref('');
const roleFilter = ref('');
const permissionFilter = ref('');
const editableRows = ref([]);

function hydrateRows() {
    editableRows.value = props.users.map((user) => ({
        ...user,
        selectedRoles: (user.roles || []).map((role) => role.name),
        selectedPermissions: (user.permissions || []).map((perm) => perm.name),
        selectedTemplateKey: '',
    }));
}

hydrateRows();

watch(
    () => props.users,
    () => hydrateRows()
);

const filteredRows = computed(() => {
    const query = searchQuery.value.trim().toLowerCase();

    return editableRows.value.filter((user) => {
        const matchesQuery = !query
            || user.name.toLowerCase().includes(query)
            || user.email.toLowerCase().includes(query);

        const matchesRole = !roleFilter.value || user.selectedRoles.includes(roleFilter.value);
        const matchesPermission = !permissionFilter.value || user.selectedPermissions.includes(permissionFilter.value);

        return matchesQuery && matchesRole && matchesPermission;
    });
});

function applyTemplate(user) {
    if (!user.selectedTemplateKey) return;

    const template = props.roleTemplates.find((item) => item.key === user.selectedTemplateKey);
    if (!template) return;

    user.selectedRoles = [...template.roles];
    user.selectedPermissions = [...template.permissions];
}

async function saveUser(user) {
    savingUserId.value = user.id;

    try {
        await axios.post(route('admin.access-control.update'), {
            user_id: user.id,
            roles: user.selectedRoles,
            permissions: user.selectedPermissions,
        });
        toast.success('Access updated for ' + user.name);
        router.reload({ only: ['users'] });
    } catch (error) {
        toast.error(error.response?.data?.message || 'Failed to update access');
    } finally {
        savingUserId.value = null;
    }
}
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-cyan-400">Roles and Permissions</h1>
            <p class="mt-1 text-sm text-slate-400">Assign roles and direct permissions for yourself or other users.</p>
        </div>

        <div class="mb-5 grid gap-3 rounded-xl border border-slate-700 bg-slate-900 p-4 lg:grid-cols-3">
            <input
                v-model="searchQuery"
                type="text"
                placeholder="Search by name or email..."
                class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
            />

            <select
                v-model="roleFilter"
                class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
            >
                <option value="">All roles</option>
                <option v-for="role in roles" :key="role.id" :value="role.name">{{ role.name }}</option>
            </select>

            <select
                v-model="permissionFilter"
                class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
            >
                <option value="">All direct permissions</option>
                <option v-for="permission in permissions" :key="permission.id" :value="permission.name">{{ permission.name }}</option>
            </select>
        </div>

        <div class="space-y-4">
            <div
                v-for="user in filteredRows"
                :key="user.id"
                class="rounded-xl border border-slate-700 bg-slate-900 p-5"
            >
                <div class="mb-4">
                    <div class="text-sm font-semibold text-slate-100">{{ user.name }}</div>
                    <div class="text-xs text-slate-500">{{ user.email }}</div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-400">Roles</label>
                        <select
                            v-model="user.selectedRoles"
                            multiple
                            size="5"
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
                        >
                            <option v-for="role in roles" :key="role.id" :value="role.name">
                                {{ role.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wider text-slate-400">Direct Permissions</label>
                        <select
                            v-model="user.selectedPermissions"
                            multiple
                            size="5"
                            class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
                        >
                            <option v-for="permission in permissions" :key="permission.id" :value="permission.name">
                                {{ permission.name }}
                            </option>
                        </select>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 lg:grid-cols-[1fr_auto]" v-if="roleTemplates.length">
                    <select
                        v-model="user.selectedTemplateKey"
                        class="rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
                    >
                        <option value="">Apply role template...</option>
                        <option v-for="template in roleTemplates" :key="template.key" :value="template.key">
                            {{ template.label }}
                        </option>
                    </select>

                    <button
                        @click="applyTemplate(user)"
                        :disabled="!user.selectedTemplateKey"
                        class="rounded-lg bg-violet-500 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-400 transition-colors disabled:opacity-60"
                    >
                        Apply Template
                    </button>
                </div>

                <div class="mt-4 flex justify-end">
                    <button
                        @click="saveUser(user)"
                        :disabled="savingUserId === user.id"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors disabled:opacity-60"
                    >
                        {{ savingUserId === user.id ? 'Saving...' : 'Save Roles & Permissions' }}
                    </button>
                </div>
            </div>

            <div v-if="!filteredRows.length" class="rounded-xl border border-slate-700 bg-slate-900 px-5 py-10 text-center text-slate-500">
                No users match the current filters.
            </div>
        </div>
    </div>
</template>
