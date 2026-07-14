<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    booking: Object,
});

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
});

function money(cents) {
    return `$${(cents / 100).toFixed(2)}`;
}

const secondsLeft = ref(Math.max(0, Math.floor((new Date(props.booking.expires_at) - new Date()) / 1000)));
let timer = null;

onMounted(() => {
    timer = setInterval(() => {
        secondsLeft.value = Math.max(0, secondsLeft.value - 1);
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const countdown = computed(() => {
    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = secondsLeft.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

function submit() {
    form.post(`/reservations/new/${props.booking.id}/guest`);
}

function cancel() {
    router.delete(`/reservations/new/${props.booking.id}`);
}
</script>

<template>
    <Head title="New Reservation — Guest Details" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">New Reservation — Guest Details</h1>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <button type="button" @click="cancel" class="text-sm text-gold-600 hover:underline">‹ Cancel and pick a different room</button>

                    <h2 class="mt-4 font-serif text-lg">Guest details</h2>
                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">First name</label>
                            <input
                                v-model="form.first_name"
                                type="text"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Last name</label>
                            <input
                                v-model="form.last_name"
                                type="text"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Email</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Phone</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                            <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs uppercase tracking-wide opacity-50">Address</label>
                            <textarea
                                v-model="form.address"
                                rows="2"
                                class="mt-1 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            ></textarea>
                            <p v-if="form.errors.address" class="mt-1 text-sm text-red-600">{{ form.errors.address }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                Create Reservation
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="space-y-3">
                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <p class="text-xs uppercase tracking-wide opacity-50">Room held for</p>
                    <p class="mt-1 font-serif text-2xl" :class="secondsLeft < 60 ? 'text-red-600' : ''">{{ countdown }}</p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="opacity-60">Room</dt>
                            <dd>{{ booking.room.room_type }} — {{ booking.room.number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">Dates</dt>
                            <dd>{{ booking.check_in }} → {{ booking.check_out }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">Nights</dt>
                            <dd>{{ booking.nights }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-black/10 pt-2 font-medium">
                            <dt>Total</dt>
                            <dd>{{ money(booking.total_cents) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">Deposit due (30%)</dt>
                            <dd>{{ money(booking.deposit_cents) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
