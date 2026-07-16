<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import HeroPhoto from '../../Components/HeroPhoto.vue';

const form = useForm({
    email: '',
});

const submit = () => {
    form.post('/forgot-password');
};
</script>

<template>
    <Head title="Forgot Password" />

    <div class="relative min-h-screen bg-white text-[#1b1b18]">
        <div
            class="absolute inset-y-0 left-0 hidden w-[60%] lg:block"
            style="clip-path: polygon(0 0, 95% 0, 75% 100%, 0 100%)"
        >
            <HeroPhoto />
        </div>

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4 py-12 sm:px-6 lg:pl-[60%]">
            <div class="w-full max-w-sm">
                <div class="mb-8 text-center">
                    <p class="text-xs uppercase tracking-[0.35em] text-gold-600">Est. Boutique Hospitality</p>
                    <h1 class="mt-3 font-serif text-4xl text-[#1b1b18]">🛎️ Bellhop</h1>
                    <p class="mt-2 text-sm opacity-60">Reset your password</p>
                </div>

                <div class="rounded-lg border border-gold-500/25 bg-white p-6 shadow-sm shadow-gold-900/5">
                    <p v-if="form.recentlySuccessful" class="mb-4 rounded-md border border-gold-500/30 bg-gold-50 px-4 py-2 text-sm text-gold-700">
                        If an account exists for that email, a password reset link is on its way.
                    </p>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autofocus
                                autocomplete="username"
                                class="w-full rounded-md border border-black/10 bg-transparent px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-3 py-2 text-sm font-medium tracking-wide text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                        >
                            Email password reset link
                        </button>
                    </form>

                    <p class="mt-6 text-center text-sm">
                        <Link href="/login" class="text-gold-600 hover:underline">‹ Back to log in</Link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
