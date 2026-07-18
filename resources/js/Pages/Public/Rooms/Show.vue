<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { Check, ImageOff, Users } from '@lucide/vue';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import { useMoney } from '../../../Composables/useMoney.js';

const props = defineProps({
    room: Object,
});

const page = usePage();
const query = new URLSearchParams(window.location.search);

const checkIn = ref(query.get('check_in') ?? '');
const checkOut = ref(query.get('check_out') ?? '');
const locking = ref(false);

const dateError = computed(() => {
    if (!checkIn.value || !checkOut.value) {
        return '';
    }
    return checkOut.value <= checkIn.value ? 'Check-out must be after check-in.' : '';
});

const roomError = computed(() => page.props.errors?.room_id);

const { money } = useMoney();

function bookNow() {
    if (dateError.value || !checkIn.value || !checkOut.value) {
        return;
    }

    locking.value = true;
    router.post(
        '/book/lock',
        {
            room_id: props.room.id,
            check_in: checkIn.value,
            check_out: checkOut.value,
        },
        { onFinish: () => (locking.value = false) },
    );
}
</script>

<template>
    <Head :title="room.title" />
    <PublicLayout>
        <Link href="/rooms" class="text-sm text-gold-600 hover:underline">‹ Back to all rooms</Link>

        <div class="mt-4 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div v-if="room.images.length" class="grid gap-2" :class="room.images.length > 1 ? 'grid-cols-2' : 'grid-cols-1'">
                    <img
                        v-for="image in room.images"
                        :key="image.url"
                        :src="image.url"
                        :alt="room.title"
                        class="h-64 w-full rounded-lg object-cover"
                    />
                </div>
                <div v-else class="flex h-64 flex-col items-center justify-center gap-2 rounded-lg bg-gold-50 text-gold-600/50">
                    <ImageOff class="h-10 w-10" />
                    <span class="text-sm">No photos yet</span>
                </div>

                <h1 class="mt-6 font-serif text-3xl">{{ room.title }}</h1>
                <p class="text-sm opacity-60">{{ room.room_type_name }} — Room {{ room.number }}, Floor {{ room.floor }}</p>

                <p class="mt-2 flex items-center gap-1 text-sm opacity-60">
                    <Users class="h-4 w-4" />
                    Sleeps up to {{ room.max_occupancy }}
                </p>

                <p v-if="room.description" class="mt-4 text-sm leading-relaxed opacity-80">{{ room.description }}</p>

                <div v-if="room.amenities.length" class="mt-6">
                    <p class="text-xs uppercase tracking-wide opacity-50">Amenities</p>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <div v-for="amenity in room.amenities" :key="amenity" class="flex items-center gap-1.5 text-sm">
                            <Check class="h-4 w-4 text-gold-600" />
                            {{ amenity }}
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="sticky top-6 rounded-lg border border-gold-500/20 bg-white p-6">
                    <p class="font-serif text-2xl">{{ money(room.base_rate_cents, room.currency) }}<span class="text-sm font-normal opacity-60"> / night</span></p>

                    <div class="mt-4 space-y-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Check-in</label>
                            <input
                                v-model="checkIn"
                                type="date"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Check-out</label>
                            <input
                                v-model="checkOut"
                                type="date"
                                :min="checkIn || undefined"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                        </div>
                        <p v-if="dateError" class="text-sm text-red-600">{{ dateError }}</p>
                        <p v-if="roomError" class="text-sm text-red-600">{{ roomError }}</p>

                        <button
                            type="button"
                            :disabled="!!dateError || !checkIn || !checkOut || locking"
                            @click="bookNow"
                            class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            {{ locking ? 'Checking…' : 'Book this room' }}
                        </button>
                        <p class="text-center text-xs opacity-50">A 30% deposit secures your reservation.</p>
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
