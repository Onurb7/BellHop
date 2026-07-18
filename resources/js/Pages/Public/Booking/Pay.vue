<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import StripeCardForm from '../../../Components/StripeCardForm.vue';
import { useMoney } from '../../../Composables/useMoney.js';

const props = defineProps({
    booking: Object,
    stripe_publishable_key: String,
    confirmation_url: String,
});

const { money } = useMoney();

const loadingIntent = ref(true);
const intentError = ref('');
const clientSecret = ref('');
const amountDueCents = ref(0);
const confirming = ref(false);

// Only a still-pending_payment booking with an expires_at is actually
// racing a hold — a booking revisited after being confirmed (e.g. paid
// in another tab) must never show a countdown or get cancelled out from
// under the guest.
const isHeld = props.booking.status === 'pending_payment' && !!props.booking.expires_at;
const secondsLeft = ref(isHeld ? Math.max(0, Math.floor((new Date(props.booking.expires_at) - new Date()) / 1000)) : null);
let timer = null;

function abandon() {
    router.delete(`/book/${props.booking.id}`);
}

onMounted(() => {
    if (!isHeld) {
        return;
    }

    if (secondsLeft.value <= 0) {
        abandon();
        return;
    }

    timer = setInterval(() => {
        secondsLeft.value = Math.max(0, secondsLeft.value - 1);

        if (secondsLeft.value <= 0) {
            clearInterval(timer);
            abandon();
        }
    }, 1000);
});

onBeforeUnmount(() => clearInterval(timer));

const countdown = computed(() => {
    if (secondsLeft.value === null) {
        return null;
    }

    const minutes = Math.floor(secondsLeft.value / 60);
    const seconds = secondsLeft.value % 60;
    return `${minutes}:${String(seconds).padStart(2, '0')}`;
});

// Unchecked by default — the guest has to actively opt in before we ever
// ask Stripe to save anything. Only relevant for a genuine deposit-plan
// booking, so the payment step waits for this decision instead of firing
// immediately on page load like the full-payment case does.
const saveCard = ref(false);
const readyForPayment = ref(!props.booking.is_deposit_plan);

async function prepare() {
    loadingIntent.value = true;
    intentError.value = '';

    try {
        const response = await axios.post(`/book/${props.booking.id}/stripe/intent`, {
            save_card: saveCard.value,
        });
        clientSecret.value = response.data.client_secret;
        amountDueCents.value = response.data.amount_cents;
    } catch (err) {
        intentError.value = err.response?.data?.message ?? 'Could not start payment — please try again.';
    } finally {
        loadingIntent.value = false;
    }
}

function continueToPayment() {
    readyForPayment.value = true;
    prepare();
}

if (readyForPayment.value) {
    prepare();
}

function onPaymentSucceeded() {
    clearInterval(timer);
    confirming.value = true;

    setTimeout(() => {
        router.visit(props.confirmation_url);
    }, 2000);
}
</script>

<template>
    <Head title="Payment" />
    <PublicLayout>
        <div class="mx-auto max-w-md">
            <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                <h1 class="font-serif text-2xl">{{ booking.is_deposit_plan ? 'Pay your deposit' : 'Pay for your stay' }}</h1>

                <div v-if="countdown" class="mt-3 flex items-center justify-between rounded-md bg-gold-500/10 px-3 py-2 text-sm">
                    <span>
                        Room held for <strong :class="secondsLeft < 60 ? 'text-red-600' : ''">{{ countdown }}</strong>
                    </span>
                    <button type="button" @click="abandon" class="text-xs text-gold-700 hover:underline">
                        Cancel &amp; pick a different room
                    </button>
                </div>
                <p v-if="countdown" class="mt-1 text-xs opacity-50">
                    If the timer runs out before payment completes, this hold is released and you'll need to search again.
                </p>

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="opacity-60">Room</dt>
                        <dd>{{ booking.room.room_type }} — {{ booking.room.number }}</dd>
                    </div>
                    <div class="flex justify-between border-t border-black/10 pt-2 font-medium">
                        <dt>Total</dt>
                        <dd>{{ money(booking.total_cents) }}</dd>
                    </div>
                </dl>

                <div v-if="booking.is_deposit_plan && !readyForPayment" class="mt-4 space-y-3">
                    <label class="flex items-start gap-2 rounded-md bg-gold-500/10 p-3 text-xs leading-relaxed">
                        <input v-model="saveCard" type="checkbox" class="mt-0.5 accent-gold-500" />
                        <span>
                            Save my card so the remaining {{ money(booking.balance_due_cents) }} is
                            automatically charged on {{ booking.balance_auto_charge_date }} — no
                            further charges until then. Your card details are never stored by us,
                            only by Stripe.
                        </span>
                    </label>
                    <p v-if="!saveCard" class="text-xs opacity-60">
                        Without this, you'll need to pay the remaining balance yourself before
                        {{ booking.balance_auto_charge_date }} — we'll email you a reminder.
                    </p>
                    <button
                        type="button"
                        @click="continueToPayment"
                        class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        Continue to Payment
                    </button>
                </div>

                <div v-else class="mt-6 border-t border-black/5 pt-6">
                    <p v-if="confirming" class="text-sm opacity-60">Payment received — setting up your account…</p>

                    <template v-else>
                        <p v-if="loadingIntent" class="text-sm opacity-60">Preparing payment…</p>
                        <p v-else-if="intentError" class="text-sm text-red-600">{{ intentError }}</p>
                        <template v-else>
                            <p class="mb-3 text-sm">{{ booking.is_deposit_plan ? 'Deposit due now' : 'Total due now' }}: <strong>{{ money(amountDueCents) }}</strong></p>
                            <StripeCardForm
                                :publishable-key="stripe_publishable_key"
                                :client-secret="clientSecret"
                                @succeeded="onPaymentSucceeded"
                            />
                        </template>
                    </template>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
