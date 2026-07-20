<script setup>
import { Head, Link } from '@inertiajs/vue3';
import HeroPhoto from '../Components/HeroPhoto.vue';
import TestimonialRotator from '../Components/TestimonialRotator.vue';

defineProps({
    message: String,
    featuredReviews: Array,
});
</script>

<template>
    <Head title="Home" />

    <div class="relative h-screen overflow-hidden bg-white">
        <div class="absolute inset-0 h-full w-full">
            <HeroPhoto />
        </div>
        <div class="absolute inset-0 bg-black/40" />

        <div class="relative z-10 flex h-full items-center justify-center px-4">
            <div class="w-full max-w-sm rounded-lg border border-gold-500/25 bg-white p-8 text-center shadow-xl shadow-black/20">
                <p class="text-xs uppercase tracking-[0.35em] text-gold-600">Est. Boutique Hospitality</p>
                <h1 class="mt-4 font-serif text-4xl sm:text-5xl">🛎️ Bellhop</h1>
                <p class="mt-4 text-sm opacity-70">{{ message }}</p>

                <TestimonialRotator v-if="featuredReviews.length" :reviews="featuredReviews" class="mt-6" />

                <div class="mt-8 flex flex-col gap-3">
                    <Link
                        href="/rooms"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-6 py-3 text-sm font-medium tracking-wide text-white shadow-sm transition hover:opacity-90"
                    >
                        Book Your Stay
                    </Link>
                    <Link
                        href="/login"
                        class="rounded-md border border-gold-500/30 px-6 py-3 text-sm font-medium tracking-wide transition hover:bg-gold-500/10"
                    >
                        Enter the Lobby
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
