<script setup>
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useMoney } from '../Composables/useMoney.js';

const props = defineProps({
    services: {
        type: Array,
        required: true,
    },
    postUrl: {
        type: String,
        required: true,
    },
    nights: {
        type: Number,
        required: true,
    },
});

const { money } = useMoney();

const selectedServiceId = ref(props.services[0]?.id ?? null);
const nightsValue = ref(props.nights);
const quantityValue = ref(1);
const submitting = ref(false);

const selectedService = computed(() => props.services.find((service) => service.id === selectedServiceId.value) ?? null);

function submit() {
    if (!selectedService.value) {
        return;
    }

    submitting.value = true;

    const payload = { service_id: selectedService.value.id };

    if (selectedService.value.pricing_type === 'per_night') {
        payload.nights = nightsValue.value;
    } else {
        payload.quantity = quantityValue.value;
    }

    router.post(props.postUrl, payload, {
        preserveScroll: true,
        onFinish: () => (submitting.value = false),
    });
}
</script>

<template>
    <div v-if="services.length" class="flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-xs uppercase tracking-wide opacity-50">Service</label>
            <select
                v-model="selectedServiceId"
                class="mt-1 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
            >
                <option v-for="service in services" :key="service.id" :value="service.id">
                    {{ service.name }} — {{ money(service.unit_price_cents, service.currency) }}{{ service.pricing_type === 'per_night' ? '/night' : '' }}
                </option>
            </select>
        </div>

        <div v-if="selectedService?.pricing_type === 'per_night'">
            <label class="block text-xs uppercase tracking-wide opacity-50">Nights</label>
            <input
                v-model.number="nightsValue"
                type="number"
                min="1"
                :max="nights"
                class="mt-1 w-24 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
            />
        </div>
        <div v-else>
            <label class="block text-xs uppercase tracking-wide opacity-50">Quantity</label>
            <input
                v-model.number="quantityValue"
                type="number"
                min="1"
                class="mt-1 w-24 rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
            />
        </div>

        <button
            type="button"
            :disabled="submitting"
            @click="submit"
            class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
        >
            Add
        </button>
    </div>
    <p v-else class="text-sm opacity-50">No services are currently available.</p>
</template>
