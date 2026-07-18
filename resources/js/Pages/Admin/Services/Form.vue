<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    service: Object,
});

const isEditing = !!props.service;

const form = useForm({
    name: props.service?.name ?? '',
    description: props.service?.description ?? '',
    unit_price: props.service?.unit_price ?? '',
    currency: props.service?.currency ?? 'USD',
    pricing_type: props.service?.pricing_type ?? 'per_night',
    active: props.service?.active ?? true,
    images: [],
    remove_images: [],
});

const existingImages = ref(props.service?.images ?? []);
const newImagePreviews = ref([]);

function onFilesSelected(event) {
    const files = Array.from(event.target.files ?? []);
    form.images = files;
    newImagePreviews.value = files.map((file) => URL.createObjectURL(file));
}

function toggleRemoveImage(mediaId) {
    if (form.remove_images.includes(mediaId)) {
        form.remove_images = form.remove_images.filter((id) => id !== mediaId);
    } else {
        form.remove_images = [...form.remove_images, mediaId];
    }
}

function submit() {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/services/${props.service.id}`);
    } else {
        form.post('/admin/services');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Service' : 'New Service'" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">{{ isEditing ? 'Edit Service' : 'New Service' }}</h1>
        </template>


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
                    <label class="mb-1 block text-sm font-medium">Unit price</label>
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
                    <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</p>
                </div>
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

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="accent-gold-500" />
                Active (purchasable by guests)
            </label>

            <div>
                <label class="mb-2 block text-sm font-medium">Images</label>

                <div v-if="existingImages.length" class="mb-3 flex flex-wrap gap-3">
                    <div v-for="image in existingImages" :key="image.id" class="relative">
                        <img :src="image.thumb_url" class="h-20 w-28 rounded object-cover" :class="{ 'opacity-30': form.remove_images.includes(image.id) }" alt="" />
                        <button
                            type="button"
                            @click="toggleRemoveImage(image.id)"
                            class="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-white text-xs shadow"
                            :class="form.remove_images.includes(image.id) ? 'text-gold-600' : 'text-red-600'"
                        >
                            {{ form.remove_images.includes(image.id) ? '↺' : '×' }}
                        </button>
                    </div>
                </div>

                <div v-if="newImagePreviews.length" class="mb-3 flex flex-wrap gap-3">
                    <img v-for="(src, i) in newImagePreviews" :key="i" :src="src" class="h-20 w-28 rounded object-cover" alt="" />
                </div>

                <input type="file" multiple accept="image/*" @change="onFilesSelected" class="text-sm" />
                <p v-if="form.errors.images" class="mt-1 text-sm text-red-600">{{ form.errors.images }}</p>
            </div>

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
    </AppLayout>
</template>
