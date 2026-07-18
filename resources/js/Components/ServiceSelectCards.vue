<script setup>
import { Check, ImageOff } from '@lucide/vue';
import { useMoney } from '../Composables/useMoney.js';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    services: {
        type: Array,
        required: true,
    },
    nights: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const { money } = useMoney();

function isSelected(id) {
    return props.modelValue.includes(id);
}

function toggle(id) {
    const selected = props.modelValue.includes(id)
        ? props.modelValue.filter((existingId) => existingId !== id)
        : [...props.modelValue, id];

    emit('update:modelValue', selected);
}

function fullStayPrice(service) {
    return service.pricing_type === 'per_night'
        ? money(service.unit_price_cents * props.nights, service.currency)
        : money(service.unit_price_cents, service.currency);
}
</script>

<template>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <button
            v-for="service in services"
            :key="service.id"
            type="button"
            @click="toggle(service.id)"
            class="group relative h-28 cursor-pointer overflow-hidden rounded-lg text-left transition"
            :class="isSelected(service.id) ? 'ring-2 ring-gold-500' : 'opacity-60 grayscale hover:opacity-80'"
        >
            <img
                v-if="service.thumb_url"
                :src="service.thumb_url"
                :alt="service.name"
                class="absolute inset-0 h-full w-full object-cover"
            />
            <div v-else class="absolute inset-0 flex items-center justify-center bg-gold-50">
                <ImageOff class="h-6 w-6 text-gold-600/40" />
            </div>

            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/10 to-transparent"></div>

            <div
                v-if="isSelected(service.id)"
                class="absolute right-2 top-2 flex h-5 w-5 items-center justify-center rounded-full bg-gold-500 text-white"
            >
                <Check class="h-3.5 w-3.5" />
            </div>

            <div class="absolute inset-x-0 bottom-0 p-2.5 text-white">
                <p class="text-sm font-medium leading-tight">{{ service.name }}</p>
                <p class="text-xs">
                    <span class="opacity-80">{{ fullStayPrice(service) }}</span>
                    <span v-if="service.pricing_type === 'per_night'" class="ml-1 font-semibold text-gold-500">×{{ nights }}</span>
                </p>
            </div>
        </button>
    </div>
</template>
