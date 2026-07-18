<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { BedDouble, ImageOff, Users } from '@lucide/vue';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import { useMoney } from '../../../Composables/useMoney.js';
import { todayDateString } from '../../../Composables/useDateFormat.js';

const { money } = useMoney();

const props = defineProps({
    check_in: String,
    check_out: String,
    guests: Number,
    rooms: Array,
});

const checkIn = ref(props.check_in ?? '');
const checkOut = ref(props.check_out ?? '');
const guestCount = ref(props.guests ?? '');
const todayString = todayDateString();

const dateError = computed(() => {
    if (checkIn.value && checkIn.value < todayString) {
        return 'Check-in can\'t be in the past.';
    }
    if (!checkIn.value || !checkOut.value) {
        return '';
    }
    return checkOut.value <= checkIn.value ? 'Check-out must be after check-in.' : '';
});

const searched = computed(() => props.check_in && props.check_out);

function search() {
    if (dateError.value) {
        return;
    }

    router.get(
        '/rooms',
        {
            check_in: checkIn.value || undefined,
            check_out: checkOut.value || undefined,
            guests: guestCount.value || undefined,
        },
        { preserveState: true },
    );
}

</script>

<template>
    <Head title="Rooms" />
    <PublicLayout>
        <div class="mb-8 text-center">
            <p class="text-xs uppercase tracking-[0.35em] text-gold-600">Est. Boutique Hospitality</p>
            <h1 class="mt-3 font-serif text-4xl">Find your stay</h1>
        </div>

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
                    Check Availability
                </button>
            </div>
            <p v-if="dateError" class="mt-2 text-sm text-red-600">{{ dateError }}</p>
            <p v-if="!searched" class="mt-2 text-xs opacity-50">Showing every room — pick dates to see what's actually available.</p>
        </div>

        <div class="mt-8">
            <div v-if="rooms.length > 0" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="room in rooms"
                    :key="room.id"
                    :href="`/rooms/${room.id}${searched ? `?check_in=${check_in}&check_out=${check_out}` : ''}`"
                    class="flex flex-col overflow-hidden rounded-lg border border-gold-500/20 bg-white transition hover:border-gold-500/40 hover:shadow-sm"
                >
                    <div class="flex h-40 items-center justify-center bg-gold-50">
                        <img v-if="room.thumb_url" :src="room.thumb_url" :alt="room.title" class="h-full w-full object-cover" />
                        <div v-else class="flex flex-col items-center gap-1 text-gold-600/50">
                            <ImageOff class="h-8 w-8" />
                            <span class="text-xs">No photo yet</span>
                        </div>
                    </div>
                    <div class="flex flex-1 flex-col p-5">
                        <p class="font-serif text-lg">{{ room.title }}</p>
                        <p class="text-sm opacity-60">{{ room.room_type_name }} — Room {{ room.number }}</p>
                        <p class="mt-2 flex items-center gap-1 text-xs opacity-50">
                            <Users class="h-3.5 w-3.5" />
                            Sleeps up to {{ room.max_occupancy }}
                        </p>
                        <div v-if="room.amenities.length" class="mt-3 flex flex-wrap gap-1.5">
                            <span
                                v-for="amenity in room.amenities.slice(0, 4)"
                                :key="amenity"
                                class="rounded-full bg-gold-500/10 px-2 py-0.5 text-xs text-gold-700"
                            >
                                {{ amenity }}
                            </span>
                        </div>
                        <p class="mt-4 text-sm font-medium">{{ money(room.base_rate_cents, room.currency) }} / night</p>
                    </div>
                </Link>
            </div>
            <div v-else class="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-black/10 bg-white p-12 text-center">
                <BedDouble class="h-8 w-8 opacity-30" />
                <p class="text-sm opacity-50">No rooms available for those dates.</p>
            </div>
        </div>
    </PublicLayout>
</template>
