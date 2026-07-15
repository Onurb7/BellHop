<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { loadStripe } from '@stripe/stripe-js';

const props = defineProps({
    publishableKey: { type: String, required: true },
    clientSecret: { type: String, required: true },
});

const emit = defineEmits(['succeeded', 'error']);

const cardElementRef = ref(null);
const submitting = ref(false);
const errorMessage = ref('');

let stripe = null;
let elements = null;
let cardElement = null;

onMounted(async () => {
    stripe = await loadStripe(props.publishableKey);
    elements = stripe.elements();
    cardElement = elements.create('card', {
        style: {
            base: {
                fontSize: '14px',
                color: '#1b1b18',
                '::placeholder': { color: 'rgba(27, 27, 24, 0.4)' },
            },
        },
    });
    cardElement.mount(cardElementRef.value);
});

onBeforeUnmount(() => {
    cardElement?.destroy();
});

async function submit() {
    if (!stripe || !cardElement) return;

    submitting.value = true;
    errorMessage.value = '';

    const { error, paymentIntent } = await stripe.confirmCardPayment(props.clientSecret, {
        payment_method: { card: cardElement },
    });

    submitting.value = false;

    if (error) {
        errorMessage.value = error.message ?? 'The card was declined.';
        emit('error', errorMessage.value);
        return;
    }

    emit('succeeded', paymentIntent);
}
</script>

<template>
    <div class="space-y-3">
        <div ref="cardElementRef" class="rounded-md border border-black/10 px-3 py-2.5 focus-within:border-gold-500 focus-within:ring-2 focus-within:ring-gold-500/30"></div>
        <p v-if="errorMessage" class="text-sm text-red-600">{{ errorMessage }}</p>
        <button
            type="button"
            :disabled="submitting"
            @click="submit"
            class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
            {{ submitting ? 'Processing…' : 'Pay Now' }}
        </button>
    </div>
</template>
