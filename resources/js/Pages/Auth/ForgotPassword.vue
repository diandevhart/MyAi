<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <h2 class="mb-1 text-2xl font-bold text-white">Forgot password?</h2>
        <p class="mb-6 text-sm text-slate-400">
            No worries. Enter your email and we'll send you a reset link.
        </p>

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-emerald-500/10 p-3 text-sm font-medium text-emerald-400"
        >
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="you@example.com"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/25 transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:opacity-50"
            >
                Send reset link
            </button>

            <p class="text-center text-sm text-slate-400">
                <Link :href="route('login')" class="font-medium text-cyan-400 hover:text-cyan-300 transition">
                    <span class="me-1">&larr;</span> Back to sign in
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
