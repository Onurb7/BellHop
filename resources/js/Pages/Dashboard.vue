<script setup>
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleNames = computed(() => (user.value?.roles ?? []).map((role) => role.name).join(', '));

const logout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Dashboard" />

    <div class="min-h-screen bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
        <div class="max-w-2xl mx-auto px-4 py-16 text-center">
            <h1 class="text-2xl font-semibold">Welcome, {{ user?.name }}</h1>
            <p class="mt-2 text-sm opacity-70">
                Logged in as <span class="font-medium">{{ roleNames }}</span> ({{ user?.email }})
            </p>
            <p class="mt-6 text-sm opacity-50">Dashboard content for this role is coming soon.</p>

            <button
                type="button"
                @click="logout"
                class="mt-8 rounded-md border border-black/10 dark:border-white/10 px-4 py-2 text-sm hover:bg-black/5 dark:hover:bg-white/5"
            >
                Log out
            </button>
        </div>
    </div>
</template>
