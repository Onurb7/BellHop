<script setup>
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { formatDatePart, formatTimePart } from '../../Composables/useDateFormat.js';
import { convertCents, formatMoney } from '../../Composables/useMoney.js';

const props = defineProps({
    date_format: String,
    time_format: String,
    week_start: String,
    currency: String,
});

const form = useForm({
    date_format: props.date_format,
    time_format: props.time_format,
    week_start: props.week_start,
    currency: props.currency,
});

const now = new Date().toISOString();
const dateExample = computed(() => formatDatePart(now, form.date_format));
const timeExample = computed(() => formatTimePart(now, form.time_format));

const page = usePage();
const rates = computed(() => page.props.exchange_rates ?? null);

const EXAMPLE_USD_CENTS = 10000;
const currencyExample = computed(() => {
    if (!rates.value) return null;
    return formatMoney(convertCents(EXAMPLE_USD_CENTS, 'USD', form.currency, rates.value), form.currency);
});

const displayCurrencies = ['EUR', 'GBP', 'JPY', 'KRW', 'CAD', 'AUD', 'CNY'];
const ratesTable = computed(() => {
    if (!rates.value) return null;
    return displayCurrencies.map((code) => ({ code, rate: rates.value[code] }));
});

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
                    <option value="eu_dot">DD.MM.YYYY.</option>
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

            <div>
                <label class="mb-1 block text-sm font-medium">Currency</label>
                <select
                    v-model="form.currency"
                    class="w-full rounded-md border border-black/10 px-3 py-2 text-sm focus:border-gold-500 focus:outline-none focus:ring-2 focus:ring-gold-500/30"
                >
                    <option value="USD">USD — US Dollar</option>
                    <option value="EUR">EUR — Euro</option>
                    <option value="GBP">GBP — British Pound</option>
                    <option value="JPY">JPY — Japanese Yen</option>
                    <option value="KRW">KRW — South Korean Won</option>
                    <option value="CAD">CAD — Canadian Dollar</option>
                    <option value="AUD">AUD — Australian Dollar</option>
                    <option value="CNY">CNY — Chinese Yuan</option>
                </select>
                <p class="mt-1 text-xs opacity-50">
                    Example: <template v-if="currencyExample">{{ formatMoney(EXAMPLE_USD_CENTS, 'USD') }} → {{ currencyExample }}</template><template v-else>exchange rates are temporarily unavailable</template>
                </p>
                <p class="mt-1 text-xs opacity-50">
                    This only changes how prices are displayed to you. All charges are still processed in USD, and invoices always show USD regardless of this setting.
                </p>
                <p v-if="form.errors.currency" class="mt-1 text-sm text-red-600">{{ form.errors.currency }}</p>
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

        <div class="mt-6 max-w-lg rounded-lg border border-gold-500/20 bg-white p-6">
            <h2 class="font-serif text-lg">Live exchange rates</h2>
            <p class="mt-1 text-xs opacity-50">Relative to 1 USD, via <a href="https://frankfurter.dev" target="_blank" rel="noopener" class="underline">Frankfurter</a> (ECB reference rates).</p>

            <table v-if="ratesTable" class="mt-4 w-full text-left text-sm">
                <thead class="text-xs uppercase tracking-wide opacity-50">
                    <tr>
                        <th class="py-1">Currency</th>
                        <th class="py-1 text-right">Rate</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in ratesTable" :key="row.code" class="border-t border-black/5">
                        <td class="py-2">{{ row.code }}</td>
                        <td class="py-2 text-right">{{ row.rate }}</td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="mt-4 text-sm opacity-50">Exchange rates are temporarily unavailable.</p>
        </div>
    </AppLayout>
</template>
