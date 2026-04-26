<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <h2 class="mb-1 text-2xl font-bold text-white">Create an account</h2>
        <p class="mb-6 text-sm text-slate-400">Get started with MyAi today</p>

        <form @submit.prevent="submit" class="space-y-5">
            <div>
                <label for="name" class="mb-1.5 block text-sm font-medium text-slate-300">Full Name</label>
                <input
                    id="name"
                    type="text"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="John Doe"
                />
                <InputError class="mt-1.5" :message="form.errors.name" />
            </div>

            <div>
                <label for="email" class="mb-1.5 block text-sm font-medium text-slate-300">Email</label>
                <input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="you@example.com"
                />
                <InputError class="mt-1.5" :message="form.errors.email" />
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-slate-300">Password</label>
                <input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="••••••••"
                />
                <InputError class="mt-1.5" :message="form.errors.password" />
            </div>

            <div>
                <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-slate-300">Confirm Password</label>
                <input
                    id="password_confirmation"
                    type="password"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-700 bg-slate-900 px-4 py-2.5 text-sm text-white placeholder-slate-500 transition focus:border-cyan-500 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                    placeholder="••••••••"
                />
                <InputError class="mt-1.5" :message="form.errors.password_confirmation" />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-cyan-500 px-4 py-2.5 text-sm font-semibold text-slate-950 shadow-lg shadow-cyan-500/25 transition hover:bg-cyan-400 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:opacity-50"
            >
                Create account
            </button>

            <p class="text-center text-sm text-slate-400">
                Already have an account?
                <Link :href="route('login')" class="font-medium text-cyan-400 hover:text-cyan-300 transition">
                    Sign in
                </Link>
            </p>
        </form>
    </GuestLayout>
</template>
