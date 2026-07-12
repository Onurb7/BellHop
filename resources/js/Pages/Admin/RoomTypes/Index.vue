<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    roomTypes: Array,
});

function destroy(roomType) {
    if (confirm(`Delete room type "${roomType.name}"? This can't be undone.`)) {
        router.delete(`/admin/room-types/${roomType.id}`);
    }
}
</script>

<template>
    <Head title="Room Types" />
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-serif text-3xl">Room Types</h1>
            <Link
                href="/admin/room-types/create"
                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                + New Room Type
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Base rate</th>
                        <th class="px-4 py-3">Max occupancy</th>
                        <th class="px-4 py-3">Rooms</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="roomType in roomTypes" :key="roomType.id" class="border-b border-black/5 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ roomType.name }}</td>
                        <td class="px-4 py-3">${{ roomType.base_rate.toFixed(2) }}</td>
                        <td class="px-4 py-3">{{ roomType.max_occupancy }}</td>
                        <td class="px-4 py-3">{{ roomType.rooms_count }}</td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <Link :href="`/admin/room-types/${roomType.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                            <button type="button" @click="destroy(roomType)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="roomTypes.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center opacity-50">No room types yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
