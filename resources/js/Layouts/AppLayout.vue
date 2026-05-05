<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Menu from 'primevue/menu';
import axios from 'axios';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const opsAlerts = computed(() => page.props.opsAlerts || { pending_internal_requests: 0, overdue_rfqs: 0, total: 0 });
const notifications = computed(() => page.props.notifications || { unread_count: 0, preview: [] });
const flashAccessDenied = computed(() => page.props.flash?.accessDenied || null);

const collapsed = ref(false);
const userMenuRef = ref(null);
const showAlerts = ref(false);
const showAccessDeniedModal = ref(false);

onMounted(() => {
    const saved = localStorage.getItem('sidebar_collapsed');
    if (saved !== null) collapsed.value = saved === 'true';

    if (flashAccessDenied.value) {
        showAccessDeniedModal.value = true;
    }
});

watch(
    () => flashAccessDenied.value,
    (value) => {
        if (value) showAccessDeniedModal.value = true;
    }
);

function toggleSidebar() {
    collapsed.value = !collapsed.value;
    localStorage.setItem('sidebar_collapsed', collapsed.value);
}

const navItems = [
    { label: 'Dashboard', icon: 'pi pi-home', route: 'dashboard' },
    { label: 'Warehouses', icon: 'pi pi-building', route: 'warehouses.index' },
    { label: 'Equipment Catalogue', icon: 'pi pi-sitemap', route: 'catalogue.index' },
    { label: 'Equipment Finder', icon: 'pi pi-search', route: 'equipment.index' },
    { label: 'Kits', icon: 'pi pi-th-large', route: 'kits.index' },
    { label: 'Containers', icon: 'pi pi-server', route: 'ccu.index' },
    { label: 'Suppliers', icon: 'pi pi-truck', route: 'suppliers.index' },
    { label: 'RFQ Pipeline', icon: 'pi pi-file-edit', route: 'rfq.pipeline' },
    { label: 'Internal Requests', icon: 'pi pi-inbox', route: 'rfq.internal.index' },
];

const adminItems = [
    { label: 'Tracking Validation', icon: 'pi pi-verified', route: 'admin.tracking-validation' },
    { label: 'Roles & Permissions', icon: 'pi pi-lock', route: 'admin.access-control' },
    { label: 'Procurement Access', icon: 'pi pi-shield', route: 'admin.procurement-access' },
];

function isActive(routeName) {
    if (!hasRoute(routeName)) return false;
    return route().current(routeName);
}

function navigate(routeName) {
    if (!hasRoute(routeName)) {
        console.warn(`Route not found: ${routeName}`);
        return;
    }
    router.visit(route(routeName));
}

function hasRoute(routeName) {
    try {
        return route().has(routeName);
    } catch {
        return false;
    }
}

const userMenuItems = ref([
    {
        label: 'Profile',
        icon: 'pi pi-user',
        command: () => router.visit(route('profile.edit')),
    },
    { separator: true },
    {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        command: () => router.post(route('logout')),
    },
]);

function toggleUserMenu(event) {
    userMenuRef.value.toggle(event);
}

function goToPendingInternal() {
    showAlerts.value = false;
    router.visit(route('rfq.internal.index', { status: 'pending' }));
}

function goToOverdueRfqs() {
    showAlerts.value = false;
    router.visit(route('rfq.pipeline', { status: 'overdue' }));
}

function goToNotifications() {
    showAlerts.value = false;
    router.visit(route('notifications.index'));
}

async function openNotification(item) {
    showAlerts.value = false;

    if (item && !item.read_at) {
        try {
            await axios.post(route('notifications.read', item.id));
        } catch {
            // Best effort; do not block navigation.
        }
    }

    const actionUrl = item?.data?.action_url;
    if (actionUrl) {
        router.visit(actionUrl);
        return;
    }
    router.visit(route('notifications.index'));
}

const sidebarWidth = computed(() => (collapsed.value ? '72px' : '260px'));
</script>

<template>
    <div class="min-h-screen bg-slate-900">
        <!-- Sidebar -->
        <aside
            class="fixed top-0 left-0 z-40 flex h-screen flex-col border-r border-slate-800 bg-slate-950 transition-all duration-300"
            :style="{ width: sidebarWidth }"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center border-b border-slate-800 px-4">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden">
                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg bg-cyan-500/20">
                        <i class="pi pi-warehouse text-cyan-400 text-lg"></i>
                    </div>
                    <span
                        v-if="!collapsed"
                        class="text-xl font-bold text-cyan-400 whitespace-nowrap transition-opacity duration-200"
                    >
                        MyAi
                    </span>
                </Link>
            </div>

            <!-- Nav Items -->
            <nav class="mt-4 flex-1 overflow-y-auto px-3 pb-24">
                <button
                    v-for="item in navItems"
                    :key="item.route"
                    type="button"
                    @click="navigate(item.route)"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150"
                    :class="[
                        isActive(item.route)
                            ? 'border-l-[3px] border-cyan-400 bg-slate-800 text-cyan-400'
                            : 'border-l-[3px] border-transparent text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                    ]"
                    :title="collapsed ? item.label : ''"
                >
                    <i :class="item.icon" class="text-base flex-shrink-0" style="width: 20px; text-align: center;"></i>
                    <span v-if="!collapsed" class="whitespace-nowrap">{{ item.label }}</span>
                </button>

                <!-- Admin Divider -->
                <div class="my-3 border-t border-slate-800"></div>
                <span v-if="!collapsed" class="px-3 text-xs text-slate-600 uppercase tracking-wider">Admin</span>

                <button
                    v-for="item in adminItems"
                    :key="item.route"
                    type="button"
                    @click="navigate(item.route)"
                    class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-all duration-150"
                    :class="[
                        isActive(item.route)
                            ? 'border-l-[3px] border-cyan-400 bg-slate-800 text-cyan-400'
                            : 'border-l-[3px] border-transparent text-slate-400 hover:bg-slate-800 hover:text-slate-100',
                    ]"
                    :title="collapsed ? item.label : ''"
                >
                    <i :class="item.icon" class="text-base flex-shrink-0" style="width: 20px; text-align: center;"></i>
                    <span v-if="!collapsed" class="whitespace-nowrap">{{ item.label }}</span>
                </button>
            </nav>

            <!-- Collapse toggle -->
            <div class="mb-4 mt-auto w-full px-3">
                <button
                    type="button"
                    @click="toggleSidebar"
                    class="flex w-full items-center justify-center rounded-lg py-2.5 text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                >
                    <i :class="collapsed ? 'pi pi-angle-right' : 'pi pi-angle-left'" class="text-base"></i>
                    <span v-if="!collapsed" class="ml-3 text-sm">Collapse</span>
                </button>
            </div>
        </aside>

        <!-- Top Bar -->
        <header
            class="fixed top-0 right-0 z-30 flex h-16 items-center justify-between border-b border-slate-800 bg-slate-900 px-6 transition-all duration-300"
            :style="{ left: sidebarWidth }"
        >
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-sm">
                <span class="text-slate-400">
                    <i class="pi pi-home text-xs"></i>
                </span>
                <span class="text-slate-600">/</span>
                <span class="text-slate-100 font-medium">{{ $page.props.pageTitle || 'Dashboard' }}</span>
            </div>

            <!-- Right side -->
            <div class="flex items-center gap-4">
                <!-- Notification bell -->
                <div class="relative">
                    <button
                        type="button"
                        @click="showAlerts = !showAlerts"
                        class="relative flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors"
                    >
                        <i class="pi pi-bell text-base"></i>
                        <span
                            v-if="notifications.unread_count > 0"
                            class="absolute -right-1 -top-1 inline-flex min-h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white"
                        >
                            {{ notifications.unread_count }}
                        </span>
                    </button>

                    <div
                        v-if="showAlerts"
                        class="absolute right-0 z-50 mt-2 w-80 rounded-xl border border-slate-700 bg-slate-800 p-3 shadow-2xl"
                    >
                        <div class="mb-2 flex items-center justify-between">
                            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Notifications</div>
                            <button @click="goToNotifications" class="text-xs text-cyan-400 hover:text-cyan-300">View all</button>
                        </div>

                        <div v-if="notifications.preview?.length" class="space-y-2">
                            <button
                                v-for="item in notifications.preview"
                                :key="item.id"
                                type="button"
                                @click="openNotification(item)"
                                class="w-full rounded-lg border px-3 py-2 text-left transition-colors"
                                :class="item.read_at ? 'border-slate-700 bg-slate-900 hover:bg-slate-700' : 'border-cyan-500/30 bg-cyan-500/10 hover:bg-cyan-500/20'"
                            >
                                <div class="text-sm font-semibold text-slate-100">{{ item.data?.title || 'Procurement update' }}</div>
                                <div class="mt-0.5 text-xs text-slate-300 line-clamp-2">{{ item.data?.message || 'Open notification center for details.' }}</div>
                                <div class="mt-1 text-[11px] text-slate-500">{{ new Date(item.created_at).toLocaleString() }}</div>
                            </button>
                        </div>

                        <div v-else class="rounded-lg border border-slate-700 bg-slate-900 px-3 py-4 text-center text-sm text-slate-500">
                            No notifications yet.
                        </div>

                        <div class="mt-3 border-t border-slate-700 pt-3">
                            <button
                                v-if="opsAlerts.pending_internal_requests > 0"
                                type="button"
                                @click="goToPendingInternal"
                                class="mb-2 flex w-full items-center justify-between rounded-lg bg-slate-900 px-3 py-2 text-left hover:bg-slate-700"
                            >
                                <span class="text-sm text-slate-200">Pending Internal Requests</span>
                                <span class="rounded-full bg-amber-500/20 px-2 py-0.5 text-xs font-semibold text-amber-400">{{ opsAlerts.pending_internal_requests }}</span>
                            </button>
                            <button
                                v-if="opsAlerts.overdue_rfqs > 0"
                                type="button"
                                @click="goToOverdueRfqs"
                                class="flex w-full items-center justify-between rounded-lg bg-slate-900 px-3 py-2 text-left hover:bg-slate-700"
                            >
                                <span class="text-sm text-slate-200">Overdue RFQs</span>
                                <span class="rounded-full bg-red-500/20 px-2 py-0.5 text-xs font-semibold text-red-400">{{ opsAlerts.overdue_rfqs }}</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- User dropdown -->
                <button
                    @click="toggleUserMenu"
                    class="flex items-center gap-3 rounded-lg px-3 py-1.5 hover:bg-slate-800 transition-colors"
                >
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-cyan-500/20 text-cyan-400 text-sm font-bold">
                        {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                    </div>
                    <span class="hidden text-sm font-medium text-slate-300 sm:block">{{ user?.name }}</span>
                    <i class="pi pi-chevron-down text-xs text-slate-500"></i>
                </button>
                <Menu ref="userMenuRef" :model="userMenuItems" :popup="true" class="!bg-slate-800 !border-slate-700" />
            </div>
        </header>

        <!-- Main Content -->
        <main
            class="min-h-screen pt-16 transition-all duration-300 bg-slate-900"
            :style="{ marginLeft: sidebarWidth }"
        >
            <div class="p-6">
                <slot />
            </div>
        </main>

        <div
            v-if="showAccessDeniedModal && flashAccessDenied"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-slate-950/70 px-4 backdrop-blur-sm"
            @click.self="showAccessDeniedModal = false"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                <div class="mb-2 inline-flex items-center rounded-full bg-rose-500/20 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-rose-300">
                    Access Denied
                </div>
                <h2 class="text-2xl font-bold text-slate-100">{{ flashAccessDenied.title || 'Only admin has access' }}</h2>
                <div class="mt-4 rounded-xl border border-rose-500/30 bg-rose-500/10 p-3">
                    <div class="text-xs font-semibold uppercase tracking-wider text-rose-300">{{ flashAccessDenied.status || 403 }}</div>
                    <p class="mt-1 text-sm text-rose-100">{{ flashAccessDenied.message || 'Only admin users can manage procurement access.' }}</p>
                </div>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        @click="showAccessDeniedModal = false"
                        class="rounded-lg bg-cyan-500 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-cyan-400"
                    >
                        OK
                    </button>
                </div>
            </div>
        </div>

        <!-- Toast Container -->
        <ToastContainer />
    </div>
</template>
