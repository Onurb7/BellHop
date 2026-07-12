<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown, ChevronRight } from '@lucide/vue';
import { computed, ref } from 'vue';

const props = defineProps({
    label: {
        type: String,
        required: true,
    },
    items: {
        type: Array,
        required: true,
    },
});

const page = usePage();

const isItemActive = (href) => page.url.startsWith(href);
const hasActiveItem = computed(() => props.items.some((item) => isItemActive(item.href)));

const open = ref(true);
</script>

<template>
    <div>
        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-center justify-between rounded-md px-3 py-2 text-sm font-medium hover:bg-gold-500/10"
            :class="hasActiveItem ? 'text-gold-700' : 'text-[#1b1b18]'"
        >
            {{ label }}
            <ChevronDown v-if="open" class="h-4 w-4" />
            <ChevronRight v-else class="h-4 w-4" />
        </button>

        <div v-show="open" class="mt-1 space-y-1 border-l border-gold-500/20 pl-3">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="block rounded-md px-3 py-1.5 text-sm hover:bg-gold-500/10"
                :class="isItemActive(item.href) ? 'bg-gold-500/15 text-gold-700' : 'text-[#1b1b18]/80'"
            >
                {{ item.label }}
            </Link>
        </div>
    </div>
</template>
