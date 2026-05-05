<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    users: { type: Array, default: () => [] },
    warehouses: { type: Array, default: () => [] },
});

const savingUserId = ref(null);

const userRows = computed(() =>
    props.users.map((user) => ({
        ...user,
        role_names: (user.roles || []).map((r) => r.name),
        selected_warehouse_ids: (user.managed_warehouses || []).map((w) => w.id),
    }))
);

async function saveUserAccess(user) {
    savingUserId.value = user.id;

    try {
        await axios.post(route('admin.procurement-access.update'), {
            user_id: user.id,
            warehouse_ids: user.selected_warehouse_ids,
        });
        toast.success('Access updated for ' + user.name);
        router.reload({ only: ['users'] });
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to update access');
    } finally {
        savingUserId.value = null;
    }
}

function roleBadgeClass(role) {
    if (role === 'Super Admin') return 'bg-emerald-500/20 text-emerald-400';
    if (role === 'Admin') return 'bg-cyan-500/20 text-cyan-400';
    if (role === 'Procurement Manager') return 'bg-amber-500/20 text-amber-400';
    return 'bg-slate-700 text-slate-300';
}
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-cyan-400">Procurement Access Matrix</h1>
            <p class="mt-1 text-sm text-slate-400">Assign warehouse approval scope to users with procurement responsibilities.</p>
        </div>

        <div class="space-y-4">
            <div
                v-for="user in userRows"
                :key="user.id"
                class="rounded-xl border border-slate-700 bg-slate-900 p-5"
            >
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="text-sm font-semibold text-slate-100">{{ user.name }}</div>
                        <div class="text-xs text-slate-500">{{ user.email }}</div>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <span
                            v-for="role in user.role_names"
                            :key="role"
                            class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="roleBadgeClass(role)"
                        >
                            {{ role }}
                        </span>
                        <span v-if="!user.role_names.length" class="rounded-full bg-slate-700 px-2.5 py-0.5 text-xs font-semibold text-slate-300">
                            No role
                        </span>
                    </div>
                </div>

                <div class="grid gap-3 md:grid-cols-[1fr_auto]">
                    <select
                        v-model="user.selected_warehouse_ids"
                        multiple
                        size="5"
                        class="w-full rounded-lg border border-slate-600 bg-slate-800 px-3 py-2 text-sm text-slate-200"
                    >
                        <option v-for="wh in warehouses" :key="wh.id" :value="wh.id">
                            {{ wh.name }} ({{ wh.code }})
                        </option>
                    </select>

                    <button
                        @click="saveUserAccess(user)"
                        :disabled="savingUserId === user.id"
                        class="h-fit rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors disabled:opacity-60"
                    >
                        {{ savingUserId === user.id ? 'Saving...' : 'Save Access' }}
                    </button>
                </div>

                <p class="mt-2 text-xs text-slate-500">Tip: leave empty to keep this user as global manager fallback in development mode.</p>
            </div>

            <div v-if="!userRows.length" class="rounded-xl border border-slate-700 bg-slate-900 px-5 py-10 text-center text-slate-500">
                No users found.
            </div>
        </div>
    </div>
</template>
