<script setup>
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Paginator from 'primevue/paginator';
import axios from 'axios';
import { toast } from 'vue-toastflow';

defineOptions({ layout: AppLayout });

const props = defineProps({
    equipment: { type: Object, default: () => ({ data: [], links: [], current_page: 1, last_page: 1, per_page: 20, total: 0 }) },
    stockLevels: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

// ─── Search State ──────────────────────────────────────
const searchQuery = ref(props.filters?.search || '');
const activeTypes = reactive(new Set());
const searchResults = ref([]);
const searchLoading = ref(false);
const isSearchMode = ref(false);

// Type counts from search results
const typeCounts = computed(() => {
    const counts = { equipment: 0, kit: 0, ccu: 0 };
    searchResults.value.forEach(r => {
        if (r.result_type === 'kit') counts.kit++;
        else if (r.result_type === 'ccu') counts.ccu++;
        else counts.equipment++;
    });
    return counts;
});

// Filtered results based on active type toggles
const filteredResults = computed(() => {
    if (activeTypes.size === 0) return searchResults.value;
    return searchResults.value.filter(r => {
        if (activeTypes.has('equipment') && r.result_type !== 'kit' && r.result_type !== 'ccu') return true;
        if (activeTypes.has('kit') && r.result_type === 'kit') return true;
        if (activeTypes.has('ccu') && r.result_type === 'ccu') return true;
        return false;
    });
});

function toggleType(type) {
    if (activeTypes.has(type)) activeTypes.delete(type);
    else activeTypes.add(type);
}

// ─── Search ────────────────────────────────────────────
let searchTimeout = null;
function onSearchInput() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        doSearch();
    }, 300);
}

async function doSearch() {
    const q = searchQuery.value.trim();
    if (!q) {
        isSearchMode.value = false;
        searchResults.value = [];
        return;
    }

    isSearchMode.value = true;
    searchLoading.value = true;
    try {
        const types = activeTypes.size > 0 ? Array.from(activeTypes) : ['equipment', 'kit', 'ccu'];
        const { data } = await axios.post(route('warehouses.global-search'), {
            query: q,
            types: types,
        });
        searchResults.value = data.results || data;
    } catch {
        toast({ type: 'error', message: 'Search failed.' });
    } finally {
        searchLoading.value = false;
    }
}

// ─── Pagination (browse mode) ──────────────────────────
function onPageChange(event) {
    const page = event.page + 1;
    router.get(route('equipment.index'), { page, search: props.filters?.search }, { preserveState: true, preserveScroll: true });
}

// ─── Stock level helper ────────────────────────────────
function getStock(equipId) {
    return props.stockLevels?.[equipId] || { available: 0, quarantine: 0, in_use: 0, total: 0 };
}

function stockBarWidth(val, total) {
    if (!total || total === 0) return '0%';
    return Math.round((val / total) * 100) + '%';
}

// ─── Card accent by type ───────────────────────────────
function resultAccent(item) {
    if (item.result_type === 'kit') return { border: 'border-violet-500/30', badge: 'bg-violet-500/20 text-violet-400' };
    if (item.result_type === 'ccu') return { border: 'border-amber-500/30', badge: 'bg-amber-500/20 text-amber-400' };
    return { border: 'border-slate-700', badge: 'bg-cyan-500/20 text-cyan-400' };
}
</script>

<template>
    <div>
        <!-- Header -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-slate-100">Equipment Finder</h1>
            <p class="mt-1 text-sm text-slate-400">Search across all equipment, kits, and containers</p>
        </div>

        <!-- Search Bar -->
        <div class="mb-6 rounded-xl border border-slate-700 bg-slate-800 p-1.5 flex items-center gap-2">
            <div class="relative flex-1">
                <i class="pi pi-search absolute left-4 top-1/2 -translate-y-1/2 text-slate-500"></i>
                <input
                    v-model="searchQuery"
                    @input="onSearchInput"
                    type="text"
                    placeholder="Search equipment by name, part number, manufacturer..."
                    class="w-full rounded-lg border-0 bg-transparent py-3.5 pl-11 pr-4 text-base text-slate-100 placeholder-slate-500 outline-none"
                />
            </div>
            <!-- Type filter pills -->
            <div class="flex items-center gap-1.5 pr-2">
                <button
                    @click="toggleType('equipment')"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeTypes.has('equipment')
                        ? 'bg-cyan-500 text-slate-950'
                        : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                >
                    Equipment
                </button>
                <button
                    @click="toggleType('kit')"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeTypes.has('kit')
                        ? 'bg-violet-500 text-white'
                        : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                >
                    Kits
                </button>
                <button
                    @click="toggleType('ccu')"
                    class="rounded-full px-3 py-1.5 text-xs font-medium transition-colors"
                    :class="activeTypes.has('ccu')
                        ? 'bg-amber-500 text-slate-950'
                        : 'bg-slate-700 text-slate-400 hover:bg-slate-600'"
                >
                    CCU
                </button>
            </div>
        </div>

        <!-- Search Mode: Results -->
        <div v-if="isSearchMode">
            <!-- Loading -->
            <div v-if="searchLoading" class="flex items-center justify-center py-12">
                <i class="pi pi-spin pi-spinner text-2xl text-cyan-400"></i>
            </div>

            <div v-else>
                <!-- Result counts -->
                <div v-if="searchResults.length > 0" class="mb-4 flex items-center gap-3 text-sm text-slate-400">
                    <span class="text-cyan-400 font-medium">{{ typeCounts.equipment }} Equipment</span>
                    <span class="text-slate-600">&middot;</span>
                    <span class="text-violet-400 font-medium">{{ typeCounts.kit }} Kits</span>
                    <span class="text-slate-600">&middot;</span>
                    <span class="text-amber-400 font-medium">{{ typeCounts.ccu }} CCU</span>
                </div>

                <!-- Results grid -->
                <div v-if="filteredResults.length > 0" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <div
                        v-for="item in filteredResults"
                        :key="`${item.result_type}-${item.id}`"
                        class="rounded-xl border bg-slate-800 p-4 transition-all hover:border-slate-600"
                        :class="resultAccent(item).border"
                    >
                        <!-- Top row -->
                        <div class="flex items-start justify-between mb-2">
                            <div class="flex-1 min-w-0">
                                <h3 class="text-sm font-semibold text-slate-100 truncate">{{ item.name }}</h3>
                            </div>
                            <span v-if="item.part_number || item.kit_code || item.container_number"
                                class="ml-2 flex-shrink-0 rounded px-2 py-0.5 text-xs"
                                :class="resultAccent(item).badge"
                            >
                                {{ item.part_number || item.kit_code || item.container_number }}
                            </span>
                        </div>

                        <!-- Second row -->
                        <div class="mb-3 flex items-center gap-2 text-xs">
                            <span v-if="item.breadcrumb || item.category" class="text-slate-500 truncate">
                                {{ item.breadcrumb || item.category }}
                            </span>
                            <span v-if="item.manufacturer" class="text-slate-400">{{ item.manufacturer }}</span>
                        </div>

                        <!-- Stock bar (equipment only) -->
                        <div v-if="item.result_type !== 'kit' && item.result_type !== 'ccu' && item.total" class="mb-3">
                            <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-700">
                                <div
                                    class="bg-emerald-500 transition-all"
                                    :style="{ width: stockBarWidth(item.available || 0, item.total) }"
                                ></div>
                                <div
                                    class="bg-amber-500 transition-all"
                                    :style="{ width: stockBarWidth(item.quarantine || 0, item.total) }"
                                ></div>
                                <div
                                    class="bg-violet-500 transition-all"
                                    :style="{ width: stockBarWidth(item.in_use || 0, item.total) }"
                                ></div>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-[10px] text-slate-500">
                                <span><span class="text-emerald-400">{{ item.available || 0 }}</span> avail</span>
                                <span><span class="text-amber-400">{{ item.quarantine || 0 }}</span> quar</span>
                                <span><span class="text-violet-400">{{ item.in_use || 0 }}</span> in use</span>
                            </div>
                        </div>

                        <!-- Kit info -->
                        <div v-if="item.result_type === 'kit'" class="mb-3 text-xs text-slate-400">
                            <i class="pi pi-th-large mr-1"></i>{{ item.component_count || 0 }} components
                        </div>

                        <!-- CCU info -->
                        <div v-if="item.result_type === 'ccu'" class="mb-3 text-xs text-slate-400">
                            <i class="pi pi-box mr-1"></i>{{ item.container_type || 'Container' }} &middot; {{ item.items_inside || 0 }} items
                        </div>

                        <!-- Bottom row -->
                        <div class="flex items-center justify-between border-t border-slate-700 pt-2">
                            <span v-if="item.total" class="text-xs text-slate-400">{{ item.total }} total</span>
                            <span v-else class="text-xs text-slate-500">—</span>
                            <a
                                v-if="item.result_type !== 'kit' && item.result_type !== 'ccu'"
                                :href="route('equipment.show', { id: item.id })"
                                class="text-xs font-medium text-cyan-400 hover:text-cyan-300 transition-colors"
                            >
                                View Details <i class="pi pi-arrow-right text-[10px] ml-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- No results -->
                <div v-if="filteredResults.length === 0 && searchResults.length > 0" class="py-12 text-center">
                    <i class="pi pi-filter-slash text-3xl text-slate-600 mb-3"></i>
                    <p class="text-sm text-slate-500">No results match the selected filters.</p>
                </div>

                <div v-if="searchResults.length === 0" class="py-12 text-center">
                    <i class="pi pi-search text-3xl text-slate-600 mb-3"></i>
                    <p class="text-sm text-slate-500">No results found for "{{ searchQuery }}"</p>
                </div>
            </div>
        </div>

        <!-- Browse Mode: Paginated Equipment List -->
        <div v-else>
            <!-- Empty state when no search -->
            <div v-if="!equipment?.data?.length && !props.filters?.search" class="flex flex-col items-center justify-center py-16">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-slate-800">
                    <i class="pi pi-search text-4xl text-slate-600"></i>
                </div>
                <h3 class="text-lg font-semibold text-slate-300">Start typing to search across all inventory</h3>
                <p class="mt-2 text-sm text-slate-500">Or browse the equipment registry below</p>
            </div>

            <!-- Equipment cards grid -->
            <div v-if="equipment?.data?.length" class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="equip in equipment.data"
                    :key="equip.id"
                    class="rounded-xl border border-slate-700 bg-slate-800 p-4 transition-all hover:border-slate-600"
                >
                    <!-- Top -->
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-slate-100 truncate">{{ equip.name }}</h3>
                        </div>
                        <span class="ml-2 flex-shrink-0 rounded bg-cyan-500/20 px-2 py-0.5 text-xs text-cyan-400">
                            {{ equip.part_number }}
                        </span>
                    </div>

                    <!-- Meta -->
                    <div class="mb-3 flex items-center gap-2 text-xs">
                        <span v-if="equip.group_requirement" class="text-slate-500">{{ equip.group_requirement.name }}</span>
                        <span v-if="equip.manufacturer" class="text-slate-400">{{ equip.manufacturer }}</span>
                        <span
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            :class="{
                                'bg-cyan-600 text-white': equip.type === 'item',
                                'bg-emerald-600 text-white': equip.type === 'ppe',
                                'bg-violet-600 text-white': equip.type === 'kit_component',
                            }"
                        >
                            {{ equip.type }}
                        </span>
                    </div>

                    <!-- Stock bar -->
                    <div class="mb-3">
                        <div class="flex h-2 w-full overflow-hidden rounded-full bg-slate-700">
                            <div class="bg-emerald-500 transition-all" :style="{ width: stockBarWidth(getStock(equip.id).available, getStock(equip.id).total) }"></div>
                            <div class="bg-amber-500 transition-all" :style="{ width: stockBarWidth(getStock(equip.id).quarantine, getStock(equip.id).total) }"></div>
                            <div class="bg-violet-500 transition-all" :style="{ width: stockBarWidth(getStock(equip.id).in_use, getStock(equip.id).total) }"></div>
                        </div>
                        <div class="mt-1 flex items-center justify-between text-[10px] text-slate-500">
                            <span><span class="text-emerald-400">{{ getStock(equip.id).available }}</span> avail</span>
                            <span><span class="text-amber-400">{{ getStock(equip.id).quarantine }}</span> quar</span>
                            <span><span class="text-violet-400">{{ getStock(equip.id).in_use }}</span> in use</span>
                        </div>
                    </div>

                    <!-- Bottom -->
                    <div class="flex items-center justify-between border-t border-slate-700 pt-2">
                        <span class="text-xs text-slate-400">{{ getStock(equip.id).total }} total</span>
                        <a
                            :href="route('equipment.show', { id: equip.id })"
                            class="text-xs font-medium text-cyan-400 hover:text-cyan-300 transition-colors"
                        >
                            View Details <i class="pi pi-arrow-right text-[10px] ml-1"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <div v-if="equipment?.last_page > 1" class="mt-6">
                <Paginator
                    :rows="equipment.per_page"
                    :totalRecords="equipment.total"
                    :first="(equipment.current_page - 1) * equipment.per_page"
                    @page="onPageChange"
                    :pt="{
                        root: { class: '!bg-slate-800 !border-slate-700' },
                        page: { class: '!text-slate-400' },
                        current: { class: '!bg-cyan-500 !text-slate-950' },
                    }"
                />
            </div>
        </div>
    </div>
</template>
