<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    ccus: { type: Object, default: () => ({ data: [], current_page: 1, last_page: 1, per_page: 18, total: 0 }) },
});

// ─── Pagination ────────────────────────────────────────
function onPageChange(page) {
    router.get(route('ccu.index'), { page }, { preserveState: true, preserveScroll: true });
}

// ─── Container type badge ──────────────────────────────
function typeBadge(type) {
    const map = {
        basket: 'bg-amber-500/20 text-amber-400',
        container: 'bg-cyan-500/20 text-cyan-400',
        skip: 'bg-violet-500/20 text-violet-400',
    };
    return map[(type || '').toLowerCase()] || 'bg-slate-700 text-slate-400';
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-100">Containers (CCU)</h1>
            <p class="mt-1 text-sm text-slate-400">{{ ccus?.total ?? 0 }} containers registered</p>
        </div>

        <!-- CCU Card Grid -->
        <div v-if="ccus?.data?.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="ccu in ccus.data"
                :key="ccu.id"
                @click="router.visit(route('ccu.show', { id: ccu.id }))"
                class="cursor-pointer rounded-xl border border-slate-700 bg-slate-800 p-5 transition-all hover:border-amber-500/50 hover:shadow-lg hover:shadow-amber-500/5"
            >
                <!-- Top -->
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-base font-semibold text-slate-100 truncate">{{ ccu.name }}</h3>
                    </div>
                    <span v-if="ccu.container_number" class="ml-2 flex-shrink-0 rounded bg-amber-500/20 px-2 py-0.5 text-xs font-medium text-amber-400">
                        {{ ccu.container_number }}
                    </span>
                </div>

                <!-- Info -->
                <div class="flex items-center gap-3 text-xs text-slate-400 mb-3">
                    <span v-if="ccu.container_type" class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="typeBadge(ccu.container_type)">
                        {{ ccu.container_type }}
                    </span>
                    <span v-if="ccu.warehouse">
                        <i class="pi pi-building mr-1"></i>{{ ccu.warehouse.name }}
                    </span>
                    <span v-if="ccu.rig">
                        <i class="pi pi-compass mr-1"></i>{{ ccu.rig.name }}
                    </span>
                </div>

                <!-- Stats row -->
                <div class="flex items-center justify-between border-t border-slate-700 pt-3">
                    <div class="flex items-center gap-1 text-sm">
                        <i class="pi pi-box text-amber-400 text-xs"></i>
                        <span class="font-bold text-slate-100">{{ ccu.items_count ?? 0 }}</span>
                        <span class="text-slate-500">items inside</span>
                    </div>
                    <span
                        class="rounded px-2 py-0.5 text-xs font-semibold"
                        :class="ccu.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                    >
                        {{ ccu.is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else class="flex flex-col items-center justify-center py-16">
            <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-800">
                <i class="pi pi-server text-4xl text-slate-600"></i>
            </div>
            <h3 class="text-lg font-semibold text-slate-300">No containers registered</h3>
            <p class="mt-2 text-sm text-slate-500">Containers are created from the warehouse page</p>
        </div>

        <!-- Pagination -->
        <div v-if="ccus?.last_page > 1" class="mt-6 flex items-center justify-center gap-1">
            <button
                v-for="p in ccus.last_page"
                :key="p"
                @click="onPageChange(p)"
                class="rounded px-3 py-1.5 text-sm transition-colors"
                :class="p === ccus.current_page
                    ? 'bg-cyan-500 text-slate-950 font-bold'
                    : 'text-slate-400 hover:bg-slate-700'"
            >
                {{ p }}
            </button>
        </div>
    </div>
</template>
