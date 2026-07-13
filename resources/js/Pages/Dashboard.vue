<script setup>
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../Layouts/AppLayout.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const roleNames = computed(() => (user.value?.roles ?? []).map((role) => role.name));
const isAdmin = computed(() => roleNames.value.some((role) => ['admin', 'super-admin'].includes(role)));
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Dashboard</h1>
        </template>

        <div class="text-center">
            <h2 class="text-2xl font-semibold">Welcome, {{ user?.name }}</h2>
            <p class="mt-2 text-sm opacity-50">Dashboard content for this role is coming soon.</p>

            <Link
                v-if="isAdmin"
                href="/admin/rooms"
                class="mt-8 inline-block rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                Manage Rooms & Services
            </Link>
        </div>
    </AppLayout>
</template>
