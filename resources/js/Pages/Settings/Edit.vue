<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDatePart, formatTimePart } from '../../Composables/useDateFormat.js';

const props = defineProps({
    date_format: String,
    time_format: String,
    week_start: String,
});

const form = useForm({
    date_format: props.date_format,
    time_format: props.time_format,
    week_start: props.week_start,
});

const now = new Date().toISOString();
const dateExample = computed(() => formatDatePart(now, form.date_format));
const timeExample = computed(() => formatTimePart(now, form.time_format));

function submit() {
    form.put('/settings');
}
</script>

<template>
    <Head title="Settings" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Settings</h1>
        </template>

        <form @submit.prevent="submit" class="max-w-lg space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div>
                <label class="mb-1 block text-sm font-medium">Date format</label>
                <select
                    v-model="form.date_format"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                >
                    <option value="iso">YYYY-MM-DD</option>
                    <option value="us">MM/DD/YYYY</option>
                    <option value="eu">DD/MM/YYYY</option>
                </select>
                <p class="mt-1 text-xs opacity-50">Example: {{ dateExample }}</p>
                <p v-if="form.errors.date_format" class="mt-1 text-sm text-red-600">{{ form.errors.date_format }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">Time format</label>
                <select
                    v-model="form.time_format"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                >
                    <option value="24h">24-hour</option>
                    <option value="12h">12-hour (AM/PM)</option>
                </select>
                <p class="mt-1 text-xs opacity-50">Example: {{ timeExample }}</p>
                <p v-if="form.errors.time_format" class="mt-1 text-sm text-red-600">{{ form.errors.time_format }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium">First day of the week</label>
                <select
                    v-model="form.week_start"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                >
                    <option value="monday">Monday</option>
                    <option value="sunday">Sunday</option>
                </select>
                <p class="mt-1 text-xs opacity-50">Controls where the week starts on the calendar.</p>
                <p v-if="form.errors.week_start" class="mt-1 text-sm text-red-600">{{ form.errors.week_start }}</p>
            </div>

            <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    Save changes
                </button>
            </div>
        </form>
    </AppLayout>
</template>
