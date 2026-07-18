<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    roomType: Object,
});

const isEditing = !!props.roomType;

const form = useForm({
    name: props.roomType?.name ?? '',
    description: props.roomType?.description ?? '',
    base_rate: props.roomType?.base_rate ?? '',
    currency: props.roomType?.currency ?? 'USD',
    max_occupancy: props.roomType?.max_occupancy ?? 2,
});

function submit() {
    if (isEditing) {
        form.put(`/admin/room-types/${props.roomType.id}`);
    } else {
        form.post('/admin/room-types');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Room Type' : 'New Room Type'" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">{{ isEditing ? 'Edit Room Type' : 'New Room Type' }}</h1>
        </template>


        <form @submit.prevent="submit" class="max-w-lg space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="e.g. Deluxe King"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Description</label>
                <textarea
                    v-model="form.description"
                    rows="3"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                ></textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Base rate (per night)</label>
                    <input
                        v-model="form.base_rate"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="form.errors.base_rate" class="mt-1 text-sm text-red-600">{{ form.errors.base_rate }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Currency</label>
                    <select
                        v-model="form.currency"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    >
                        <option value="USD">USD — US Dollar</option>
                        <option value="EUR">EUR — Euro</option>
                        <option value="GBP">GBP — British Pound</option>
                        <option value="JPY">JPY — Japanese Yen</option>
                        <option value="KRW">KRW — South Korean Won</option>
                        <option value="CAD">CAD — Canadian Dollar</option>
                        <option value="AUD">AUD — Australian Dollar</option>
                        <option value="CNY">CNY — Chinese Yuan</option>
                    </select>
                    <p class="mt-1 text-xs opacity-50">The currency the base rate above is entered in.</p>
                    <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Max occupancy</label>
                <input
                    v-model="form.max_occupancy"
                    type="number"
                    min="1"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.max_occupancy" class="mt-1 text-sm text-red-600">{{ form.errors.max_occupancy }}</p>
            </div>

            <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {{ isEditing ? 'Save changes' : 'Create room type' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
