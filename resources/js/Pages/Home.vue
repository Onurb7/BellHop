<script setup>
import { Head, Link } from '@inertiajs/vue3';
import HeroPhoto from '../Components/HeroPhoto.vue';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="Home" />

    <div class="relative min-h-screen overflow-hidden bg-white">
        <HeroPhoto class="absolute inset-0 h-full w-full" />
        <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent" />

        <div class="relative z-10 flex min-h-screen items-center justify-center px-4">
            <div class="max-w-lg text-center">
                <p class="text-xs uppercase tracking-[0.35em] text-gold-300">Est. Boutique Hospitality</p>
                <h1 class="mt-4 font-serif text-5xl text-white sm:text-6xl">🛎️ Bellhop</h1>
                <p class="mt-4 text-lg text-gold-100/80">{{ message }}</p>

                <Link
                    href="/login"
                    class="mt-8 inline-block rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-6 py-3 text-sm font-medium tracking-wide text-white shadow-lg transition hover:opacity-90"
                >
                    Enter the Lobby
                </Link>
            </div>
        </div>
    </div>
</template>
