<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        required: true,
    },
    points: {
        type: Array,
        required: true, // [{ label, value }]
    },
    // Direct value labels only make sense for a handful of bars (e.g. 7
    // weekdays) — flood a 31-bar chart with labels and none of them get
    // read. Those charts lean on the hover tooltip + table view instead.
    directLabels: {
        type: Boolean,
        default: false,
    },
    valueSuffix: {
        type: String,
        default: '%',
    },
});

const showTable = ref(false);
const hoveredIndex = ref(null);

const plotHeight = 160;

const maxValue = computed(() => {
    const max = Math.max(0, ...props.points.map((point) => point.value));
    if (max <= 0) return 10;
    const step = max <= 20 ? 5 : max <= 50 ? 10 : 20;
    return Math.ceil(max / step) * step;
});

const gridLines = computed(() => [0, 0.5, 1].map((fraction) => ({
    value: Math.round(maxValue.value * fraction),
    bottom: plotHeight * fraction,
})));

function barHeight(value) {
    return Math.max((value / maxValue.value) * plotHeight, 2);
}

function formatValue(value) {
    return `${value}${props.valueSuffix}`;
}
</script>

<template>
    <div class="rounded-lg border border-gold-500/20 bg-white p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="font-serif text-base">{{ title }}</h3>
            <button type="button" @click="showTable = !showTable" class="text-xs text-gold-600 hover:underline">
                {{ showTable ? 'View chart' : 'View as table' }}
            </button>
        </div>

        <table v-if="showTable" class="w-full text-left text-sm">
            <thead class="border-b border-black/10 text-xs uppercase tracking-wide opacity-50">
                <tr>
                    <th class="py-1">Label</th>
                    <th class="py-1 text-right">Value</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="point in points" :key="point.label" class="border-b border-black/5 last:border-0">
                    <td class="py-1">{{ point.label }}</td>
                    <td class="py-1 text-right">{{ formatValue(point.value) }}</td>
                </tr>
            </tbody>
        </table>

        <div v-else>
            <div class="relative flex items-end gap-[2px]" :style="{ height: `${plotHeight + 28}px` }">
                <div
                    v-for="line in gridLines"
                    :key="line.value"
                    class="pointer-events-none absolute left-0 right-0 border-t border-black/5"
                    :style="{ bottom: `${line.bottom + 28}px` }"
                >
                    <span class="absolute -top-2 left-0 text-[9px] opacity-40">{{ line.value }}{{ valueSuffix }}</span>
                </div>

                <div
                    v-for="(point, index) in points"
                    :key="point.label"
                    class="relative flex flex-1 flex-col items-center justify-end"
                    style="min-width: 4px"
                    tabindex="0"
                    @mouseenter="hoveredIndex = index"
                    @mouseleave="hoveredIndex = null"
                    @focus="hoveredIndex = index"
                    @blur="hoveredIndex = null"
                >
                    <span
                        v-if="directLabels"
                        class="pointer-events-none mb-1 text-[10px] text-[#1b1b18]"
                    >
                        {{ formatValue(point.value) }}
                    </span>

                    <div
                        v-if="hoveredIndex === index && !directLabels"
                        class="pointer-events-none absolute bottom-full z-10 mb-1 whitespace-nowrap rounded-md bg-[#1b1b18] px-2 py-1 text-xs text-white"
                    >
                        {{ point.label }}: {{ formatValue(point.value) }}
                    </div>

                    <div
                        class="w-full max-w-[24px] rounded-t transition-colors"
                        :class="hoveredIndex === index ? 'bg-gold-700' : 'bg-gold-600'"
                        :style="{ height: `${barHeight(point.value)}px` }"
                    ></div>

                    <span class="mt-1 h-4 text-[9px] opacity-50">{{ point.label }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
