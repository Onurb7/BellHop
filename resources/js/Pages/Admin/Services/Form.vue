<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

const props = defineProps({
    service: Object,
});

const isEditing = !!props.service;

const form = useForm({
    name: props.service?.name ?? '',
    description: props.service?.description ?? '',
    unit_price: props.service?.unit_price ?? '',
    pricing_type: props.service?.pricing_type ?? 'per_night',
    active: props.service?.active ?? true,
});

function submit() {
    if (isEditing) {
        form.put(`/admin/services/${props.service.id}`);
    } else {
        form.post('/admin/services');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Service' : 'New Service'" />
    <AdminLayout>
        <h1 class="mb-6 font-serif text-3xl">{{ isEditing ? 'Edit Service' : 'New Service' }}</h1>

        <form @submit.prevent="submit" class="max-w-lg space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div>
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="e.g. Parking, Breakfast, Late Checkout"
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
                    <label class="mb-1 block text-sm font-medium">Unit price (USD)</label>
                    <input
                        v-model="form.unit_price"
                        type="number"
                        step="0.01"
                        min="0"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="form.errors.unit_price" class="mt-1 text-sm text-red-600">{{ form.errors.unit_price }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Pricing</label>
                    <select
                        v-model="form.pricing_type"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    >
                        <option value="per_night">Per night</option>
                        <option value="flat">Flat fee</option>
                    </select>
                    <p class="mt-1 text-xs opacity-50">
                        Per night: unit price × number of nights. Flat: charged once regardless of stay length.
                    </p>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="accent-gold-500" />
                Active (purchasable by guests)
            </label>

            <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {{ isEditing ? 'Save changes' : 'Create service' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
