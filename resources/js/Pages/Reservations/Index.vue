<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    status: String,
    search: String,
    statuses: Array,
    bookings: Object,
});

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

const searchInput = ref(props.search ?? '');

function money(cents) {
    return `$${(cents / 100).toFixed(2)}`;
}

function setStatus(status) {
    router.get(
        '/reservations',
        { status: status || undefined, search: searchInput.value || undefined },
        { preserveState: true, preserveScroll: true },
    );
}

let debounceHandle = null;

watch(searchInput, (value) => {
    clearTimeout(debounceHandle);
    debounceHandle = setTimeout(() => {
        router.get(
            '/reservations',
            { status: props.status || undefined, search: value || undefined },
            { preserveState: true, preserveScroll: true },
        );
    }, 300);
});

function goToPage(url) {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Reservations" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Reservations</h1>
        </template>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <input
                v-model="searchInput"
                type="text"
                placeholder="Search guest name, room number, or room type…"
                class="w-full max-w-sm rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
            />
            <Link
                href="/reservations/new"
                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                + New Reservation
            </Link>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                type="button"
                @click="setStatus(null)"
                class="rounded-md border border-black/10 px-3 py-1.5 text-sm capitalize"
                :class="!status ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white' : 'hover:bg-black/5'"
            >
                All
            </button>
            <button
                v-for="s in statuses"
                :key="s"
                type="button"
                @click="setStatus(s)"
                class="rounded-md border border-black/10 px-3 py-1.5 text-sm"
                :class="status === s ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white' : 'hover:bg-black/5'"
            >
                {{ statusLabels[s] ?? s }}
            </button>
        </div>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Room</th>
                        <th class="px-4 py-3">Dates</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Total</th>
                        <th class="px-4 py-3">Paid</th>
                        <th class="px-4 py-3">Balance Due</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="booking in bookings.data"
                        :key="booking.id"
                        class="cursor-pointer border-b border-black/5 last:border-0 hover:bg-gold-500/10"
                        @click="router.visit(`/reservations/${booking.id}`)"
                    >
                        <td class="px-4 py-3 font-medium">{{ booking.guest_name }}</td>
                        <td class="px-4 py-3">{{ booking.room_number }} <span class="opacity-60">— {{ booking.room_type }}</span></td>
                        <td class="px-4 py-3">{{ booking.check_in }} → {{ booking.check_out }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs" :class="statusBadgeClass[booking.status]">
                                {{ statusLabels[booking.status] ?? booking.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">{{ money(booking.total_cents) }}</td>
                        <td class="px-4 py-3">{{ money(booking.amount_paid_cents) }}</td>
                        <td class="px-4 py-3">
                            <span :class="booking.balance_due_cents > 0 ? 'font-medium text-red-600' : 'opacity-60'">
                                {{ money(booking.balance_due_cents) }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center opacity-50">No reservations match this filter.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="bookings.data.length > 0" class="mt-4 flex items-center justify-between text-sm opacity-70">
            <span>{{ bookings.total }} reservation{{ bookings.total === 1 ? '' : 's' }} — page {{ bookings.current_page }} of {{ bookings.last_page }}</span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="!bookings.prev_page_url"
                    @click="goToPage(bookings.prev_page_url)"
                    class="rounded-md border border-black/10 px-3 py-1.5 hover:bg-black/5 disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    ‹ Prev
                </button>
                <button
                    type="button"
                    :disabled="!bookings.next_page_url"
                    @click="goToPage(bookings.next_page_url)"
                    class="rounded-md border border-black/10 px-3 py-1.5 hover:bg-black/5 disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    Next ›
                </button>
            </div>
        </div>
    </AppLayout>
</template>
