<script setup>
import { ref, computed, onMounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Menu from 'primevue/menu';

const page = usePage();
const user = computed(() => page.props.auth?.user);

const collapsed = ref(false);
const userMenuRef = ref(null);

onMounted(() => {
    const saved = localStorage.getItem('sidebar_collapsed');
    if (saved !== null) collapsed.value = saved === 'true';
});

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
];

function isActive(routeName) {
    return route().current(routeName);
}

function navigate(routeName) {
    router.visit(route(routeName));
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

const sidebarWidth = computed(() => (collapsed.value ? '72px' : '260px'));
</script>

<template>
    <div class="min-h-screen bg-slate-900">
        <!-- Sidebar -->
        <aside
            class="fixed top-0 left-0 z-40 h-screen border-r border-slate-800 bg-slate-950 transition-all duration-300"
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
            <nav class="mt-4 flex flex-col gap-1 px-3">
                <button
                    v-for="item in navItems"
                    :key="item.route"
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
            <div class="absolute bottom-4 left-0 w-full px-3">
                <button
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
                <button class="relative flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-slate-800 hover:text-slate-100 transition-colors">
                    <i class="pi pi-bell text-base"></i>
                </button>

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

        <!-- Toast Container -->
        <ToastContainer />
    </div>
</template>
