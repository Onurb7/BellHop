<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flashSuccess = computed(() => page.props.flash?.success);
</script>

<template>
    <div class="min-h-screen bg-[#FAF8F3] text-[#1b1b18]">
        <header class="border-b border-gold-500/20 bg-white px-6 py-4">
            <div class="mx-auto flex max-w-6xl items-center justify-between">
                <Link href="/" class="font-serif text-lg">🛎️ Bellhop</Link>
                <nav class="flex items-center gap-4 text-sm">
                    <Link href="/rooms" class="hover:text-gold-600">Rooms</Link>
                    <Link
                        v-if="user"
                        href="/dashboard"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 font-medium text-white"
                    >
                        My Account
                    </Link>
                    <Link
                        v-else
                        href="/login"
                        class="rounded-md border border-gold-500/30 px-4 py-2 hover:bg-gold-500/10"
                    >
                        Log in
                    </Link>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-8">
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
