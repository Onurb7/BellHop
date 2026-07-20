<script setup>
import { usePage } from '@inertiajs/vue3';
import { Calendar, ChevronLeft, ChevronRight } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, useAttrs, watch } from 'vue';
import { todayDateString, useDateFormat } from '../Composables/useDateFormat.js';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: String, default: '' },
    min: { type: String, default: '' },
    max: { type: String, default: '' },
    placeholder: { type: String, default: 'Select date' },
});

const emit = defineEmits(['update:modelValue']);

const attrs = useAttrs();
const page = usePage();
const { formatDate } = useDateFormat();

const weekStart = computed(() => page.props.auth?.user?.week_start ?? 'monday');
const weekStartIndex = computed(() => (weekStart.value === 'sunday' ? 0 : 1));

const WEEKDAY_LABELS = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
const orderedWeekdayLabels = computed(() => {
    const start = weekStartIndex.value;
    return [...WEEKDAY_LABELS.slice(start), ...WEEKDAY_LABELS.slice(0, start)];
});

const open = ref(false);
const root = ref(null);

function anchorDate() {
    return props.modelValue || todayDateString();
}

const [anchorYear, anchorMonth] = anchorDate().split('-').map(Number);
const viewYear = ref(anchorYear);
const viewMonth = ref(anchorMonth - 1); // 0-indexed

function resetView() {
    const [year, month] = anchorDate().split('-').map(Number);
    viewYear.value = year;
    viewMonth.value = month - 1;
}

function toggle() {
    if (!open.value) {
        resetView();
    }
    open.value = !open.value;
}

function close() {
    open.value = false;
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        close();
    }
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('mousedown', onDocumentClick);
    } else {
        document.removeEventListener('mousedown', onDocumentClick);
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocumentClick);
});

function shiftMonth(delta) {
    let month = viewMonth.value + delta;
    let year = viewYear.value;

    if (month < 0) {
        month = 11;
        year -= 1;
    } else if (month > 11) {
        month = 0;
        year += 1;
    }

    viewMonth.value = month;
    viewYear.value = year;
}

function toISO(year, month, day) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function makeCell(year, month, day, otherMonth) {
    const iso = toISO(year, month, day);

    return {
        iso,
        day,
        otherMonth,
        isToday: iso === todayDateString(),
        selected: iso === props.modelValue,
        disabled: (!!props.min && iso < props.min) || (!!props.max && iso > props.max),
    };
}

const gridDays = computed(() => {
    const year = viewYear.value;
    const month = viewMonth.value;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const rawFirstWeekday = new Date(year, month, 1).getDay();
    const leading = (rawFirstWeekday - weekStartIndex.value + 7) % 7;
    const prevMonthDays = new Date(year, month, 0).getDate();
    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const nextMonth = month === 11 ? 0 : month + 1;
    const nextYear = month === 11 ? year + 1 : year;

    const cells = [];

    for (let i = leading - 1; i >= 0; i--) {
        cells.push(makeCell(prevYear, prevMonth, prevMonthDays - i, true));
    }

    for (let day = 1; day <= daysInMonth; day++) {
        cells.push(makeCell(year, month, day, false));
    }

    let nextDay = 1;
    while (cells.length < 42) {
        cells.push(makeCell(nextYear, nextMonth, nextDay, true));
        nextDay += 1;
    }

    return cells;
});

const monthLabel = computed(() => new Date(viewYear.value, viewMonth.value, 1).toLocaleDateString(undefined, { month: 'long', year: 'numeric' }));

function selectDay(cell) {
    if (cell.disabled) return;

    emit('update:modelValue', cell.iso);
    close();
}
</script>

<template>
    <div ref="root" class="relative inline-block" :class="attrs.class">
        <button
            type="button"
            v-bind="{ ...attrs, class: null }"
            @click="toggle"
            class="flex w-full items-center justify-between gap-2 rounded-md border border-black/10 bg-white px-3 py-2 text-left text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
        >
            <span :class="modelValue ? '' : 'opacity-50'">{{ modelValue ? formatDate(modelValue) : placeholder }}</span>
            <Calendar class="h-4 w-4 shrink-0 opacity-50" />
        </button>

        <div
            v-if="open"
            class="absolute left-0 z-20 mt-1 w-64 rounded-lg border border-gold-500/20 bg-white p-3 shadow-lg"
        >
            <div class="mb-2 flex items-center justify-between">
                <button type="button" @click="shiftMonth(-1)" class="rounded-md p-1 hover:bg-gold-500/10">
                    <ChevronLeft class="h-4 w-4" />
                </button>
                <span class="text-sm font-medium">{{ monthLabel }}</span>
                <button type="button" @click="shiftMonth(1)" class="rounded-md p-1 hover:bg-gold-500/10">
                    <ChevronRight class="h-4 w-4" />
                </button>
            </div>

            <div class="grid grid-cols-7 gap-0.5 text-center text-xs uppercase tracking-wide opacity-50">
                <span v-for="label in orderedWeekdayLabels" :key="label" class="py-1">{{ label }}</span>
            </div>

            <div class="grid grid-cols-7 gap-0.5">
                <button
                    v-for="cell in gridDays"
                    :key="cell.iso"
                    type="button"
                    :disabled="cell.disabled"
                    @click="selectDay(cell)"
                    class="rounded-md py-1.5 text-center text-sm transition-colors"
                    :class="[
                        cell.otherMonth ? 'text-black/30' : 'text-[#1b1b18]',
                        cell.disabled ? 'cursor-not-allowed opacity-30' : 'hover:bg-gold-500/10',
                        cell.selected ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white hover:from-gold-500 hover:to-gold-600' : '',
                        cell.isToday && !cell.selected ? 'font-semibold text-gold-700' : '',
                    ]"
                >
                    {{ cell.day }}
                </button>
            </div>
        </div>
    </div>
</template>
