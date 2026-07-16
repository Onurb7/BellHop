<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import StripeCardForm from '../../../Components/StripeCardForm.vue';

const props = defineProps({
    booking: Object,
    stripe_publishable_key: String,
    confirmation_url: String,
});

function money(cents) {
    return `$${(cents / 100).toFixed(2)}`;
}

const loadingIntent = ref(true);
const intentError = ref('');
const clientSecret = ref('');
const amountDueCents = ref(0);
const confirming = ref(false);

async function prepare() {
    loadingIntent.value = true;
    intentError.value = '';

    try {
        const response = await axios.post(`/book/${props.booking.id}/stripe/intent`);
        clientSecret.value = response.data.client_secret;
        amountDueCents.value = response.data.amount_cents;
    } catch (err) {
        intentError.value = err.response?.data?.message ?? 'Could not start payment — please try again.';
    } finally {
        loadingIntent.value = false;
    }
}

prepare();

function onPaymentSucceeded() {
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
                <h1 class="font-serif text-2xl">Pay your deposit</h1>
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

                <div class="mt-6 border-t border-black/5 pt-6">
                    <p v-if="confirming" class="text-sm opacity-60">Payment received — setting up your account…</p>

                    <template v-else>
                        <p v-if="loadingIntent" class="text-sm opacity-60">Preparing payment…</p>
                        <p v-else-if="intentError" class="text-sm text-red-600">{{ intentError }}</p>
                        <template v-else>
                            <p class="mb-3 text-sm">Deposit due now: <strong>{{ money(amountDueCents) }}</strong></p>
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
