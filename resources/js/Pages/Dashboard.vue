<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { BedDouble, CalendarRange, CalendarX, CircleCheck, History, Receipt, Wallet } from '@lucide/vue';
import AppLayout from '../Layouts/AppLayout.vue';
import StatTile from '../Components/Charts/StatTile.vue';
import BarChart from '../Components/Charts/BarChart.vue';
import TrendChart from '../Components/Charts/TrendChart.vue';
import { useDateFormat } from '../Composables/useDateFormat.js';
import { useMoney } from '../Composables/useMoney.js';

const props = defineProps({
    capacity: {
        type: Object,
        default: null,
    },
    reservations: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const user = computed(() => page.props.auth.user);

const { formatDate } = useDateFormat();
const { money } = useMoney();

const statusLabels = {
    pending_payment: 'Pending Payment',
    confirmed: 'Confirmed',
    checked_in: 'Checked In',
    checked_out: 'Checked Out',
    cancelled: 'Cancelled',
    no_show: 'No Show',
};

const statusBadgeClass = {
    pending_payment: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-gold-500/15 text-gold-700',
    checked_in: 'bg-emerald-100 text-emerald-700',
    checked_out: 'bg-black/5 text-black/50',
    cancelled: 'bg-red-100 text-red-700',
    no_show: 'bg-red-100 text-red-700',
};


function nights(booking) {
    return Math.round((new Date(booking.check_out) - new Date(booking.check_in)) / 86400000);
}

const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function formatTrendLabel(label) {
    if (!props.capacity) return label;

    if (props.capacity.view === 'month') {
        const [year, month] = label.split('-');
        return `${monthLabels[parseInt(month, 10) - 1]} ${year}`;
    }

    return formatDate(label);
}

const trendPoints = computed(() => {
    if (!props.capacity) return [];
    return props.capacity.trend.map((point) => ({ label: formatTrendLabel(point.label), value: point.value }));
});

function setTrendView(view) {
    router.get('/dashboard', { view }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Dashboard</h1>
        </template>

        <div v-if="capacity">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatTile
                    label="Occupancy today"
                    :value="`${capacity.kpis.occupancy_today_pct}%`"
                    :hint="`${capacity.kpis.occupied_today} of ${capacity.total_rooms} rooms`"
                />
                <StatTile label="Check-ins today" :value="String(capacity.kpis.check_ins_today)" />
                <StatTile label="Check-outs today" :value="String(capacity.kpis.check_outs_today)" />
                <StatTile
                    label="Awaiting payment"
                    :value="String(capacity.kpis.pending_payment_count)"
                    hint="Reservations still pending_payment"
                />
            </div>

            <div class="mt-6">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-serif text-lg">Occupancy trend</h2>
                    <div class="flex overflow-hidden rounded-md border border-black/10 text-sm">
                        <button
                            v-for="v in ['day', 'week', 'month']"
                            :key="v"
                            type="button"
                            @click="setTrendView(v)"
                            class="px-3 py-1.5 capitalize"
                            :class="v === capacity.view ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white' : 'hover:bg-black/5'"
                        >
                            {{ v }}
                        </button>
                    </div>
                </div>
                <TrendChart title="Occupancy over time" :points="trendPoints" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <BarChart title="Average occupancy by weekday" :points="capacity.by_weekday" direct-labels />
                <BarChart title="Average occupancy by day of month" :points="capacity.by_day_of_month" />
            </div>
        </div>

        <div v-else>
            <h2 class="mb-6 font-serif text-2xl">Welcome, {{ user?.name }}</h2>

            <div v-if="reservations">
                <div class="mb-8">
                    <h3 class="mb-3 flex items-center gap-2 font-serif text-lg">
                        <CalendarRange class="h-5 w-5 text-gold-600" />
                        Active reservations
                    </h3>

                    <div v-if="reservations.active.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <Link
                            v-for="booking in reservations.active"
                            :key="booking.id"
                            :href="`/my-reservations/${booking.id}`"
                            class="block rounded-lg border border-gold-500/20 bg-white p-5 transition hover:border-gold-500/40 hover:shadow-sm"
                        >
                            <div class="mb-4 flex items-start justify-between gap-2">
                                <div class="flex items-center gap-3">
                                    <div class="rounded-md bg-gold-50 p-2 text-gold-600">
                                        <BedDouble class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <p class="font-serif text-base leading-tight">{{ booking.room_type }}</p>
                                        <p class="text-xs opacity-50">Room {{ booking.room_number }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusBadgeClass[booking.status]">
                                    {{ statusLabels[booking.status] ?? booking.status }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 text-sm opacity-70">
                                <CalendarRange class="h-4 w-4 shrink-0 opacity-50" />
                                <span>{{ formatDate(booking.check_in) }} – {{ formatDate(booking.check_out) }}</span>
                                <span class="opacity-40">· {{ nights(booking) }} night{{ nights(booking) === 1 ? '' : 's' }}</span>
                            </div>

                            <div class="mt-4 flex items-center gap-2 border-t border-black/5 pt-3 text-sm">
                                <component
                                    :is="booking.balance_due_cents > 0 ? Wallet : CircleCheck"
                                    class="h-4 w-4 shrink-0"
                                    :class="booking.balance_due_cents > 0 ? 'text-red-600' : 'text-emerald-600'"
                                />
                                <span :class="booking.balance_due_cents > 0 ? 'text-red-600' : 'text-emerald-600'">
                                    {{ booking.balance_due_cents > 0 ? `${money(booking.balance_due_cents)} due` : 'Paid in full' }}
                                </span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-black/10 bg-white p-10 text-center">
                        <CalendarX class="h-8 w-8 opacity-30" />
                        <p class="text-sm opacity-50">No active reservations right now.</p>
                    </div>
                </div>

                <div>
                    <h3 class="mb-3 flex items-center gap-2 font-serif text-lg">
                        <History class="h-5 w-5 opacity-50" />
                        Past reservations
                    </h3>

                    <div v-if="reservations.past.length" class="grid gap-3 sm:grid-cols-2">
                        <Link
                            v-for="booking in reservations.past"
                            :key="booking.id"
                            :href="`/my-reservations/${booking.id}`"
                            class="flex items-center gap-3 rounded-lg border border-black/10 bg-white p-4 transition hover:border-gold-500/40 hover:shadow-sm"
                        >
                            <div class="rounded-md bg-black/5 p-2 text-black/50">
                                <BedDouble class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate text-sm font-medium">{{ booking.room_type }} — Room {{ booking.room_number }}</p>
                                    <span class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px]" :class="statusBadgeClass[booking.status]">
                                        {{ statusLabels[booking.status] ?? booking.status }}
                                    </span>
                                </div>
                                <p class="text-xs opacity-50">{{ formatDate(booking.check_in) }} – {{ formatDate(booking.check_out) }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-1 text-xs opacity-60">
                                <Receipt class="h-3.5 w-3.5" />
                                {{ money(booking.amount_paid_cents) }}
                            </div>
                        </Link>
                    </div>

                    <div v-else class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-black/10 bg-white p-10 text-center">
                        <History class="h-8 w-8 opacity-30" />
                        <p class="text-sm opacity-50">No past stays yet.</p>
                    </div>
                </div>
            </div>

            <p v-else class="text-center text-sm opacity-50">No reservations on file for this account yet.</p>
        </div>
    </AppLayout>
</template>
