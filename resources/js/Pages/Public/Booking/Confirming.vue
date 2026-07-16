<script setup>
import { Head, router } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { Loader } from '@lucide/vue';
import PublicLayout from '../../../Layouts/PublicLayout.vue';

const retried = ref(false);

onMounted(() => {
    setTimeout(() => {
        retried.value = true;
        router.reload();
    }, 2000);
});
</script>

<template>
    <Head title="Confirming Payment" />
    <PublicLayout>
        <div class="mx-auto max-w-md rounded-lg border border-gold-500/20 bg-white p-10 text-center">
            <Loader class="mx-auto h-8 w-8 animate-spin text-gold-600" />
            <p class="mt-4 text-sm opacity-70">Confirming your payment…</p>
            <p v-if="retried" class="mt-2 text-xs opacity-50">
                Still working on it — if this doesn't update in a moment, refresh the page.
            </p>
        </div>
    </PublicLayout>
</template>
