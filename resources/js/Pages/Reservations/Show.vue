<script setup>
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import axios from 'axios';
import { FileText } from '@lucide/vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import ConfirmTypedDialog from '../../Components/ConfirmTypedDialog.vue';
import { useDateFormat } from '../../Composables/useDateFormat.js';

const props = defineProps({
    booking: Object,
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

const canCancel = computed(() => ['pending_payment', 'confirmed'].includes(props.booking.status));
const canCheckIn = computed(() => props.booking.status === 'confirmed');
const canCheckOut = computed(() => props.booking.status === 'checked_in');
const canChangeDates = computed(() => !props.booking.balance_paid);

function checkIn() {
    router.post(`/reservations/${props.booking.id}/check-in`, {}, { preserveScroll: true });
}

function checkOut() {
    router.post(`/reservations/${props.booking.id}/check-out`, {}, { preserveScroll: true });
}

const verifyPaymentLabel = computed(() => {
    if (props.booking.status === 'pending_payment') {
        return `Verify Deposit Payment (${money(props.booking.deposit_cents ?? 0)})`;
    }
    if (props.booking.balance_due_cents > 0) {
        return `Verify Balance Payment (${money(props.booking.balance_due_cents)})`;
    }
    return 'Verify Payment';
});

function verifyPayment() {
    router.post(`/reservations/${props.booking.id}/verify-payment`, {}, { preserveScroll: true });
}

function sendReservationReminder() {
    router.post(`/reservations/${props.booking.id}/remind/reservation`, {}, { preserveScroll: true });
}

function sendPaymentReminder() {
    router.post(`/reservations/${props.booking.id}/remind/payment`, {}, { preserveScroll: true });
}

const showCancelDialog = ref(false);

function cancelReservation() {
    router.post(
        `/reservations/${props.booking.id}/cancel`,
        { confirmation: 'cancel' },
        { onFinish: () => (showCancelDialog.value = false) },
    );
}

const refundingPayment = ref(null);

function refundPayment(payment) {
    router.post(
        `/reservations/${props.booking.id}/payments/${payment.id}/refund`,
        {},
        { preserveScroll: true, onFinish: () => (refundingPayment.value = null) },
    );
}

// Date/room change panel
const newCheckIn = ref(props.booking.check_in);
const newCheckOut = ref(props.booking.check_out);
const checking = ref(false);
const applying = ref(false);
const previewError = ref('');
const preview = ref(null);

async function checkAvailability() {
    checking.value = true;
    previewError.value = '';
    preview.value = null;

    try {
        const response = await axios.post(`/reservations/${props.booking.id}/date-change/preview`, {
            check_in: newCheckIn.value,
            check_out: newCheckOut.value,
        });
        preview.value = response.data;
    } catch (err) {
        previewError.value = err.response?.data?.message ?? 'Could not check availability.';
    } finally {
        checking.value = false;
    }
}

function applyOption(option) {
    applying.value = true;
    router.post(
        `/reservations/${props.booking.id}/date-change/apply`,
        {
            room_id: option.room_id,
            check_in: newCheckIn.value,
            check_out: newCheckOut.value,
        },
        { onFinish: () => (applying.value = false) },
    );
}
</script>

<template>
    <Head title="Reservation" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Reservation — {{ booking.guest.name }}</h1>
        </template>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
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
                        <div>
                            <dt class="text-xs uppercase tracking-wide opacity-50">Guest</dt>
                            <dd class="mt-1">{{ booking.guest.name }} — {{ booking.guest.email }}<span v-if="booking.guest.phone"> — {{ booking.guest.phone }}</span></dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide opacity-50">Last reminder</dt>
                            <dd class="mt-1">
                                <span v-if="booking.last_reminder_sent_at">{{ booking.last_reminder_type }} — {{ booking.last_reminder_sent_at }}</span>
                                <span v-else class="opacity-50">None sent yet</span>
                            </dd>
                        </div>
                    </dl>
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
                    <div class="flex items-center justify-between">
                        <h2 class="font-serif text-lg">Payments</h2>
                        <a
                            v-if="booking.invoice_generated_at"
                            :href="`/invoices/${booking.id}`"
                            class="inline-flex items-center gap-1.5 text-sm text-gold-700 hover:underline"
                        >
                            <FileText class="h-4 w-4" />
                            Download Invoice
                        </a>
                    </div>
                    <table class="mt-3 w-full text-left text-sm">
                        <thead class="border-b border-black/10 text-xs uppercase tracking-wide opacity-50">
                            <tr>
                                <th class="py-2">Kind</th>
                                <th class="py-2">Verified</th>
                                <th class="py-2 text-right">Amount</th>
                                <th class="py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="payment in booking.payments" :key="payment.id" class="border-b border-black/5 last:border-0">
                                <td class="py-2">{{ paymentKindLabels[payment.kind] ?? payment.kind }}</td>
                                <td class="py-2 opacity-60">{{ formatDateTime(payment.verified_at) }}</td>
                                <td class="py-2 text-right" :class="payment.amount_cents < 0 ? 'text-red-600' : ''">{{ money(payment.amount_cents) }}</td>
                                <td class="py-2 text-right">
                                    <button
                                        v-if="payment.refundable"
                                        type="button"
                                        @click="refundingPayment = payment"
                                        class="text-xs text-red-600 hover:underline"
                                    >
                                        Refund
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="booking.payments.length === 0">
                                <td colspan="4" class="py-4 text-center opacity-50">No payments recorded yet.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t border-black/10 font-medium">
                                <td class="py-2" colspan="2">Balance due</td>
                                <td class="py-2 text-right" :class="booking.balance_due_cents > 0 ? 'text-red-600' : ''">
                                    {{ money(booking.balance_due_cents) }}
                                </td>
                                <td class="py-2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <h2 class="font-serif text-lg">Change dates / room</h2>
                    <p v-if="!canChangeDates" class="mt-3 text-sm opacity-60">
                        The balance has already been charged for this reservation — dates and room
                        can no longer be changed.
                    </p>
                    <div v-else class="mt-3 flex flex-wrap items-end gap-3">
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Check-in</label>
                            <input
                                v-model="newCheckIn"
                                type="date"
                                class="mt-1 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wide opacity-50">Check-out</label>
                            <input
                                v-model="newCheckOut"
                                type="date"
                                class="mt-1 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                            />
                        </div>
                        <button
                            type="button"
                            :disabled="checking"
                            @click="checkAvailability"
                            class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                        >
                            {{ checking ? 'Checking…' : 'Check Availability' }}
                        </button>
                    </div>

                    <p v-if="previewError" class="mt-3 text-sm text-red-600">{{ previewError }}</p>

                    <div v-if="preview" class="mt-4 space-y-2">
                        <div
                            class="flex items-center justify-between rounded-md border px-4 py-3 text-sm"
                            :class="preview.current_room_option.available && !preview.current_room_option.blocked ? 'border-gold-500/30 bg-gold-50' : 'border-black/10 bg-black/[0.02] opacity-60'"
                        >
                            <div>
                                <p class="font-medium">
                                    Keep current room — {{ preview.current_room_option.room_type_name }} {{ preview.current_room_option.room_number }}
                                </p>
                                <p class="text-xs opacity-60">
                                    <span v-if="!preview.current_room_option.available">Not available for these dates.</span>
                                    <span v-else-if="preview.current_room_option.blocked">Would drop total below amount already paid.</span>
                                    <span v-else>New total {{ money(preview.current_room_option.total_cents) }} ({{ preview.current_room_option.delta_cents >= 0 ? '+' : '' }}{{ money(preview.current_room_option.delta_cents) }})</span>
                                </p>
                            </div>
                            <button
                                v-if="preview.current_room_option.available && !preview.current_room_option.blocked"
                                type="button"
                                :disabled="applying"
                                @click="applyOption(preview.current_room_option)"
                                class="rounded-md border border-gold-500/30 px-3 py-1.5 text-sm hover:bg-gold-500/10"
                            >
                                Apply
                            </button>
                        </div>

                        <template v-if="!preview.current_room_option.available">
                            <p class="pt-2 text-xs uppercase tracking-wide opacity-50">Suggested alternates</p>
                            <div
                                v-for="option in preview.alternate_rooms"
                                :key="option.room_id"
                                class="flex items-center justify-between rounded-md border px-4 py-3 text-sm"
                                :class="option.blocked ? 'border-black/10 bg-black/[0.02] opacity-60' : 'border-gold-500/30 bg-gold-50'"
                            >
                                <div>
                                    <p class="font-medium">{{ option.room_type_name }} — {{ option.room_number }}</p>
                                    <p class="text-xs opacity-60">
                                        <span v-if="option.blocked">Would drop total below amount already paid.</span>
                                        <span v-else>New total {{ money(option.total_cents) }} ({{ option.delta_cents >= 0 ? '+' : '' }}{{ money(option.delta_cents) }})</span>
                                    </p>
                                </div>
                                <button
                                    v-if="!option.blocked"
                                    type="button"
                                    :disabled="applying"
                                    @click="applyOption(option)"
                                    class="rounded-md border border-gold-500/30 px-3 py-1.5 text-sm hover:bg-gold-500/10"
                                >
                                    Apply
                                </button>
                            </div>
                            <p v-if="preview.alternate_rooms.length === 0" class="text-sm opacity-50">
                                No rooms of any type are available for those dates.
                            </p>
                        </template>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                    <h2 class="font-serif text-lg">Actions</h2>
                    <div class="mt-4 flex flex-col gap-2">
                        <button
                            type="button"
                            @click="verifyPayment"
                            class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
                        >
                            {{ verifyPaymentLabel }}
                        </button>
                        <button
                            type="button"
                            @click="sendReservationReminder"
                            class="rounded-md border border-black/10 px-4 py-2 text-sm hover:bg-black/5"
                        >
                            Send Reservation Reminder
                        </button>
                        <button
                            v-if="booking.balance_due_cents > 0"
                            type="button"
                            @click="sendPaymentReminder"
                            class="rounded-md border border-black/10 px-4 py-2 text-sm hover:bg-black/5"
                        >
                            Send Payment Reminder
                        </button>
                        <button
                            v-if="canCheckIn"
                            type="button"
                            @click="checkIn"
                            class="rounded-md border border-black/10 px-4 py-2 text-sm hover:bg-black/5"
                        >
                            Check In
                        </button>
                        <button
                            v-if="canCheckOut"
                            type="button"
                            @click="checkOut"
                            class="rounded-md border border-black/10 px-4 py-2 text-sm hover:bg-black/5"
                        >
                            Check Out
                        </button>
                        <button
                            v-if="canCancel"
                            type="button"
                            @click="showCancelDialog = true"
                            class="rounded-md border border-red-200 px-4 py-2 text-sm text-red-600 hover:bg-red-50"
                        >
                            Cancel Reservation
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmTypedDialog
            :open="showCancelDialog"
            title="Cancel this reservation?"
            message="This can't be undone. The guest will lose this room for these dates."
            confirm-word="cancel"
            @confirm="cancelReservation"
            @close="showCancelDialog = false"
        />

        <ConfirmTypedDialog
            :open="refundingPayment !== null"
            title="Refund this payment?"
            message="This issues a real refund via Stripe back to the guest's card."
            confirm-word="refund"
            @confirm="refundPayment(refundingPayment)"
            @close="refundingPayment = null"
        />
    </AppLayout>
</template>
