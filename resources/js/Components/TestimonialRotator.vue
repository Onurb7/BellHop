<script setup>
import { Star } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    reviews: Array,
});

const index = ref(0);
let timer = null;

onMounted(() => {
    if (props.reviews.length > 1) {
        timer = setInterval(() => {
            index.value = (index.value + 1) % props.reviews.length;
        }, 6000);
    }
});

onBeforeUnmount(() => clearInterval(timer));

const current = computed(() => props.reviews[index.value]);
</script>

<template>
    <div v-if="current" class="mx-auto min-h-[76px] max-w-xs text-center">
        <Transition name="testimonial" mode="out-in">
            <div :key="index">
                <div class="flex justify-center">
                    <Star
                        v-for="star in 5"
                        :key="star"
                        class="h-3.5 w-3.5"
                        :class="star <= current.rating ? 'fill-gold-500 text-gold-500' : 'text-black/20'"
                    />
                </div>
                <p v-if="current.body" class="mt-1.5 text-xs italic opacity-70 line-clamp-2">"{{ current.body }}"</p>
                <p class="mt-1 text-xs uppercase tracking-wide opacity-50">— {{ current.guest_name }}</p>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.testimonial-enter-active,
.testimonial-leave-active {
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.testimonial-enter-from {
    opacity: 0;
    transform: translateY(4px);
}

.testimonial-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}
</style>
