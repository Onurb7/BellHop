<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import HeroPhoto from '../../Components/HeroPhoto.vue';

const props = defineProps({
    token: String,
    email: String,
});

const form = useForm({
    token: props.token,
    email: props.email ?? '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Set Your Password" />

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
                    <p class="mt-2 text-sm opacity-60">Set your password</p>
                </div>

                <div class="rounded-lg border border-gold-500/25 bg-white p-6 shadow-sm shadow-gold-900/5">
                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <label for="email" class="block text-sm font-medium mb-1">Email</label>
                            <input
                                id="email"
                                v-model="form.email"
                                type="email"
                                autocomplete="username"
                                class="w-full rounded-md border border-black/10 bg-transparent px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label for="password" class="block text-sm font-medium mb-1">New password</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autofocus
                                autocomplete="new-password"
                                class="w-full rounded-md border border-black/10 bg-transparent px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <div>
                            <label for="password_confirmation" class="block text-sm font-medium mb-1">Confirm password</label>
                            <input
                                id="password_confirmation"
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full rounded-md border border-black/10 bg-transparent px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-3 py-2 text-sm font-medium tracking-wide text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                        >
                            Set password
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>
