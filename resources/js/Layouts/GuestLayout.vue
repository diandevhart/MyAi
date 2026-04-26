<script setup>
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-slate-900 px-4">
        <!-- Floating particles background -->
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -top-40 -left-40 h-80 w-80 rounded-full bg-cyan-500/5 blur-3xl"></div>
            <div class="absolute -right-40 -bottom-40 h-80 w-80 rounded-full bg-cyan-500/5 blur-3xl"></div>
        </div>

        <!-- Logo -->
        <div class="relative mb-8 flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-500/20">
                <i class="pi pi-warehouse text-xl text-cyan-400"></i>
            </div>
            <span class="text-2xl font-bold text-cyan-400">MyAi</span>
        </div>

        <!-- Card -->
        <div class="relative w-full max-w-md overflow-hidden rounded-2xl border border-slate-800 bg-slate-950 p-8 shadow-2xl shadow-cyan-500/5">
            <slot />
        </div>

        <!-- Footer -->
        <p class="mt-8 text-sm text-slate-500">&copy; {{ new Date().getFullYear() }} MyAi. All rights reserved.</p>
    </div>
</template>
