<script setup>
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import axios from 'axios';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import ServiceSelectCards from '../../../Components/ServiceSelectCards.vue';
import { useDateFormat } from '../../../Composables/useDateFormat.js';
import { useMoney, convertCents } from '../../../Composables/useMoney.js';

const props = defineProps({
    booking: Object,
    services: Array,
    guestAccount: {
        type: Object,
        default: null,
    },
});

const { formatDate } = useDateFormat();
const { money, rates } = useMoney();

const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    address: '',
    services: [],
    promo_code: '',
});

const appliedPromoCode = ref(null);
const promoCodeError = ref('');
const applyingPromoCode = ref(false);

async function applyPromoCode() {
    if (!form.promo_code.trim()) {
        return;
    }

    applyingPromoCode.value = true;
    promoCodeError.value = '';

    try {
        const response = await axios.post(`/book/${props.booking.id}/promo-code/preview`, {
            code: form.promo_code,
            services: form.services,
        });
        appliedPromoCode.value = response.data;
    } catch (err) {
        appliedPromoCode.value = null;
        promoCodeError.value = err.response?.data?.message ?? 'Could not check that code — please try again.';
    } finally {
        applyingPromoCode.value = false;
    }
}

// Keeps a scoped code's applied state from going stale if the guest
// unchecks the service it applies to after Apply already succeeded —
// an empty service_ids means unscoped (off the room charge), which no
// service selection change affects.
watch(
    () => form.services.slice(),
    () => {
        if (!appliedPromoCode.value || appliedPromoCode.value.service_ids.length === 0) {
            return;
        }

        const stillSelected = appliedPromoCode.value.service_ids.some((id) => form.services.includes(id));

        if (!stillSelected) {
            appliedPromoCode.value = null;
            promoCodeError.value = 'Your promo code no longer applies — re-select its service or apply a different code.';
        }
    },
);

// The room charge is still in the room type's own currency at this point
// (conversion to USD only happens once storeGuest() creates the real
// charge row) and each service carries its own currency too — pivot
// everything through USD before summing so mixed currencies add up
// correctly, then let money() convert the USD sum to the viewer's
// preferred display currency.
const totalCents = computed(() => {
    const roomUsdCents = convertCents(props.booking.total_cents, props.booking.currency, 'USD', rates.value);

    const servicesUsdCents = props.services
        .filter((service) => form.services.includes(service.id))
        .reduce((sum, service) => {
            const cents = service.pricing_type === 'per_night'
                ? service.unit_price_cents * props.booking.nights
                : service.unit_price_cents;

            return sum + convertCents(cents, service.currency, 'USD', rates.value);
        }, 0);

    const discountUsdCents = appliedPromoCode.value?.discount_cents ?? 0;

    return roomUsdCents + servicesUsdCents - discountUsdCents;
});

const secondsLeft = ref(Math.max(0, Math.floor((new Date(props.booking.expires_at) - new Date()) / 1000)));
let timer = null;

onMounted(() => {
    // The hold has already expired server-side too (see
    // BookingController::lock()'s 15-minute window) — abandon it and send
    // the guest back to search rather than leaving them stuck on a page
    // where every action would just fail against an expired booking.
    if (secondsLeft.value <= 0) {
        cancel();
        return;
    }

    timer = setInterval(() => {
        secondsLeft.value = Math.max(0, secondsLeft.value - 1);

        if (secondsLeft.value <= 0) {
            clearInterval(timer);
            cancel();
        }
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const countdown = computed(() => {
    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = secondsLeft.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

function submit() {
    form.post(`/book/${props.booking.id}/guest`);
}

function cancel() {
    router.delete(`/book/${props.booking.id}`);
}
</script>

<template>
    <Head title="Your Details" />
    <PublicLayout>
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <button type="button" @click="cancel" class="text-sm text-gold-600 hover:underline">‹ Pick a different room</button>

                    <template v-if="guestAccount">
                        <h1 class="mt-4 font-serif text-2xl">Almost there — review your stay</h1>
                        <p class="mt-1 text-sm opacity-60">
                            Booking as
                            <span class="font-medium text-[#1b1b18]">{{ guestAccount.first_name }} {{ guestAccount.last_name }}</span>
                            — {{ guestAccount.email }}
                        </p>
                    </template>
                    <template v-else>
                        <h1 class="mt-4 font-serif text-2xl">Almost there — your details</h1>
                        <p class="mt-1 text-sm opacity-60">We'll email you a link to set up your account once your deposit is paid.</p>
                    </template>

                    <form class="mt-4 grid gap-4 sm:grid-cols-2" @submit.prevent="submit">
                        <template v-if="!guestAccount">
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
                        </template>

                        <div v-if="services.length" class="sm:col-span-2">
                            <label class="block text-xs uppercase tracking-wide opacity-50">Add for your whole stay (optional)</label>
                            <div class="mt-2">
                                <ServiceSelectCards v-model="form.services" :services="services" :nights="booking.nights" />
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label class="block text-xs uppercase tracking-wide opacity-50">Promo code (optional)</label>
                            <div class="mt-1 flex gap-2">
                                <input
                                    v-model="form.promo_code"
                                    type="text"
                                    placeholder="e.g. SUMMER10"
                                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm uppercase focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                                    @keydown.enter.prevent="applyPromoCode"
                                />
                                <button
                                    type="button"
                                    :disabled="applyingPromoCode || !form.promo_code.trim()"
                                    @click="applyPromoCode"
                                    class="shrink-0 rounded-md border border-gold-500/30 px-4 py-2 text-sm font-medium text-gold-700 hover:bg-gold-500/10 disabled:opacity-50"
                                >
                                    {{ applyingPromoCode ? 'Checking…' : 'Apply' }}
                                </button>
                            </div>
                            <p v-if="appliedPromoCode" class="mt-2 text-sm text-emerald-700">
                                Code applied — {{ appliedPromoCode.description || `${appliedPromoCode.percentage}% off` }}
                            </p>
                            <p v-else-if="promoCodeError" class="mt-2 text-sm text-red-600">{{ promoCodeError }}</p>
                        </div>

                        <div class="sm:col-span-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                            >
                                Continue to Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="space-y-3">
                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <p class="text-xs uppercase tracking-wide opacity-50">Room held for</p>
                    <p class="mt-1 font-serif text-2xl" :class="secondsLeft < 60 ? 'text-red-600' : ''">{{ countdown }}</p>
                    <p class="mt-1 text-xs opacity-50">
                        We hold this room for 15 minutes so you have time to complete your details and payment.
                        If the timer runs out, you'll need to search again.
                    </p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="opacity-60">Room</dt>
                            <dd>{{ booking.room.room_type }} — {{ booking.room.number }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">Dates</dt>
                            <dd>{{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">Nights</dt>
                            <dd>{{ booking.nights }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-black/10 pt-2 font-medium">
                            <dt>Total</dt>
                            <dd>{{ money(totalCents, 'USD') }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="opacity-60">{{ booking.is_deposit_plan ? 'Deposit due (30% of room)' : 'Due now (full payment)' }}</dt>
                            <dd>{{ money(booking.deposit_cents, booking.currency) }}</dd>
                        </div>
                        <p v-if="form.services.length" class="text-xs opacity-50">
                            Selected services are billed post checkout and are not part of today's payment.
                        </p>
                        <p class="text-xs opacity-50">
                            Reservations made within 3 days of check-in are required to be paid in full.
                        </p>
                    </dl>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
