<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const flashSuccess = computed(() => page.props.flash?.success);

const nav = [
    { label: 'Room Types', href: '/admin/room-types' },
    { label: 'Rooms', href: '/admin/rooms' },
    { label: 'Services', href: '/admin/services' },
];
</script>

<template>
    <div class="min-h-screen bg-[#FAF8F3] text-[#1b1b18]">
        <header class="border-b border-gold-500/20 bg-white">
            <div class="mx-auto flex max-w-5xl items-center justify-between px-6 py-4">
                <Link href="/dashboard" class="font-serif text-lg text-[#1b1b18]">🛎️ Bellhop Admin</Link>
                <nav class="flex gap-4 text-sm">
                    <Link
                        v-for="item in nav"
                        :key="item.href"
                        :href="item.href"
                        class="rounded-md px-3 py-1.5 hover:bg-gold-500/10"
                    >
                        {{ item.label }}
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-6 py-10">
            <div
                v-if="flashSuccess"
                class="mb-6 rounded-md border border-gold-500/30 bg-gold-50 px-4 py-2 text-sm text-gold-700"
            >
                {{ flashSuccess }}
            </div>

            <slot />
        </main>
    </div>
</template>
