<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import DatePicker from '../../../Components/DatePicker.vue';

const props = defineProps({
    rule: Object,
});

const isEditing = !!props.rule;
const isTemplate = props.rule?.is_template ?? false;
const dateKind = props.rule?.date_kind ?? 'date_range';

const dayOptions = [
    { value: 0, label: 'Sun' },
    { value: 1, label: 'Mon' },
    { value: 2, label: 'Tue' },
    { value: 3, label: 'Wed' },
    { value: 4, label: 'Thu' },
    { value: 5, label: 'Fri' },
    { value: 6, label: 'Sat' },
];

const form = useForm({
    name: props.rule?.name ?? '',
    start_date: props.rule?.start_date ?? '',
    end_date: props.rule?.end_date ?? '',
    recurring: props.rule?.recurring ?? false,
    days_of_week: props.rule?.days_of_week ?? [],
    percentage: props.rule?.percentage ?? 10,
    ramp_in_days: props.rule?.ramp_in_days ?? 0,
    ramp_out_days: props.rule?.ramp_out_days ?? 0,
    active: props.rule?.active ?? true,
});

function submit() {
    if (isEditing) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(`/admin/pricing/${props.rule.id}`);
    } else {
        form.post('/admin/pricing');
    }
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Pricing Rule' : 'New Pricing Rule'" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">{{ isEditing ? `Edit ${rule.name}` : 'New Pricing Rule' }}</h1>
        </template>

        <form @submit.prevent="submit" class="max-w-lg space-y-6 rounded-lg border border-gold-500/20 bg-white p-6">
            <div v-if="!isTemplate">
                <label class="mb-1 block text-sm font-medium">Name</label>
                <input
                    v-model="form.name"
                    type="text"
                    placeholder="e.g. Summer Music Festival"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <p v-else class="text-xs uppercase tracking-wide opacity-50">Template — name and dates can't be renamed, only tuned below.</p>

            <div v-if="dateKind === 'day_of_week'">
                <label class="mb-2 block text-sm font-medium">Nights</label>
                <div class="flex flex-wrap gap-2">
                    <label
                        v-for="day in dayOptions"
                        :key="day.value"
                        class="flex items-center gap-1.5 rounded-md border border-black/10 px-3 py-1.5 text-sm"
                    >
                        <input type="checkbox" :value="day.value" v-model="form.days_of_week" class="accent-gold-500" />
                        {{ day.label }}
                    </label>
                </div>
                <p v-if="form.errors.days_of_week" class="mt-1 text-sm text-red-600">{{ form.errors.days_of_week }}</p>
            </div>

            <template v-else>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Start date</label>
                        <DatePicker v-model="form.start_date" class="w-full" />
                        <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">End date</label>
                        <DatePicker v-model="form.end_date" :min="form.start_date || undefined" class="w-full" />
                        <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
                    </div>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input v-model="form.recurring" type="checkbox" class="accent-gold-500" />
                    Repeats every year (only the month/day of the dates above are matched)
                </label>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium">Ramp in (days)</label>
                        <input
                            v-model.number="form.ramp_in_days"
                            type="number"
                            min="0"
                            max="30"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        />
                        <p v-if="form.errors.ramp_in_days" class="mt-1 text-sm text-red-600">{{ form.errors.ramp_in_days }}</p>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium">Ramp out (days)</label>
                        <input
                            v-model.number="form.ramp_out_days"
                            type="number"
                            min="0"
                            max="30"
                            class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                        />
                        <p v-if="form.errors.ramp_out_days" class="mt-1 text-sm text-red-600">{{ form.errors.ramp_out_days }}</p>
                    </div>
                </div>
                <p class="text-xs opacity-50">
                    Either can be 0 to skip tapering on that side entirely — e.g. a price cliff back to normal right after an event ends.
                </p>
            </template>

            <div>
                <label class="mb-1 block text-sm font-medium">Adjustment (%)</label>
                <input
                    v-model.number="form.percentage"
                    type="number"
                    min="-90"
                    max="500"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                />
                <p class="mt-1 text-xs opacity-50">Positive increases the room rate, negative discounts it. Always off the room's own base price.</p>
                <p v-if="form.errors.percentage" class="mt-1 text-sm text-red-600">{{ form.errors.percentage }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input v-model="form.active" type="checkbox" class="accent-gold-500" />
                Active
            </label>

            <div class="flex justify-end gap-3 border-t border-black/5 pt-4">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-5 py-2 text-sm font-medium text-white disabled:opacity-50"
                >
                    {{ isEditing ? 'Save changes' : 'Create rule' }}
                </button>
            </div>
        </form>
    </AppLayout>
</template>
