<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useDateFormat } from '../../../Composables/useDateFormat.js';

const props = defineProps({
    rules: Array,
});

const { formatDate } = useDateFormat();

const dayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

function dateSummary(rule) {
    if (rule.date_kind === 'day_of_week') {
        return (rule.days_of_week ?? []).map((d) => dayLabels[d]).join(', ') + ' nights';
    }

    const range = `${formatDate(rule.start_date)} → ${formatDate(rule.end_date)}`;
    return rule.recurring ? `${range} (yearly)` : range;
}

function rampSummary(rule) {
    if (rule.date_kind === 'day_of_week') {
        return '—';
    }
    if (rule.ramp_in_days === 0 && rule.ramp_out_days === 0) {
        return 'None';
    }
    return `${rule.ramp_in_days} in / ${rule.ramp_out_days} out`;
}

const templates = computed(() => props.rules.filter((r) => r.is_template));
const manualRules = computed(() => props.rules.filter((r) => !r.is_template));

function destroy(rule) {
    if (confirm(`Delete "${rule.name}"? This can't be undone.`)) {
        router.delete(`/admin/pricing/${rule.id}`);
    }
}
</script>

<template>
    <Head title="Pricing" />
    <AppLayout>
        <template #header>
            <h1 class="font-serif text-xl">Pricing</h1>
        </template>

        <div class="space-y-8">
            <div>
                <h2 class="font-serif text-lg">Templates</h2>
                <p class="mt-1 text-xs opacity-50">
                    Always available — tune the percentage, ramp, and dates, or switch one off. They can't be renamed or deleted.
                </p>

                <div class="mt-3 overflow-hidden rounded-lg border border-gold-500/20 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3">Adjustment</th>
                                <th class="px-4 py-3">Ramp</th>
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in templates" :key="rule.id" class="border-b border-black/5 last:border-0">
                                <td class="px-4 py-3 font-medium">{{ rule.name }}</td>
                                <td class="px-4 py-3">{{ dateSummary(rule) }}</td>
                                <td class="px-4 py-3" :class="rule.percentage >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                    {{ rule.percentage >= 0 ? '+' : '' }}{{ rule.percentage }}%
                                </td>
                                <td class="px-4 py-3">{{ rampSummary(rule) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="rule.active ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                                    >
                                        {{ rule.active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <Link :href="`/admin/pricing/${rule.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <h2 class="font-serif text-lg">Manual Rules</h2>
                    <Link
                        href="/admin/pricing/create"
                        class="rounded-md bg-gradient-to-r from-gold-500 to-gold-600 px-4 py-2 text-sm font-medium text-white"
                    >
                        + New Rule
                    </Link>
                </div>

                <div class="mt-3 overflow-hidden rounded-lg border border-gold-500/20 bg-white">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-gold-500/20 bg-gold-50 text-xs uppercase tracking-wide text-gold-700">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3">Adjustment</th>
                                <th class="px-4 py-3">Ramp</th>
                                <th class="px-4 py-3">Active</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="rule in manualRules" :key="rule.id" class="border-b border-black/5 last:border-0">
                                <td class="px-4 py-3 font-medium">{{ rule.name }}</td>
                                <td class="px-4 py-3">{{ dateSummary(rule) }}</td>
                                <td class="px-4 py-3" :class="rule.percentage >= 0 ? 'text-emerald-700' : 'text-red-600'">
                                    {{ rule.percentage >= 0 ? '+' : '' }}{{ rule.percentage }}%
                                </td>
                                <td class="px-4 py-3">{{ rampSummary(rule) }}</td>
                                <td class="px-4 py-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs"
                                        :class="rule.active ? 'bg-gold-500/15 text-gold-700' : 'bg-black/5 text-black/50'"
                                    >
                                        {{ rule.active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right space-x-3 whitespace-nowrap">
                                    <Link :href="`/admin/pricing/${rule.id}/edit`" class="text-gold-600 hover:underline">Edit</Link>
                                    <button type="button" @click="destroy(rule)" class="text-red-600 hover:underline">Delete</button>
                                </td>
                            </tr>
                            <tr v-if="manualRules.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center opacity-50">No manual rules yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
