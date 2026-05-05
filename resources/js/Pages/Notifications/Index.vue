<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { toast } from 'vue-toastflow';
import axios from 'axios';

defineOptions({ layout: AppLayout });

const props = defineProps({
    notifications: Object,
    unreadCount: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({ unread: false, type: '' }) },
    typeOptions: { type: Array, default: () => [] },
});

const list = computed(() => props.notifications?.data || []);
const unreadOnly = ref(!!props.filters?.unread);
const selectedType = ref(props.filters?.type || '');

function applyFilters() {
    const params = {};
    if (unreadOnly.value) params.unread = 1;
    if (selectedType.value) params.type = selectedType.value;

    router.get(route('notifications.index'), params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearFilters() {
    unreadOnly.value = false;
    selectedType.value = '';
    applyFilters();
}

async function openNotification(item) {
    if (!item.read_at) {
        try {
            await axios.post(route('notifications.read', item.id));
        } catch {
            // Do not block navigation if marking read fails.
        }
    }

    const actionUrl = item?.data?.action_url;
    if (actionUrl) {
        router.visit(actionUrl);
        return;
    }
    router.reload({ only: ['notifications', 'unreadCount'] });
}

async function markRead(id) {
    try {
        await axios.post(route('notifications.read', id));
        router.reload({ only: ['notifications', 'unreadCount'] });
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to mark notification as read');
    }
}

async function markAllRead() {
    try {
        await axios.post(route('notifications.read-all'));
        toast.success('All notifications marked as read');
        router.reload({ only: ['notifications', 'unreadCount'] });
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to mark all notifications as read');
    }
}

async function removeNotification(id) {
    try {
        await axios.delete(route('notifications.destroy', id));
        router.reload({ only: ['notifications', 'unreadCount'] });
    } catch (e) {
        toast.error(e.response?.data?.message || 'Failed to delete notification');
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-cyan-400">Notification Center</h1>
                <p class="mt-1 text-sm text-slate-400">Unread: <span class="text-slate-200">{{ unreadCount }}</span></p>
            </div>
            <button
                v-if="unreadCount > 0"
                @click="markAllRead"
                class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-400 transition-colors"
            >
                Mark all as read
            </button>
        </div>

        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-slate-700 bg-slate-800 p-4">
            <label class="inline-flex items-center gap-2 text-sm text-slate-300">
                <input
                    v-model="unreadOnly"
                    type="checkbox"
                    class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-cyan-500 focus:ring-cyan-500"
                    @change="applyFilters"
                />
                Unread only
            </label>

            <select
                v-model="selectedType"
                class="rounded-lg border border-slate-600 bg-slate-900 px-3 py-2 text-sm text-slate-200"
                @change="applyFilters"
            >
                <option value="">All types</option>
                <option v-for="type in typeOptions" :key="type" :value="type">{{ type }}</option>
            </select>

            <button
                @click="clearFilters"
                class="rounded-lg bg-slate-700 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-600 transition-colors"
            >
                Clear filters
            </button>
        </div>

        <div class="rounded-xl border border-slate-700 overflow-hidden">
            <div v-if="list.length" class="divide-y divide-slate-700">
                <div
                    v-for="item in list"
                    :key="item.id"
                    class="flex items-start justify-between gap-4 px-5 py-4"
                    :class="item.read_at ? 'bg-slate-900' : 'bg-cyan-500/10'"
                >
                    <button class="flex-1 text-left" @click="openNotification(item)">
                        <div class="flex items-center gap-2">
                            <span class="text-sm font-semibold text-slate-100">{{ item.data?.title || 'Procurement update' }}</span>
                            <span v-if="!item.read_at" class="rounded-full bg-cyan-500 px-2 py-0.5 text-[10px] font-semibold text-white">New</span>
                        </div>
                        <div class="mt-1 text-sm text-slate-300">{{ item.data?.message || 'Open to view details.' }}</div>
                        <div class="mt-1 text-xs text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</div>
                    </button>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="!item.read_at"
                            @click="markRead(item.id)"
                            class="rounded-lg bg-slate-700 px-3 py-1.5 text-xs text-slate-200 hover:bg-slate-600 transition-colors"
                        >
                            Mark read
                        </button>
                        <button
                            @click="removeNotification(item.id)"
                            class="rounded-lg bg-red-500/20 px-3 py-1.5 text-xs text-red-400 hover:bg-red-500/30 transition-colors"
                        >
                            Delete
                        </button>
                    </div>
                </div>
            </div>
            <div v-else class="px-5 py-10 text-center text-slate-500">
                No notifications yet.
            </div>
        </div>
    </div>
</template>
