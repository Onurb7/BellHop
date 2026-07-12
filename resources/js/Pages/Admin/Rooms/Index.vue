<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AdminLayout from '../../../Layouts/AdminLayout.vue';

defineProps({
    rooms: Array,
});

function duplicate(room) {
    router.post(`/admin/rooms/${room.id}/duplicate`);
}

function destroy(room) {
    if (confirm(`Delete room "${room.title}" (#${room.number})? This can't be undone.`)) {
        router.delete(`/admin/rooms/${room.id}`);
    }
}
</script>

<template>
    <Head title="Rooms" />
    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="font-serif text-3xl">Rooms</h1>
            <Link
                href="/admin/rooms/create"
                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                + New Room
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3">Title</th>
                        <th class="px-4 py-3">Number</th>
                        <th class="px-4 py-3">Type</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Published</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="room in rooms" :key="room.id" class="border-b border-black/5 last:border-0">
                        <td class="px-4 py-3">
                            <img
                                v-if="room.thumb_url"
                                :src="room.thumb_url"
                                class="h-12 w-16 rounded object-cover"
                                alt=""
                            />
                            <div v-else class="h-12 w-16 rounded bg-black/5"></div>
                        </td>
                        <td class="px-4 py-3 font-medium">{{ room.title }}</td>
                        <td class="px-4 py-3">{{ room.number }}</td>
                        <td class="px-4 py-3">{{ room.room_type }}</td>
                        <td class="px-4 py-3 capitalize">{{ room.status }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="room.is_published ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                            >
                                {{ room.is_published ? 'Published' : 'Draft' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <Link :href="`/admin/rooms/${room.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                            <button type="button" @click="duplicate(room)" class="text-gold-600 hover:underline">Duplicate</button>
                            <button type="button" @click="destroy(room)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="rooms.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center opacity-50">No rooms yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AdminLayout>
</template>
