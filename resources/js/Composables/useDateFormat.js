import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

// Simple string splitting/rearranging, not a date library — matches the
// hand-rolled, timezone-safe approach already used for local date math
// in Calendar/Index.vue's toISO/parseISO.
export function formatDatePart(isoValue, dateFormat) {
    if (!isoValue) return '';
    const [year, month, day] = isoValue.slice(0, 10).split('-');

    if (dateFormat === 'us') return `${month}/${day}/${year}`;
    if (dateFormat === 'eu') return `${day}/${month}/${year}`;

    return `${year}-${month}-${day}`;
}

export function formatTimePart(isoValue, timeFormat) {
    const timeSegment = isoValue.includes('T') ? isoValue.split('T')[1] : isoValue.split(' ')[1];

    if (!timeSegment) return '';

    const [hourStr, minute] = timeSegment.split(':');
    let hour = parseInt(hourStr, 10);

    if (timeFormat === '12h') {
        const period = hour >= 12 ? 'PM' : 'AM';
        hour = hour % 12 || 12;

        return `${hour}:${minute} ${period}`;
    }

    return `${String(hour).padStart(2, '0')}:${minute}`;
}

export function useDateFormat() {
    const page = usePage();
    const dateFormat = computed(() => page.props.auth.user?.date_format ?? 'iso');
    const timeFormat = computed(() => page.props.auth.user?.time_format ?? '24h');

    function formatDate(isoValue) {
        return formatDatePart(isoValue, dateFormat.value);
    }

    function formatTime(isoValue) {
        if (!isoValue) return '';
        return formatTimePart(isoValue, timeFormat.value);
    }

    function formatDateTime(isoValue) {
        if (!isoValue) return '';
        return `${formatDatePart(isoValue, dateFormat.value)} ${formatTimePart(isoValue, timeFormat.value)}`;
    }

    return { formatDate, formatTime, formatDateTime };
}
