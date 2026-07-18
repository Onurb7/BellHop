<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useMoney } from '../../../Composables/useMoney.js';
import { todayDateString } from '../../../Composables/useDateFormat.js';

const props = defineProps({
    check_in: String,
    check_out: String,
    guests: Number,
    nights: Number,
    rooms: Array,
});

const checkIn = ref(props.check_in ?? '');
const checkOut = ref(props.check_out ?? '');
const guestCount = ref(props.guests ?? '');
const locking = ref(null);
const todayString = todayDateString();

const { money } = useMoney();

const dateError = computed(() => {
    if (checkIn.value && checkIn.value < todayString) {
        return 'Check-in can\'t be in the past.';
    }
    if (!checkIn.value || !checkOut.value) {
        return '';
    }
    return checkOut.value <= checkIn.value ? 'Check-out must be after check-in.' : '';
});

function search() {
    if (dateError.value) {
        return;
    }

    router.get(
        '/reservations/new',
        {
            check_in: checkIn.value || undefined,
            check_out: checkOut.value || undefined,
            guests: guestCount.value || undefined,
        },
        { preserveState: true },
    );
}

function selectRoom(room) {
    locking.value = room.room_id;
    router.post(
        '/reservations/new/lock',
        {
            room_id: room.room_id,
            check_in: checkIn.value,
            check_out: checkOut.value,
        },
        { onFinish: () => (locking.value = null) },
    );
}

const searched = computed(() => props.check_in && props.check_out);
</script>

<template>
    <Head title="New Reservation" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">New Reservation</h1>
        </template>

        <div class="rounded-lg border border-gold-500/20 bg-white p-6">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="block text-xs uppercase tracking-wide opacity-50">Check-in</label>
                    <input
                        v-model="checkIn"
                        type="date"
                        :min="todayString"
                        class="mt-1 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wide opacity-50">Check-out</label>
                    <input
                        v-model="checkOut"
                        type="date"
                        :min="checkIn || todayString"
                        class="mt-1 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                </div>
                <div>
                    <label class="block text-xs uppercase tracking-wide opacity-50">Guests</label>
                    <input
                        v-model="guestCount"
                        type="number"
                        min="1"
                        placeholder="Any"
                        class="mt-1 w-24 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                </div>
                <button
                    type="button"
                    :disabled="!!dateError"
                    @click="search"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Search Availability
                </button>
            </div>
            <p v-if="dateError" class="mt-2 text-sm text-red-600">{{ dateError }}</p>
        </div>

        <div v-if="searched" class="mt-6">
            <p class="mb-3 text-sm opacity-60">{{ nights }} night(s) — {{ check_in }} → {{ check_out }}</p>

            <div v-if="rooms.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="room in rooms"
                    :key="room.room_id"
                    class="flex flex-col justify-between rounded-lg border border-gold-500/20 bg-white p-5"
                >
                    <div>
                        <p class="font-serif text-lg">{{ room.room_type_name }}</p>
                        <p class="text-sm opacity-60">Room {{ room.room_number }} — Floor {{ room.floor }}</p>
                        <p class="mt-2 text-xs opacity-50">Sleeps up to {{ room.max_occupancy }}</p>
                        <p class="mt-3 text-sm">
                            {{ money(room.nightly_rate_cents, room.currency) }} / night
                            <span class="block font-medium">{{ money(room.total_cents, room.currency) }} total</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="locking === room.room_id"
                        @click="selectRoom(room)"
                        class="mt-4 rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        {{ locking === room.room_id ? 'Locking…' : 'Select this room' }}
                    </button>
                </div>
            </div>
            <p v-else class="rounded-lg border border-gold-500/20 bg-white px-4 py-8 text-center text-sm opacity-50">
                No rooms available for those dates.
            </p>
        </div>
    </AppLayout>
</template>
