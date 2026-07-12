<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    modelValue: {
        type: Array,
        default: () => [],
    },
    amenities: {
        type: Array,
        required: true,
    },
});

const emit = defineEmits(['update:modelValue']);

const localAmenities = ref([...props.amenities]);
const showModal = ref(false);
const newName = ref('');
const submitting = ref(false);
const error = ref('');

function isSelected(id) {
    return props.modelValue.includes(id);
}

function toggle(id) {
    const selected = props.modelValue.includes(id)
        ? props.modelValue.filter((existingId) => existingId !== id)
        : [...props.modelValue, id];

    emit('update:modelValue', selected);
}

function openModal() {
    newName.value = '';
    error.value = '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

async function addAmenity() {
    if (!newName.value.trim()) {
        return;
    }

    submitting.value = true;
    error.value = '';

    try {
        const response = await axios.post('/admin/amenities', { name: newName.value.trim() });
        const amenity = response.data;

        localAmenities.value.push(amenity);
        emit('update:modelValue', [...props.modelValue, amenity.id]);
        closeModal();
    } catch (err) {
        error.value = err.response?.data?.errors?.name?.[0] ?? 'Could not add that feature.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <button
            v-for="amenity in localAmenities"
            :key="amenity.id"
            type="button"
            @click="toggle(amenity.id)"
            class="rounded-full px-3 py-1 text-sm transition"
            :class="isSelected(amenity.id)
                ? 'bg-gradient-to-r from-gold-500 to-gold-600 text-white'
                : 'bg-black/5 text-[#1b1b18] hover:bg-black/10'"
        >
            {{ amenity.name }}
        </button>

        <button
            type="button"
            @click="openModal"
            class="rounded-full border border-dashed border-gold-500/50 px-3 py-1 text-sm text-gold-600 hover:bg-gold-500/10"
        >
            + Add feature
        </button>
    </div>

    <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
        @click.self="closeModal"
    >
        <div class="w-full max-w-sm rounded-lg bg-white p-6 shadow-lg">
            <h2 class="font-serif text-xl text-[#1b1b18]">Add a feature</h2>
            <p class="mt-1 text-sm opacity-60">
                This creates a reusable feature any room can be tagged with.
            </p>

            <input
                v-model="newName"
                type="text"
                autofocus
                placeholder="e.g. TV, Private Bathroom"
                class="mt-4 w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                @keyup.enter="addAmenity"
            />
            <p v-if="error" class="mt-1 text-sm text-red-600">{{ error }}</p>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    @click="closeModal"
                    class="rounded-md px-3 py-2 text-sm hover:bg-black/5"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    :disabled="submitting"
                    @click="addAmenity"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Add
                </button>
            </div>
        </div>
    </div>
</template>
