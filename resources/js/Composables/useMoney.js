import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Converts via a USD pivot — the shared `exchange_rates` prop only ever
// gives base=USD rates, so any non-USD-to-non-USD conversion goes through
// USD as an intermediate step. Mirrors ExchangeRateService::convertCents()
// on the backend — same algorithm, kept in sync manually since one's PHP
// and one's JS.
export function convertCents(amountCents, from, to, rates) {
    if (from === to || !rates) return amountCents;

    const usdCents = from === 'USD' ? amountCents : amountCents / (rates[from] ?? 1);
    const targetCents = to === 'USD' ? usdCents : usdCents * (rates[to] ?? 1);

    return Math.round(targetCents);
}

// Intl.NumberFormat, not a hand-rolled `$X.XX` string — JPY/KRW have zero
// decimal places and this gets that right automatically.
export function formatMoney(amountCents, currency) {
    return new Intl.NumberFormat(undefined, { style: 'currency', currency }).format(amountCents / 100);
}

export function useMoney() {
    const page = usePage();
    const preferredCurrency = computed(() => page.props.auth.user?.currency ?? 'USD');
    const rates = computed(() => page.props.exchange_rates ?? null);

    function money(amountCents, sourceCurrency = 'USD') {
        return formatMoney(convertCents(amountCents, sourceCurrency, preferredCurrency.value, rates.value), preferredCurrency.value);
    }

    return { money, preferredCurrency, rates };
}
