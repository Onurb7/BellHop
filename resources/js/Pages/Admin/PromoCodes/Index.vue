<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useDateFormat } from '../../../Composables/useDateFormat.js';

defineProps({
    promoCodes: Array,
});

const { formatDate } = useDateFormat();

function scopeSummary(promoCode) {
    return promoCode.services.length ? promoCode.services.join(', ') : 'Any';
}

function usesSummary(promoCode) {
    return `${promoCode.redemptions_count} / ${promoCode.max_uses ?? '∞'}`;
}

function destroy(promoCode) {
    if (confirm(`Delete "${promoCode.code}"? This can't be undone.`)) {
        router.delete(`/admin/promo-codes/${promoCode.id}`);
    }
}
</script>

<template>
    <Head title="Promo Codes" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Promo Codes</h1>
        </template>

        <div class="mb-6 flex justify-end">
            <Link
                href="/admin/promo-codes/create"
                class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
            >
                + New Code
            </Link>
        </div>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">Discount</th>
                        <th class="px-4 py-3">Scope</th>
                        <th class="px-4 py-3">Uses</th>
                        <th class="px-4 py-3">Expires</th>
                        <th class="px-4 py-3">Active</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="promoCode in promoCodes" :key="promoCode.id" class="border-b border-black/5 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ promoCode.code }}</td>
                        <td class="px-4 py-3 text-emerald-700">-{{ promoCode.percentage }}%</td>
                        <td class="px-4 py-3">{{ scopeSummary(promoCode) }}</td>
                        <td class="px-4 py-3">{{ usesSummary(promoCode) }}</td>
                        <td class="px-4 py-3">{{ promoCode.expires_at ? formatDate(promoCode.expires_at) : 'Never' }}</td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="promoCode.active ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                            >
                                {{ promoCode.active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                            <Link :href="`/admin/promo-codes/${promoCode.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                            <button type="button" @click="destroy(promoCode)" class="text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="promoCodes.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center opacity-50">No promo codes yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
