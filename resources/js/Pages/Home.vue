<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    message: String,
});
</script>

<template>
    <Head title="Home" />

    <div class="min-h-screen flex items-center justify-center bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC]">
        <div class="text-center">
            <h1 class="text-2xl font-semibold">Bellhop</h1>
            <p class="mt-2 text-sm opacity-70">{{ message }}</p>
        </div>
    </div>
</template>
