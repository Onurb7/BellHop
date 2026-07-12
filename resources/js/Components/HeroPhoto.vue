<script setup>
import { ref } from 'vue';

defineProps({
    alt: {
        type: String,
        default: 'Bellhop — boutique hotel',
    },
});

const imageFailed = ref(false);
</script>

<template>
    <div class="relative h-full w-full bg-gold-50">
        <picture v-show="!imageFailed">
            <source srcset="/images/login.webp" type="image/webp" />
            <img
                src="/images/login.jpg"
                :alt="alt"
                fetchpriority="high"
                class="h-full w-full object-cover"
                @error="imageFailed = true"
            />
        </picture>

        <div v-if="imageFailed" class="flex h-full w-full items-center justify-center px-6 text-center">
            <p class="font-serif text-sm text-gold-700">
                Drop a photo at <code class="font-mono text-xs">public/images/login.jpg</code>
            </p>
        </div>
    </div>
</template>
