<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
    hint: {
        type: String,
        default: '',
    },
});
</script>

<template>
    <div class="rounded-lg border border-gold-500/20 bg-white p-5">
        <p class="text-xs uppercase tracking-wide opacity-50">{{ label }}</p>
        <p class="mt-1 text-3xl font-semibold text-[#1b1b18]">{{ value }}</p>
        <p v-if="hint" class="mt-1 text-xs opacity-50">{{ hint }}</p>
    </div>
</template>
