<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import axios from 'axios';
import { FileText } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import StripeCardForm from '../../Components/StripeCardForm.vue';
import { useDateFormat } from '../../Composables/useDateFormat.js';

const props = defineProps({
    booking: Object,
    stripe_publishable_key: String,
});

const { formatDate, formatDateTime } = useDateFormat();

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

const chargeCategoryLabels = {
    room: 'Room charge',
    date_change: 'Date change',
    room_change: 'Room change',
    refund: 'Refund',
};

const paymentKindLabels = {
    deposit: 'Deposit',
    balance: 'Balance',
    additional: 'Additional payment',
    refund: 'Refund',
};

function money(cents) {
    const negative = cents < 0;
    const amount = `$${(Math.abs(cents) / 100).toFixed(2)}`;
    return negative ? `-${amount}` : amount;
}

const payPanelOpen = ref(false);
const loadingIntent = ref(false);
const intentError = ref('');
const clientSecret = ref('');
const amountDueCents = ref(0);
const confirming = ref(false);

async function openPayPanel() {
    payPanelOpen.value = true;
    loadingIntent.value = true;
    intentError.value = '';

    try {
        const response = await axios.post(`/my-reservations/${props.booking.id}/stripe/intent`);
        clientSecret.value = response.data.client_secret;
        amountDueCents.value = response.data.amount_cents;
    } catch (err) {
        intentError.value = err.response?.data?.message ?? 'Could not start payment — please try again.';
    } finally {
        loadingIntent.value = false;
    }
}

function onPaymentSucceeded() {
    confirming.value = true;
    payPanelOpen.value = false;

    setTimeout(() => {
        router.reload({ onFinish: () => (confirming.value = false) });
    }, 2000);
}
</script>

<template>
    <Head title="My Reservation" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">My Reservation</h1>
        </template>

        <div class="max-w-2xl space-y-6">
            <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-lg">Stay details</h2>
                    <span class="rounded-full px-2 py-0.5 text-xs" :class="statusBadgeClass[booking.status]">
                        {{ statusLabels[booking.status] ?? booking.status }}
                    </span>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-xs uppercase tracking-wide opacity-50">Room</dt>
                        <dd class="mt-1">{{ booking.room.room_type }} — {{ booking.room.number }} (Floor {{ booking.room.floor }})</dd>
                    </div>
                    <div>
                        <dt class="text-xs uppercase tracking-wide opacity-50">Dates</dt>
                        <dd class="mt-1">{{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}</dd>
                    </div>
                </dl>

                <a
                    v-if="booking.invoice_generated_at"
                    :href="`/invoices/${booking.id}`"
                    class="mt-4 inline-flex items-center gap-1.5 text-sm text-gold-700 hover:underline"
                >
                    <FileText class="h-4 w-4" />
                    Download Invoice
                </a>
            </div>

            <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                <h2 class="font-serif text-lg">Charges</h2>
                <table class="mt-3 w-full text-left text-sm">
                    <thead class="border-b border-black/10 text-xs uppercase tracking-wide opacity-50">
                        <tr>
                            <th class="py-2">Description</th>
                            <th class="py-2">Category</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="charge in booking.charges" :key="charge.id" class="border-b border-black/5 last:border-0">
                            <td class="py-2">{{ charge.description }}</td>
                            <td class="py-2 opacity-60">{{ chargeCategoryLabels[charge.category] ?? charge.category }}</td>
                            <td class="py-2 text-right" :class="charge.amount_cents < 0 ? 'text-red-600' : ''">{{ money(charge.amount_cents) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-black/10 font-medium">
                            <td class="py-2" colspan="2">Total</td>
                            <td class="py-2 text-right">{{ money(booking.total_cents) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                <h2 class="font-serif text-lg">Payments</h2>
                <table class="mt-3 w-full text-left text-sm">
                    <thead class="border-b border-black/10 text-xs uppercase tracking-wide opacity-50">
                        <tr>
                            <th class="py-2">Kind</th>
                            <th class="py-2">Date</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in booking.payments" :key="payment.id" class="border-b border-black/5 last:border-0">
                            <td class="py-2">{{ paymentKindLabels[payment.kind] ?? payment.kind }}</td>
                            <td class="py-2 opacity-60">{{ formatDateTime(payment.verified_at) }}</td>
                            <td class="py-2 text-right" :class="payment.amount_cents < 0 ? 'text-red-600' : ''">{{ money(payment.amount_cents) }}</td>
                        </tr>
                        <tr v-if="booking.payments.length === 0">
                            <td colspan="3" class="py-4 text-center opacity-50">No payments recorded yet.</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-black/10 font-medium">
                            <td class="py-2" colspan="2">Balance due</td>
                            <td class="py-2 text-right" :class="booking.balance_due_cents > 0 ? 'text-red-600' : ''">
                                {{ money(booking.balance_due_cents) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="booking.balance_due_cents > 0 && booking.payable" class="mt-5 border-t border-black/5 pt-5">
                    <button
                        v-if="!payPanelOpen && !confirming"
                        type="button"
                        @click="openPayPanel"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white"
                    >
                        Pay Now
                    </button>

                    <p v-if="confirming" class="text-sm opacity-60">Confirming your payment…</p>

                    <div v-if="payPanelOpen" class="max-w-sm space-y-3">
                        <p v-if="loadingIntent" class="text-sm opacity-60">Preparing payment…</p>
                        <p v-else-if="intentError" class="text-sm text-red-600">{{ intentError }}</p>
                        <template v-else>
                            <p class="text-sm">Amount due now: <strong>{{ money(amountDueCents) }}</strong></p>
                            <StripeCardForm
                                :publishable-key="stripe_publishable_key"
                                :client-secret="clientSecret"
                                @succeeded="onPaymentSucceeded"
                            />
                        </template>
                        <button type="button" @click="payPanelOpen = false" class="text-sm opacity-60 hover:underline">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
