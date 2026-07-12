<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import HeroPhoto from '../../Components/HeroPhoto.vue';

const props = defineProps({
    demoLoginEnabled: Boolean,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};

const demoAccounts = [
    { role: 'admin', label: 'Admin' },
    { role: 'staff', label: 'Staff' },
    { role: 'guest', label: 'Guest' },
];

const loginAs = (role) => {
    router.post(`/login-as/${role}`);
};
</script>

<template>
    <Head title="Log in" />

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
                    <p class="mt-2 text-sm opacity-60">Sign in to manage your stay</p>
                </div>

                <div class="rounded-lg border border-gold-500/25 bg-white p-6 shadow-sm shadow-gold-900/5">
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

                        <div>
                            <label for="password" class="block text-sm font-medium mb-1">Password</label>
                            <input
                                id="password"
                                v-model="form.password"
                                type="password"
                                autocomplete="current-password"
                                class="w-full rounded-md border border-black/10 bg-transparent px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                        </div>

                        <label class="flex items-center gap-2 text-sm">
                            <input v-model="form.remember" type="checkbox" class="accent-gold-500" />
                            Remember me
                        </label>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-3 py-2 text-sm font-medium tracking-wide text-white shadow-sm transition hover:opacity-90 disabled:opacity-50"
                        >
                            Log in
                        </button>
                    </form>

                    <template v-if="demoLoginEnabled">
                        <div class="my-6 flex items-center gap-3 text-xs uppercase tracking-wide opacity-50">
                            <div class="h-px flex-1 bg-gold-500/30" />
                            Try it as a demo
                            <div class="h-px flex-1 bg-gold-500/30" />
                        </div>

                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="account in demoAccounts"
                                :key="account.role"
                                type="button"
                                @click="loginAs(account.role)"
                                class="rounded-md border border-gold-500/30 px-3 py-2 text-sm hover:bg-gold-500/10"
                            >
                                {{ account.label }}
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
