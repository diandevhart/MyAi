<script setup>
import { ref, reactive, computed, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import InputText from 'primevue/inputtext';
import axios from 'axios';
import { toast } from 'vue-toastflow';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rootNodes: { type: Array, default: () => [] },
});

// ─── Tree State ────────────────────────────────────────
const treeNodes = ref(buildTree(props.rootNodes));
const selectedNode = ref(null);
const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const isSearchActive = computed(() => searchQuery.value.trim().length > 0);

function buildTree(nodes) {
    return nodes.map(n => ({
        id: n.id,
        name: n.name,
        type: n.type,
        level: n.level,
        description: n.description,
        children_count: n.children_count ?? 0,
        inventory_equipment_count: n.inventory_equipment_count ?? 0,
        children: n.children ? buildTree(n.children) : [],
        expanded: false,
        loaded: n.children && n.children.length > 0,
        loading: false,
    }));
}

// ─── Tree operations ───────────────────────────────────
async function toggleExpand(node) {
    if (node.children_count === 0 && node.children.length === 0) return;

    if (!node.loaded) {
        node.loading = true;
        try {
            const { data } = await axios.get(route('catalogue.children', { id: node.id }));
            node.children = data.map(c => ({
                id: c.id,
                name: c.name,
                type: c.type,
                level: c.level,
                description: c.description,
                children_count: c.children_count ?? 0,
                inventory_equipment_count: c.item_count ?? 0,
                has_children: c.has_children,
                children: [],
                expanded: false,
                loaded: false,
                loading: false,
            }));
            node.loaded = true;
        } catch {
            toast({ type: 'error', message: 'Failed to load children.' });
        } finally {
            node.loading = false;
        }
    }
    node.expanded = !node.expanded;
}

function selectNode(node) {
    selectedNode.value = node;
    loadNodeDetails(node);
}

// ─── Right Panel Detail ────────────────────────────────
const nodeDetail = reactive({
    loading: false,
    breadcrumb: '',
    childrenList: [],
    equipmentList: [],
});

async function loadNodeDetails(node) {
    nodeDetail.loading = true;
    nodeDetail.breadcrumb = '';
    nodeDetail.childrenList = [];
    nodeDetail.equipmentList = [];

    try {
        // Load children if it's a group
        if (node.type === 'group' || node.children_count > 0) {
            const { data } = await axios.get(route('catalogue.children', { id: node.id }));
            nodeDetail.childrenList = data;
        }

        // Search for this node to get breadcrumb
        const { data: searchData } = await axios.get(route('catalogue.search', { q: node.name }));
        const match = searchData.find(s => s.id === node.id);
        if (match) {
            nodeDetail.breadcrumb = match.breadcrumb;
        }

        // If it's a leaf node (item/ppe/kit/ccu), load equipment registry entries
        if (['item', 'ppe', 'kit', 'ccu'].includes(node.type)) {
            const { data: equipData } = await axios.get(route('equipment.index', { group_requirement_id: node.id }), {
                headers: { 'X-Inertia': false, Accept: 'application/json' },
            });
            nodeDetail.equipmentList = equipData.equipment?.data || equipData.data || [];
        }
    } catch {
        // Non-critical, partial data is fine
    } finally {
        nodeDetail.loading = false;
    }
}

// ─── Search ────────────────────────────────────────────
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
    }, 300);
}

function selectSearchResult(result) {
    selectedNode.value = {
        id: result.id,
        name: result.name,
        type: result.type,
        level: result.level,
        description: result.description,
        children_count: 0,
        inventory_equipment_count: 0,
    };
    loadNodeDetails(selectedNode.value);
}

// ─── Create / Edit Dialog ──────────────────────────────
const dialogVisible = ref(false);
const dialogMode = ref('create'); // 'create' | 'edit'
const dialogForm = reactive({
    name: '',
    type: 'group',
    description: '',
    parent_id: null,
});
const dialogSubmitting = ref(false);

const typeOptions = [
    { label: 'Group', value: 'group' },
    { label: 'Item', value: 'item' },
    { label: 'PPE', value: 'ppe' },
    { label: 'Kit', value: 'kit' },
    { label: 'CCU', value: 'ccu' },
];

function openAddRoot() {
    dialogMode.value = 'create';
    dialogForm.name = '';
    dialogForm.type = 'group';
    dialogForm.description = '';
    dialogForm.parent_id = null;
    dialogVisible.value = true;
}

function openAddChild() {
    if (!selectedNode.value) return;
    dialogMode.value = 'create';
    dialogForm.name = '';
    dialogForm.type = 'group';
    dialogForm.description = '';
    dialogForm.parent_id = selectedNode.value.id;
    dialogVisible.value = true;
}

function openEdit() {
    if (!selectedNode.value) return;
    dialogMode.value = 'edit';
    dialogForm.name = selectedNode.value.name;
    dialogForm.type = selectedNode.value.type;
    dialogForm.description = selectedNode.value.description || '';
    dialogForm.parent_id = null;
    dialogVisible.value = true;
}

async function submitDialog() {
    dialogSubmitting.value = true;
    try {
        if (dialogMode.value === 'create') {
            await axios.post(route('catalogue.store'), {
                name: dialogForm.name,
                type: dialogForm.type,
                description: dialogForm.description,
                parent_id: dialogForm.parent_id,
            });
            toast({ type: 'success', message: 'Node created.' });
        } else {
            await axios.put(route('catalogue.update', { id: selectedNode.value.id }), {
                name: dialogForm.name,
                type: dialogForm.type,
                description: dialogForm.description,
            });
            toast({ type: 'success', message: 'Node updated.' });
        }
        dialogVisible.value = false;
        // Reload page to refresh tree
        window.location.reload();
    } catch (err) {
        const msg = err.response?.data?.message || err.response?.data?.errors?.type?.[0] || 'Operation failed.';
        toast({ type: 'error', message: msg });
    } finally {
        dialogSubmitting.value = false;
    }
}

async function deleteNode() {
    if (!selectedNode.value) return;
    if (!confirm(`Delete "${selectedNode.value.name}"?`)) return;
    try {
        await axios.delete(route('catalogue.destroy', { id: selectedNode.value.id }));
        toast({ type: 'success', message: 'Node deleted.' });
        selectedNode.value = null;
        window.location.reload();
    } catch (err) {
        const msg = err.response?.data?.errors?.delete?.[0] || 'Delete failed.';
        toast({ type: 'error', message: msg });
    }
}

// ─── Type badge styling ────────────────────────────────
const typeBadge = {
    group: 'bg-slate-600 text-slate-200',
    item: 'bg-cyan-600 text-white',
    kit: 'bg-violet-600 text-white',
    ccu: 'bg-amber-600 text-white',
    ppe: 'bg-emerald-600 text-white',
};
</script>

<template>
    <div class="flex gap-5" style="min-height: calc(100vh - 120px)">
        <!-- ────────────── Left Panel: Tree ────────────── -->
        <div class="w-2/5 flex-shrink-0 rounded-xl border border-slate-700 bg-slate-800 flex flex-col" style="max-height: calc(100vh - 120px)">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-slate-700 px-5 py-4">
                <h2 class="text-lg font-semibold text-slate-100">Equipment Catalogue</h2>
                <button
                    @click="openAddRoot"
                    class="rounded-lg bg-cyan-500/20 px-3 py-1.5 text-xs font-medium text-cyan-400 hover:bg-cyan-500/30 transition-colors"
                >
                    <i class="pi pi-plus mr-1 text-xs"></i>Add Group
                </button>
            </div>

            <!-- Search -->
            <div class="border-b border-slate-700 px-4 py-3">
                <div class="relative">
                    <i class="pi pi-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-500 text-sm"></i>
                    <input
                        v-model="searchQuery"
                        @input="onSearch"
                        type="text"
                        placeholder="Search catalogue..."
                        class="w-full rounded-lg border border-slate-700 bg-slate-900 py-2 pl-9 pr-3 text-sm text-slate-100 placeholder-slate-500 outline-none focus:border-cyan-500"
                    />
                </div>
            </div>

            <!-- Tree / Search Results -->
            <div class="flex-1 overflow-y-auto px-3 py-2">
                <!-- Search results -->
                <div v-if="isSearchActive">
                    <div v-if="searchLoading" class="flex items-center justify-center py-6">
                        <i class="pi pi-spin pi-spinner text-cyan-400"></i>
                    </div>
                    <div v-else-if="searchResults.length === 0" class="py-6 text-center text-sm text-slate-500">
                        No results found.
                    </div>
                    <div v-else class="space-y-1">
                        <div
                            v-for="result in searchResults"
                            :key="result.id"
                            @click="selectSearchResult(result)"
                            class="cursor-pointer rounded-lg p-2 transition-colors hover:bg-slate-700"
                            :class="selectedNode?.id === result.id ? 'bg-slate-700 border-l-[3px] border-cyan-400' : ''"
                        >
                            <div class="flex items-center gap-2">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="typeBadge[result.type] || typeBadge.group">
                                    {{ result.type }}
                                </span>
                                <span class="text-sm text-slate-100">{{ result.name }}</span>
                            </div>
                            <div class="mt-0.5 text-xs text-slate-500 pl-8">{{ result.breadcrumb }}</div>
                        </div>
                    </div>
                </div>

                <!-- Tree view -->
                <div v-else>
                    <template v-for="node in treeNodes" :key="node.id">
                        <TreeNode
                            :node="node"
                            :selected-id="selectedNode?.id"
                            :type-badge="typeBadge"
                            @toggle="toggleExpand"
                            @select="selectNode"
                        />
                    </template>
                </div>
            </div>
        </div>

        <!-- ────────────── Right Panel: Detail ─────────── -->
        <div class="flex-1 rounded-xl border border-slate-700 bg-slate-800 p-6" style="max-height: calc(100vh - 120px); overflow-y: auto">
            <!-- No selection -->
            <div v-if="!selectedNode" class="flex h-full flex-col items-center justify-center text-slate-500">
                <i class="pi pi-folder-open text-5xl mb-4 text-slate-600"></i>
                <p class="text-sm">Select a node from the tree to view details</p>
            </div>

            <!-- Loading -->
            <div v-else-if="nodeDetail.loading" class="flex items-center justify-center py-12">
                <i class="pi pi-spin pi-spinner text-xl text-cyan-400"></i>
            </div>

            <!-- Node Detail -->
            <div v-else>
                <!-- Header -->
                <div class="mb-6">
                    <div class="flex items-center gap-3 mb-2">
                        <h2 class="text-xl font-bold text-slate-100">{{ selectedNode.name }}</h2>
                        <span class="rounded px-2 py-0.5 text-xs font-bold uppercase" :class="typeBadge[selectedNode.type] || typeBadge.group">
                            {{ selectedNode.type }}
                        </span>
                    </div>
                    <div v-if="nodeDetail.breadcrumb" class="text-sm text-slate-400">
                        <i class="pi pi-sitemap mr-1 text-xs"></i>{{ nodeDetail.breadcrumb }}
                    </div>
                    <p v-if="selectedNode.description" class="mt-2 text-sm text-slate-300">
                        {{ selectedNode.description }}
                    </p>
                </div>

                <!-- Action buttons -->
                <div class="mb-6 flex items-center gap-2">
                    <button
                        v-if="selectedNode.type === 'group'"
                        @click="openAddChild"
                        class="rounded-lg bg-cyan-500 px-3 py-1.5 text-xs font-semibold text-slate-950 hover:bg-cyan-400 transition-colors"
                    >
                        <i class="pi pi-plus mr-1 text-xs"></i>Add Child
                    </button>
                    <button
                        @click="openEdit"
                        class="rounded-lg border border-slate-600 px-3 py-1.5 text-xs text-slate-300 hover:bg-slate-700 transition-colors"
                    >
                        <i class="pi pi-pencil mr-1 text-xs"></i>Edit
                    </button>
                    <button
                        @click="deleteNode"
                        class="rounded-lg border border-red-500/30 px-3 py-1.5 text-xs text-red-400 hover:bg-red-500/10 transition-colors"
                    >
                        <i class="pi pi-trash mr-1 text-xs"></i>Delete
                    </button>
                </div>

                <!-- Children Section (for group nodes) -->
                <div v-if="nodeDetail.childrenList.length > 0" class="mb-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wide">
                        Children ({{ nodeDetail.childrenList.length }})
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="child in nodeDetail.childrenList"
                            :key="child.id"
                            @click="selectNode({ ...child, children_count: child.children_count || 0, inventory_equipment_count: child.item_count || 0 })"
                            class="flex cursor-pointer items-center justify-between rounded-lg bg-slate-900 p-3 transition-all hover:bg-slate-800 border border-transparent hover:border-slate-600"
                        >
                            <div class="flex items-center gap-3">
                                <i :class="child.has_children ? 'pi pi-folder text-amber-400' : 'pi pi-box text-cyan-400'" class="text-sm"></i>
                                <div>
                                    <span class="text-sm font-medium text-slate-100">{{ child.name }}</span>
                                    <span class="ml-2 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="typeBadge[child.type] || typeBadge.group">
                                        {{ child.type }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="child.item_count" class="text-xs text-slate-400">{{ child.item_count }} equip</span>
                                <span v-if="child.children_count" class="text-xs text-slate-400">{{ child.children_count }} sub</span>
                                <i class="pi pi-chevron-right text-xs text-slate-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Equipment Registry (for leaf nodes) -->
                <div v-if="nodeDetail.equipmentList.length > 0" class="mb-6">
                    <h3 class="mb-3 text-sm font-semibold text-slate-400 uppercase tracking-wide">
                        Equipment Registry ({{ nodeDetail.equipmentList.length }})
                    </h3>
                    <div class="space-y-2">
                        <div
                            v-for="equip in nodeDetail.equipmentList"
                            :key="equip.id"
                            class="flex items-center justify-between rounded-lg bg-slate-900 p-3 border border-slate-700"
                        >
                            <div>
                                <div class="text-sm font-medium text-slate-100">{{ equip.name }}</div>
                                <div class="mt-0.5 flex items-center gap-2 text-xs">
                                    <span class="rounded bg-cyan-500/20 px-1.5 py-0.5 text-cyan-400">{{ equip.part_number }}</span>
                                    <span v-if="equip.manufacturer" class="text-slate-500">{{ equip.manufacturer }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span
                                    class="rounded px-2 py-0.5 text-xs font-semibold"
                                    :class="equip.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                                >
                                    {{ equip.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty leaf with no equipment -->
                <div
                    v-if="['item', 'ppe', 'kit', 'ccu'].includes(selectedNode.type) && nodeDetail.equipmentList.length === 0 && !nodeDetail.loading"
                    class="rounded-xl border border-dashed border-slate-600 bg-slate-900/50 p-6 text-center"
                >
                    <i class="pi pi-inbox text-3xl text-slate-600 mb-2"></i>
                    <p class="text-sm text-slate-500">No equipment registered under this category yet.</p>
                </div>
            </div>
        </div>

        <!-- ────────────── Create/Edit Dialog ──────────── -->
        <Dialog
            v-model:visible="dialogVisible"
            :header="dialogMode === 'create' ? 'Create Node' : 'Edit Node'"
            :modal="true"
            :style="{ width: '500px' }"
            :pt="{
                root: { class: '!bg-slate-800 !border-slate-700' },
                header: { class: '!bg-slate-900 !text-slate-100 !border-b !border-slate-700' },
                content: { class: '!bg-slate-800 !text-slate-100' },
            }"
        >
            <div class="space-y-4">
                <div>
                    <label class="mb-1 block text-sm text-slate-400">Name</label>
                    <InputText
                        v-model="dialogForm.name"
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        placeholder="Node name..."
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">Type</label>
                    <Select
                        v-model="dialogForm.type"
                        :options="typeOptions"
                        optionLabel="label"
                        optionValue="value"
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-400">Description (optional)</label>
                    <Textarea
                        v-model="dialogForm.description"
                        rows="3"
                        class="w-full !bg-slate-900 !border-slate-700 !text-slate-100"
                        placeholder="Description..."
                    />
                </div>
                <div v-if="dialogMode === 'create' && dialogForm.parent_id" class="rounded-lg bg-slate-900 p-3">
                    <span class="text-xs text-slate-500">Parent:</span>
                    <span class="ml-2 text-sm text-cyan-400">{{ selectedNode?.name }}</span>
                </div>
            </div>

            <template #footer>
                <div class="flex justify-end gap-2">
                    <button
                        @click="dialogVisible = false"
                        class="rounded-lg border border-slate-600 px-4 py-2 text-sm text-slate-300 hover:bg-slate-700 transition-colors"
                    >
                        Cancel
                    </button>
                    <button
                        @click="submitDialog"
                        :disabled="!dialogForm.name || dialogSubmitting"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-slate-950 hover:bg-cyan-400 transition-colors disabled:opacity-50"
                    >
                        <i v-if="dialogSubmitting" class="pi pi-spin pi-spinner mr-2 text-xs"></i>
                        {{ dialogMode === 'create' ? 'Create' : 'Save Changes' }}
                    </button>
                </div>
            </template>
        </Dialog>
    </div>
</template>

<script>
// Recursive TreeNode component defined inline
const TreeNode = {
    name: 'TreeNode',
    props: {
        node: { type: Object, required: true },
        selectedId: { type: Number, default: null },
        typeBadge: { type: Object, required: true },
    },
    emits: ['toggle', 'select'],
    template: `
        <div>
            <div
                @click="$emit('select', node)"
                class="flex cursor-pointer items-center rounded-lg p-2 transition-colors hover:bg-slate-700 mb-0.5"
                :class="selectedId === node.id ? 'bg-slate-700 border-l-[3px] border-cyan-400' : ''"
                :style="{ paddingLeft: (node.level * 16 + 8) + 'px' }"
            >
                <!-- Expand toggle -->
                <button
                    v-if="node.children_count > 0 || node.children.length > 0"
                    @click.stop="$emit('toggle', node)"
                    class="mr-2 flex h-5 w-5 items-center justify-center text-slate-500 hover:text-slate-300"
                >
                    <i v-if="node.loading" class="pi pi-spin pi-spinner text-xs text-cyan-400"></i>
                    <i v-else :class="node.expanded ? 'pi pi-chevron-down' : 'pi pi-chevron-right'" class="text-xs"></i>
                </button>
                <span v-else class="mr-2 w-5"></span>

                <!-- Icon -->
                <i
                    :class="(node.type === 'group' && (node.children_count > 0 || node.children.length > 0))
                        ? (node.expanded ? 'pi pi-folder-open text-amber-400' : 'pi pi-folder text-amber-400')
                        : 'pi pi-box text-cyan-400'"
                    class="mr-2 text-sm"
                ></i>

                <!-- Name + badge -->
                <span class="flex-1 text-sm text-slate-100 truncate">{{ node.name }}</span>
                <span
                    class="ml-2 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase flex-shrink-0"
                    :class="typeBadge[node.type] || typeBadge.group"
                >
                    {{ node.type }}
                </span>
                <span v-if="node.inventory_equipment_count" class="ml-1.5 text-[10px] text-slate-500">
                    {{ node.inventory_equipment_count }}
                </span>
            </div>

            <!-- Children (recursive) -->
            <div v-if="node.expanded && node.children.length > 0">
                <TreeNode
                    v-for="child in node.children"
                    :key="child.id"
                    :node="child"
                    :selected-id="selectedId"
                    :type-badge="typeBadge"
                    @toggle="(n) => $emit('toggle', n)"
                    @select="(n) => $emit('select', n)"
                />
            </div>
        </div>
    `,
};

export default {
    components: { TreeNode },
};
</script>
