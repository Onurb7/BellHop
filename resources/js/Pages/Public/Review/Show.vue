<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Star } from '@lucide/vue';
import PublicLayout from '../../../Layouts/PublicLayout.vue';
import { useDateFormat } from '../../../Composables/useDateFormat.js';

const props = defineProps({
    review: Object,
    booking: Object,
});

const { formatDate } = useDateFormat();

const form = useForm({
    rating: props.review.rating ?? 0,
    body: props.review.body ?? '',
});

function submit() {
    form.post(`/review/${props.review.uuid}`);
}
</script>

<template>
    <Head title="Leave a Review" />
    <PublicLayout>
        <div class="mx-auto max-w-lg">
            <div class="rounded-lg border border-gold-500/20 bg-white p-6">
                <p class="text-xs uppercase tracking-wide opacity-50">Your stay</p>
                <p class="mt-1 font-serif text-lg">{{ booking.room_type }}</p>
                <p class="text-sm opacity-60">{{ formatDate(booking.check_in) }} → {{ formatDate(booking.check_out) }}</p>

                <div v-if="review.already_submitted" class="mt-6 rounded-md border border-gold-500/30 bg-gold-50 px-4 py-6 text-center">
                    <p class="font-serif text-lg">Thank you for your feedback!</p>
                    <p class="mt-1 text-sm opacity-70">You've already reviewed this stay — we appreciate you taking the time.</p>
                </div>

                <form v-else class="mt-6 space-y-4" @submit.prevent="submit">
                    <h1 class="font-serif text-xl">How was your stay?</h1>
                    <p class="text-sm opacity-70">
                        Let us know how it was — your feedback helps us improve and maintain a high level of service.
                    </p>

                    <div>
                        <label class="mb-2 block text-xs uppercase tracking-wide opacity-50">Rating</label>
                        <div class="flex gap-1">
                            <button
                                v-for="star in 5"
                                :key="star"
                                type="button"
                                @click="form.rating = star"
                                class="p-1"
                            >
                                <Star
                                    class="h-7 w-7"
                                    :class="star <= form.rating ? 'fill-gold-500 text-gold-500' : 'text-black/20'"
                                />
                            </button>
                        </div>
                        <p v-if="form.errors.rating" class="mt-1 text-sm text-red-600">{{ form.errors.rating }}</p>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs uppercase tracking-wide opacity-50">Your review (optional)</label>
                        <textarea
                            v-model="form.body"
                            rows="4"
                            placeholder="Tell us about your stay..."
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        ></textarea>
                        <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing || form.rating === 0"
                        class="w-full rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white disabled:opacity-50"
                    >
                        Submit review
                    </button>
                </form>
            </div>
        </div>
    </PublicLayout>
</template>
