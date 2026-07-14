<script setup>
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useDateFormat } from '../../Composables/useDateFormat.js';

const { formatDate } = useDateFormat();

const props = defineProps({
    view: String,
    date: String,
    floor: String,
    floors: Array,
    rangeStart: String,
    rangeEnd: String,
    rooms: Array,
    bookings: Array,
});

const page = usePage();
const isAdmin = computed(() => {
    const roles = (page.props.auth.user?.roles ?? []).map((role) => role.name);
    return roles.some((role) => ['admin', 'super-admin'].includes(role));
});

const hoveredRoomId = ref(null);
const hoveredDate = ref(null);

function isCellHovered(room, date) {
    return hoveredRoomId.value === room.id || hoveredDate.value === date;
}

function halfClass(filled, hovered) {
    if (filled) {
        return hovered ? 'bg-gold-500/45 text-gold-700' : 'bg-gold-500/30 text-gold-700';
    }

    return hovered ? 'bg-gold-500/10' : 'bg-white';
}

function roomCellClass(room) {
    return hoveredRoomId.value === room.id ? 'bg-gold-500/10' : 'bg-white';
}

function headerCellClass(date) {
    if (hoveredDate.value === date) {
        return isToday(date) ? 'bg-gold-500/20' : 'bg-gold-500/10';
    }

    return isToday(date) ? 'bg-gold-100' : 'bg-gold-50';
}

function goToRoom(room) {
    if (!isAdmin.value) return;

    router.visit(`/admin/rooms/${room.id}/edit`);
}

// Deliberately not toISOString() — that always converts to UTC, which
// silently shifts the date by a day (relabeling the first column) for any
// browser whose local timezone isn't UTC. These getters read the Date
// object's own local calendar fields instead.
function toISO(d) {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function parseISO(s) {
    return new Date(`${s}T00:00:00`);
}

const dates = computed(() => {
    const result = [];
    const cursor = parseISO(props.rangeStart);
    const end = parseISO(props.rangeEnd);

    while (cursor < end) {
        result.push(toISO(cursor));
        cursor.setDate(cursor.getDate() + 1);
    }

    return result;
});

const roomsByFloor = computed(() => {
    const groups = {};

    for (const room of props.rooms) {
        (groups[room.floor] ??= []).push(room);
    }

    return Object.entries(groups)
        .sort(([a], [b]) => a.localeCompare(b, undefined, { numeric: true }))
        .map(([floor, rooms]) => ({ floor, rooms }));
});

const bookingsByRoom = computed(() => {
    const map = {};

    for (const booking of props.bookings) {
        (map[booking.room_id] ??= []).push(booking);
    }

    return map;
});

function dayInfo(room, date) {
    const bookings = bookingsByRoom.value[room.id] ?? [];
    const departure = bookings.find((b) => b.check_out === date);
    const arrival = bookings.find((b) => b.check_in === date);
    const midstay = bookings.find((b) => b.check_in < date && b.check_out > date);

    return {
        am: { filled: !!departure || !!midstay, label: departure ? 'OUT' : '', booking: departure ?? midstay },
        pm: { filled: !!arrival || !!midstay, label: arrival ? 'IN' : '', booking: arrival ?? midstay },
    };
}

function cellTooltip(room, date) {
    const info = dayInfo(room, date);
    const parts = [];

    if (info.am.label && info.am.booking) parts.push(`Departing: ${info.am.booking.guest_name}`);
    if (info.pm.label && info.pm.booking) parts.push(`Arriving: ${info.pm.booking.guest_name}`);
    if (!info.am.label && !info.pm.label && info.am.booking) parts.push(`Staying: ${info.am.booking.guest_name}`);

    return parts.join(' · ') || 'Vacant';
}

function formatHeader(date) {
    const d = parseISO(date);
    return d.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: props.view === 'day' ? 'long' : 'short' });
}

function isToday(date) {
    return date === toISO(new Date());
}

function navigate(overrides) {
    router.get('/calendar', {
        view: props.view,
        date: props.date,
        floor: props.floor || undefined,
        ...overrides,
    }, { preserveState: true, preserveScroll: true });
}

function shift(direction) {
    const d = parseISO(props.date);

    if (props.view === 'day') d.setDate(d.getDate() + direction);
    else if (props.view === 'week') d.setDate(d.getDate() + 7 * direction);
    else d.setMonth(d.getMonth() + direction);

    navigate({ date: toISO(d) });
}

function goToday() {
    navigate({ date: toISO(new Date()) });
}

function setView(view) {
    navigate({ view });
}

function setFloor(event) {
    navigate({ floor: event.target.value || undefined });
}
</script>

<template>
    <Head title="Calendar" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Calendar</h1>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button type="button" @click="shift(-1)" class="rounded-md border border-black/10 px-3 py-1.5 text-sm hover:bg-black/5">‹</button>
                <button type="button" @click="goToday" class="rounded-md border border-black/10 px-3 py-1.5 text-sm hover:bg-black/5">Today</button>
                <button type="button" @click="shift(1)" class="rounded-md border border-black/10 px-3 py-1.5 text-sm hover:bg-black/5">›</button>
                <span class="ml-2 text-sm opacity-70">{{ formatDate(rangeStart) }} – {{ formatDate(rangeEnd) }}</span>
            </div>

            <div class="flex items-center gap-3">
                <select
                    :value="floor ?? ''"
                    @change="setFloor"
                    class="rounded-md border border-black/10 px-3 py-1.5 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                >
                    <option value="">All floors</option>
                    <option v-for="f in floors" :key="f" :value="f">Floor {{ f }}</option>
                </select>

                <div class="flex overflow-hidden rounded-md border border-black/10 text-sm">
                    <button
                        v-for="v in ['day', 'week', 'month']"
                        :key="v"
                        type="button"
                        @click="setView(v)"
                        class="px-3 py-1.5 capitalize"
                        :class="v === view ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white' : 'hover:bg-black/5'"
                    >
                        {{ v }}
                    </button>
                </div>
            </div>
        </div>

        <div class="inline-block max-w-full overflow-x-auto rounded-lg border border-gold-500/20">
            <table class="cursor-default border-collapse text-sm">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 border-b border-r border-gold-500/20 bg-gold-50 px-3 py-2 text-left text-xs uppercase tracking-wide text-gold-700">
                            Room
                        </th>
                        <th
                            v-for="date in dates"
                            :key="date"
                            class="min-w-[64px] border-b border-r border-gold-500/20 px-2 py-2 text-center text-xs uppercase tracking-wide text-gold-700 transition-colors duration-300"
                            :class="headerCellClass(date)"
                            @mouseenter="hoveredDate = date"
                            @mouseleave="hoveredDate = null"
                        >
                            {{ formatHeader(date) }}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="group in roomsByFloor" :key="group.floor">
                        <tr>
                            <td :colspan="dates.length + 1" class="border-b border-black/5 bg-black/[0.03] px-3 py-1 text-xs font-medium uppercase tracking-wide opacity-60">
                                Floor {{ group.floor }}
                            </td>
                        </tr>
                        <tr v-for="room in group.rooms" :key="room.id">
                            <td
                                class="sticky left-0 z-10 whitespace-nowrap border-b border-r border-gold-500/20 px-3 py-2 font-medium transition-colors duration-300"
                                :class="[roomCellClass(room), isAdmin ? 'cursor-pointer' : '']"
                                :title="isAdmin ? 'Edit this room' : ''"
                                @mouseenter="hoveredRoomId = room.id"
                                @mouseleave="hoveredRoomId = null"
                                @click="goToRoom(room)"
                            >
                                {{ room.number }} <span class="font-normal opacity-60">— {{ room.title }}</span>
                            </td>
                            <td
                                v-for="date in dates"
                                :key="date"
                                class="h-10 border-b border-r border-black/5 p-0"
                                :title="cellTooltip(room, date)"
                            >
                                <div class="flex h-full w-full">
                                    <div
                                        class="flex w-1/2 items-center justify-center text-[9px] font-semibold transition-colors duration-300"
                                        :class="halfClass(dayInfo(room, date).am.filled, isCellHovered(room, date))"
                                    >
                                        {{ dayInfo(room, date).am.label }}
                                    </div>
                                    <div
                                        class="flex w-1/2 items-center justify-center text-[9px] font-semibold transition-colors duration-300"
                                        :class="halfClass(dayInfo(room, date).pm.filled, isCellHovered(room, date))"
                                    >
                                        {{ dayInfo(room, date).pm.label }}
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <tr v-if="rooms.length === 0">
                        <td :colspan="dates.length + 1" class="px-4 py-8 text-center opacity-50">No rooms match this filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
