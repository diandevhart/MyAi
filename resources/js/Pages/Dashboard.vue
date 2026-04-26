<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';

defineOptions({ layout: AppLayout });
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-white">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-400">Welcome back. Here's an overview of your operations.</p>
            </div>

            <!-- Placeholder cards -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-cyan-500/10">
                            <i class="pi pi-building text-cyan-400"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Warehouses</p>
                            <p class="text-xl font-semibold text-white">—</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-500/10">
                            <i class="pi pi-box text-emerald-400"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Total Items</p>
                            <p class="text-xl font-semibold text-white">—</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-500/10">
                            <i class="pi pi-exclamation-triangle text-amber-400"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Low Stock</p>
                            <p class="text-xl font-semibold text-white">—</p>
                        </div>
                    </div>
                </div>
                <div class="rounded-xl border border-slate-800 bg-slate-950 p-5">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-500/10">
                            <i class="pi pi-file-edit text-violet-400"></i>
                        </div>
                        <div>
                            <p class="text-sm text-slate-400">Open RFQs</p>
                            <p class="text-xl font-semibold text-white">—</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Empty state -->
            <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-700 bg-slate-950/50 py-16">
                <div class="flex h-14 w-14 items-center justify-center rounded-full bg-slate-800">
                    <i class="pi pi-chart-bar text-2xl text-slate-500"></i>
                </div>
                <h3 class="mt-4 text-lg font-medium text-slate-300">Dashboard coming soon</h3>
                <p class="mt-1 text-sm text-slate-500">Summary stats and activity feeds will appear here.</p>
            </div>
    </div>
</template>
