<script setup>
import { router } from '@inertiajs/vue3';

const props = defineProps({
    status: { type: Number, default: 403 },
    title: { type: String, default: 'Only admin has access' },
    message: { type: String, default: 'Only admin users can manage procurement access.' },
});

function goBack() {
    window.history.length > 1 ? window.history.back() : router.visit(route('dashboard'));
}

function goDashboard() {
    router.visit(route('dashboard'));
}
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-slate-950">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(14,165,233,0.22),transparent_42%),radial-gradient(circle_at_85%_80%,rgba(244,63,94,0.16),transparent_38%),linear-gradient(145deg,#020617,#0b1220_48%,#020617)]"></div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-10">
            <div class="w-full max-w-xl">
                <div class="rounded-3xl border border-white/10 bg-slate-900/80 p-8 shadow-[0_0_120px_-35px_rgba(14,165,233,0.45)] backdrop-blur-xl">
                    <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-400/30 bg-cyan-400/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-cyan-300">
                        Access Restricted
                    </div>

                    <h1 class="text-3xl font-black leading-tight text-white sm:text-4xl">{{ props.title }}</h1>

                    <div class="mt-6 rounded-2xl border border-rose-400/30 bg-rose-400/10 px-5 py-4">
                        <div class="text-xs font-semibold uppercase tracking-wider text-rose-200">{{ props.status }}</div>
                        <p class="mt-1 text-sm text-rose-100">{{ props.message }}</p>
                    </div>

                    <div class="mt-8 flex flex-wrap gap-3">
                        <button
                            type="button"
                            @click="goBack"
                            class="rounded-xl bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-white transition-colors hover:bg-cyan-400"
                        >
                            Go Back
                        </button>
                        <button
                            type="button"
                            @click="goDashboard"
                            class="rounded-xl border border-slate-600 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition-colors hover:border-slate-500 hover:bg-slate-700"
                        >
                            Open Dashboard
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
