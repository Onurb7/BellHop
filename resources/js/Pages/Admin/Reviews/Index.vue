<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, ChevronsUpDown, Star } from '@lucide/vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useDateFormat } from '../../../Composables/useDateFormat.js';

const props = defineProps({
    reviews: Object,
    sort: String,
    dir: String,
});

const { formatDate } = useDateFormat();

const statusLabels = {
    pending: 'Pending',
    sent: 'Sent',
    reviewed: 'Reviewed',
};

// Default direction per column — newest first and best-rated first read
// more naturally than starting ascending; featured-first surfaces what's
// already on the home page before what isn't.
const defaultDirections = { submitted_at: 'desc', rating: 'desc', featured: 'desc' };

function sortBy(key) {
    const dir = props.sort === key ? (props.dir === 'asc' ? 'desc' : 'asc') : defaultDirections[key];

    router.get('/admin/reviews', { sort: key, dir }, { preserveState: true, preserveScroll: true });
}

function toggleFeatured(review) {
    router.post(`/admin/reviews/${review.id}/toggle-featured`, {}, { preserveState: true, preserveScroll: true });
}

function destroy(review) {
    if (confirm(`Delete this review from ${review.guest_name}? This can't be undone.`)) {
        router.delete(`/admin/reviews/${review.id}`);
    }
}

function goToPage(url) {
    if (!url) return;
    router.visit(url, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head title="Reviews" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Reviews</h1>
        </template>

        <div class="overflow-hidden rounded-lg border border-gold-500/20 bg-white">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                    <tr>
                        <th class="px-4 py-3">Guest</th>
                        <th class="px-4 py-3">Stay</th>
                        <th class="px-4 py-3">
                            <button type="button" class="flex cursor-pointer items-center gap-1 hover:text-gold-900" @click="sortBy('submitted_at')">
                                Submitted
                                <ChevronUp v-if="sort === 'submitted_at' && dir === 'asc'" class="h-3 w-3" />
                                <ChevronDown v-else-if="sort === 'submitted_at' && dir === 'desc'" class="h-3 w-3" />
                                <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-4 py-3">
                            <button type="button" class="flex cursor-pointer items-center gap-1 hover:text-gold-900" @click="sortBy('rating')">
                                Rating
                                <ChevronUp v-if="sort === 'rating' && dir === 'asc'" class="h-3 w-3" />
                                <ChevronDown v-else-if="sort === 'rating' && dir === 'desc'" class="h-3 w-3" />
                                <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-4 py-3">Review</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">
                            <button type="button" class="flex cursor-pointer items-center gap-1 hover:text-gold-900" @click="sortBy('featured')">
                                Featured
                                <ChevronUp v-if="sort === 'featured' && dir === 'asc'" class="h-3 w-3" />
                                <ChevronDown v-else-if="sort === 'featured' && dir === 'desc'" class="h-3 w-3" />
                                <ChevronsUpDown v-else class="h-3 w-3 opacity-40" />
                            </button>
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="review in reviews.data" :key="review.id" class="border-b border-black/5 last:border-0">
                        <td class="px-4 py-3 font-medium">{{ review.guest_name }}</td>
                        <td class="px-4 py-3">
                            {{ review.room_type }}
                            <span class="block text-xs opacity-50">{{ formatDate(review.check_in) }} → {{ formatDate(review.check_out) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span v-if="review.submitted_at">{{ formatDate(review.submitted_at) }}</span>
                            <span v-else class="opacity-40">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="review.rating" class="flex">
                                <Star
                                    v-for="star in 5"
                                    :key="star"
                                    class="h-4 w-4"
                                    :class="star <= review.rating ? 'fill-gold-500 text-gold-500' : 'text-black/20'"
                                />
                            </div>
                            <span v-else class="opacity-40">—</span>
                        </td>
                        <td class="max-w-xs px-4 py-3">
                            <span v-if="review.body" class="line-clamp-2 opacity-80">{{ review.body }}</span>
                            <span v-else class="opacity-40">—</span>
                        </td>
                        <td class="px-4 py-3">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="review.status === 'reviewed' ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                            >
                                {{ statusLabels[review.status] }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button
                                type="button"
                                :disabled="review.status !== 'reviewed'"
                                @click="toggleFeatured(review)"
                                class="cursor-pointer rounded-full px-2 py-0.5 text-xs disabled:cursor-not-allowed disabled:opacity-30"
                                :class="review.featured ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50 hover:bg-black/10'"
                            >
                                {{ review.featured ? 'Featured' : 'Feature' }}
                            </button>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button type="button" @click="destroy(review)" class="cursor-pointer text-red-600 hover:underline">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="reviews.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center opacity-50">No reviews yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="reviews.data.length > 0" class="mt-4 flex items-center justify-between text-sm opacity-70">
            <span>{{ reviews.total }} review{{ reviews.total === 1 ? '' : 's' }} — page {{ reviews.current_page }} of {{ reviews.last_page }}</span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    :disabled="!reviews.prev_page_url"
                    @click="goToPage(reviews.prev_page_url)"
                    class="cursor-pointer rounded-md border border-black/10 px-3 py-1.5 hover:bg-black/5 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    ‹ Prev
                </button>
                <button
                    type="button"
                    :disabled="!reviews.next_page_url"
                    @click="goToPage(reviews.next_page_url)"
                    class="cursor-pointer rounded-md border border-black/10 px-3 py-1.5 hover:bg-black/5 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
                >
                    Next ›
                </button>
            </div>
        </div>
    </AppLayout>
</template>
