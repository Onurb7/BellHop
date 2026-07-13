<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

defineProps({
    services: Array,
});

function destroy(service) {
    if (confirm(`Delete "${service.name}"? This can't be undone.`)) {
        router.delete(`/admin/services/${service.id}`);
    }
}
</script>

<template>
    <Head title="Services" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Services</h1>
        </template>

        <div class="mb-6 flex justify-end">
            <Link
                href="/admin/services/create"
                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                + New Service
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Price</th>
                        <th class="px-4 py-3">Pricing</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="service in services" :key="service.id" class="border-b border-black/5 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ service.name }}</td>
                        <td class="px-4 py-3">${{ service.unit_price.toFixed(2) }}</td>
                        <td class="px-4 py-3">{{ service.pricing_type === 'per_night' ? 'Per night' : 'Flat fee' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="service.active ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                            >
                                {{ service.active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <Link :href="`/admin/services/${service.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                            <button type="button" @click="destroy(service)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="services.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center opacity-50">No services yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
