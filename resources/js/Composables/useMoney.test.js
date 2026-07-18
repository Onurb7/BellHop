import { describe, expect, it } from 'vitest';
import { convertCents, formatMoney } from './useMoney.js';

const rates = { EUR: 0.5, JPY: 100 };

describe('convertCents', () => {
    it('returns the amount unchanged when currencies match', () => {
        expect(convertCents(1000, 'USD', 'USD', rates)).toBe(1000);
    });

    it('converts USD to a target currency', () => {
        expect(convertCents(1000, 'USD', 'EUR', rates)).toBe(500);
    });

    it('converts a target currency back to USD', () => {
        expect(convertCents(500, 'EUR', 'USD', rates)).toBe(1000);
    });

    it('converts between two non-USD currencies via a USD pivot', () => {
        // €10.00 -> $20.00 -> ¥2000.00
        expect(convertCents(1000, 'EUR', 'JPY', rates)).toBe(200000);
    });

    it('returns the amount unchanged when rates are unavailable', () => {
        expect(convertCents(1000, 'USD', 'EUR', null)).toBe(1000);
    });
});

describe('formatMoney', () => {
    it('formats USD with two decimal places', () => {
        expect(formatMoney(12345, 'USD')).toBe('$123.45');
    });

    it('formats JPY with zero decimal places', () => {
        expect(formatMoney(1200000, 'JPY')).toBe('¥12,000');
    });
});
