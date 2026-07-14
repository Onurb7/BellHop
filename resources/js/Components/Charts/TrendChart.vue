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
    valueSuffix: {
        type: String,
        default: '%',
    },
});

const showTable = ref(false);
const hoveredIndex = ref(null);
const svgEl = ref(null);

const width = 600;
const height = 130;
const padding = { top: 8, right: 14, bottom: 18, left: 14 };
const plotWidth = width - padding.left - padding.right;
const plotHeight = height - padding.top - padding.bottom;

const maxValue = computed(() => {
    const max = Math.max(0, ...props.points.map((point) => point.value));
    if (max <= 0) return 10;
    const step = max <= 20 ? 5 : max <= 50 ? 10 : 20;
    return Math.ceil(max / step) * step;
});

function xFor(index) {
    if (props.points.length <= 1) return padding.left + plotWidth / 2;
    return padding.left + (index / (props.points.length - 1)) * plotWidth;
}

function yFor(value) {
    return padding.top + plotHeight - (value / maxValue.value) * plotHeight;
}

const linePath = computed(() =>
    props.points.map((point, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(point.value)}`).join(' '),
);

const areaPath = computed(() => {
    if (props.points.length === 0) return '';
    const baseline = padding.top + plotHeight;
    const first = `M ${xFor(0)} ${baseline}`;
    const line = props.points.map((point, i) => `L ${xFor(i)} ${yFor(point.value)}`).join(' ');
    const last = `L ${xFor(props.points.length - 1)} ${baseline} Z`;
    return `${first} ${line} ${last}`;
});

const gridLines = computed(() => [0, 0.5, 1].map((fraction) => ({
    value: Math.round(maxValue.value * fraction),
    y: padding.top + plotHeight * (1 - fraction),
})));

const labelIndices = computed(() => {
    const n = props.points.length;
    if (n <= 6) return props.points.map((_, i) => i);
    const step = Math.ceil(n / 6);
    const indices = [];
    for (let i = 0; i < n; i += step) indices.push(i);
    if (indices[indices.length - 1] !== n - 1) indices.push(n - 1);
    return indices;
});

function onPointerMove(event) {
    if (!svgEl.value || props.points.length === 0) return;

    const rect = svgEl.value.getBoundingClientRect();
    const relativeX = (event.clientX - rect.left) / rect.width;
    const targetX = padding.left + relativeX * plotWidth;

    let nearest = 0;
    let nearestDist = Infinity;

    props.points.forEach((_, i) => {
        const dist = Math.abs(xFor(i) - targetX);
        if (dist < nearestDist) {
            nearestDist = dist;
            nearest = i;
        }
    });

    hoveredIndex.value = nearest;
}

function onPointerLeave() {
    hoveredIndex.value = null;
}

const tooltipStyle = computed(() => {
    if (hoveredIndex.value === null) return { display: 'none' };

    const point = props.points[hoveredIndex.value];

    return {
        left: `${(xFor(hoveredIndex.value) / width) * 100}%`,
        top: `${(yFor(point.value) / height) * 100}%`,
    };
});

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
                    <th class="py-1">Date</th>
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

        <div v-else class="relative">
            <svg
                ref="svgEl"
                :viewBox="`0 0 ${width} ${height}`"
                class="w-full"
                @pointermove="onPointerMove"
                @pointerleave="onPointerLeave"
            >
                <line
                    v-for="line in gridLines"
                    :key="line.value"
                    :x1="padding.left"
                    :x2="width - padding.right"
                    :y1="line.y"
                    :y2="line.y"
                    stroke="rgba(0,0,0,0.05)"
                    stroke-width="1"
                />

                <path :d="areaPath" fill="#c6a15b" fill-opacity="0.12" stroke="none" />
                <path :d="linePath" fill="none" stroke="#a17e3e" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />

                <g v-if="hoveredIndex !== null">
                    <line
                        :x1="xFor(hoveredIndex)"
                        :x2="xFor(hoveredIndex)"
                        :y1="padding.top"
                        :y2="padding.top + plotHeight"
                        stroke="rgba(27,27,24,0.2)"
                        stroke-width="1"
                    />
                    <circle
                        :cx="xFor(hoveredIndex)"
                        :cy="yFor(points[hoveredIndex].value)"
                        r="4"
                        fill="#a17e3e"
                        stroke="#ffffff"
                        stroke-width="2"
                    />
                </g>
            </svg>

            <span
                v-for="line in gridLines"
                :key="`t-${line.value}`"
                class="pointer-events-none absolute -translate-y-full text-[9px] opacity-40"
                :style="{ left: `${(padding.left / width) * 100}%`, top: `${(line.y / height) * 100}%` }"
            >{{ line.value }}{{ valueSuffix }}</span>

            <span
                v-for="index in labelIndices"
                :key="`l-${index}`"
                class="pointer-events-none absolute -translate-x-1/2 text-[9px] opacity-50"
                :style="{ left: `${(xFor(index) / width) * 100}%`, top: `${((height - padding.bottom + 6) / height) * 100}%` }"
            >{{ points[index].label }}</span>

            <div
                v-if="hoveredIndex !== null"
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-[130%] whitespace-nowrap rounded-md bg-[#1b1b18] px-2 py-1 text-xs text-white"
                :style="tooltipStyle"
            >
                {{ points[hoveredIndex].label }}: {{ formatValue(points[hoveredIndex].value) }}
            </div>
        </div>
    </div>
</template>
