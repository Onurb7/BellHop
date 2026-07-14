<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
    confirmWord: {
        type: String,
        default: 'cancel',
    },
    processing: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['confirm', 'close']);

const typed = ref('');

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            typed.value = '';
        }
    },
);

function confirm() {
    if (typed.value === props.confirmWord) {
        emit('confirm');
    }
}
</script>

<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="emit('close')"
    >
        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg">
            <h2 class="font-serif text-xl text-[#1b1b18]">{{ title }}</h2>
            <p v-if="message" class="mt-1 text-sm opacity-60">{{ message }}</p>

            <p class="mt-4 text-sm">
                Type <strong>{{ confirmWord }}</strong> to confirm.
            </p>
            <input
                v-model="typed"
                type="text"
                autofocus
                class="mt-2 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                @keyup.enter="confirm"
            />

            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="emit('close')" class="rounded-md px-3 py-2 text-sm hover:bg-black/5">
                    Nevermind
                </button>
                <button
                    type="button"
                    :disabled="typed !== confirmWord || processing"
                    @click="confirm"
                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-40"
                >
                    Confirm
                </button>
            </div>
        </div>
    </div>
</template>
