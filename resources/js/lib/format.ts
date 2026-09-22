import { usePage } from '@inertiajs/vue3';

function timezone(): string {
    return (usePage().props.timezone as string) ?? 'America/Chicago';
}

/** Season points: "8.5", "10", "7.33". */
export function points(value: number | string | null | undefined): string {
    return Number(value ?? 0)
        .toFixed(2)
        .replace(/\.?0+$/, '');
}

/** "1st", "2nd", "3rd", "11th". */
export function ordinal(n: number): string {
    const suffix =
        n % 100 >= 11 && n % 100 <= 13
            ? 'th'
            : (({ 1: 'st', 2: 'nd', 3: 'rd' } as Record<number, string>)[
                  n % 10
              ] ?? 'th');

    return `${n}${suffix}`;
}

/** e.g. "Thu, Sep 24 · 7:15 PM CDT" in the display timezone. */
export function kickoff(iso: string | null | undefined): string {
    if (!iso) {
        return 'TBD';
    }

    const date = new Date(iso);
    const tz = timezone();
    const day = date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        timeZone: tz,
    });
    const time = date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        timeZone: tz,
        timeZoneName: 'short',
    });

    return `${day} · ${time}`;
}

/** Value for an <input type="datetime-local"> in the display timezone. */
export function toLocalInput(iso: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone(),
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(iso));
    const get = (type: string) => parts.find((p) => p.type === type)?.value;

    return `${get('year')}-${get('month')}-${get('day')}T${get('hour')}:${get('minute')}`;
}

export function relative(iso: string | null | undefined): string {
    if (!iso) {
        return '';
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const units: [Intl.RelativeTimeFormatUnit, number][] = [
        ['day', 86400],
        ['hour', 3600],
        ['minute', 60],
    ];
    const rtf = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

    for (const [unit, size] of units) {
        if (Math.abs(seconds) >= size) {
            return rtf.format(Math.round(seconds / size), unit);
        }
    }

    return rtf.format(seconds, 'second');
}
