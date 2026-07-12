<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import AmenityBadgePicker from '../../../Components/Admin/AmenityBadgePicker.vue';

const props = defineProps({
    room: Object,
    roomTypes: Array,
    amenities: Array,
});

const isEditing = !!props.room;

const form = useForm({
    room_type_id: props.room?.room_type_id ?? (props.roomTypes[0]?.id ?? ''),
    title: props.room?.title ?? '',
    description: props.room?.description ?? '',
    number: props.room?.number ?? '',
    floor: props.room?.floor ?? '',
    status: props.room?.status ?? 'active',
    is_published: props.room?.is_published ?? false,
    amenities: props.room?.amenity_ids ?? [],
    images: [],
    remove_images: [],
});

const existingImages = ref(props.room?.images ?? []);
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
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/rooms/${props.room.id}`);
    } else {
        form.post('/admin/rooms');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Room' : 'New Room'" />
    <AdminLayout>
        <h1 class="mb-6 font-serif text-3xl">{{ isEditing ? 'Edit Room' : 'New Room' }}</h1>

        <form @submit.prevent="submit" class="max-w-2xl space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Room type</label>
                    <select
                        v-model="form.room_type_id"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    >
                        <option v-for="type in roomTypes" :key="type.id" :value="type.id">{{ type.name }}</option>
                    </select>
                    <p v-if="form.errors.room_type_id" class="mt-1 text-sm text-red-600">{{ form.errors.room_type_id }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Status</label>
                    <select
                        v-model="form.status"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    >
                        <option value="active">Active</option>
                        <option value="maintenance">Maintenance</option>
                        <option value="retired">Retired</option>
                    </select>
                    <p v-if="form.errors.status" class="mt-1 text-sm text-red-600">{{ form.errors.status }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Title</label>
                <input
                    v-model="form.title"
                    type="text"
                    placeholder="e.g. Cozy Garden View King Room"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Description</label>
                <textarea
                    v-model="form.description"
                    rows="4"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                ></textarea>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium">Room number</label>
                    <input
                        v-model="form.number"
                        type="text"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                    <p v-if="form.errors.number" class="mt-1 text-sm text-red-600">{{ form.errors.number }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium">Floor</label>
                    <input
                        v-model="form.floor"
                        type="text"
                        class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                    />
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.is_published" type="checkbox" class="accent-gold-500" />
                Published (visible to guests)
            </label>

            <div>
                <label class="mb-2 block text-sm font-medium">Features</label>
                <AmenityBadgePicker v-model="form.amenities" :amenities="amenities" />
            </div>

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
                    {{ isEditing ? 'Save changes' : 'Create room' }}
                </button>
            </div>
        </form>
    </AdminLayout>
</template>
