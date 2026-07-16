import { mount, flushPromises } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import StripeCardForm from './StripeCardForm.vue';

const cardElement = { mount: vi.fn(), destroy: vi.fn() };

function fakeStripe(confirmCardPayment) {
    return {
        elements: () => ({ create: () => cardElement }),
        confirmCardPayment,
    };
}

vi.mock('@stripe/stripe-js', () => ({
    loadStripe: vi.fn(),
}));

describe('StripeCardForm', () => {
    it('emits succeeded with the payment intent when the card is accepted', async () => {
        const { loadStripe } = await import('@stripe/stripe-js');
        const paymentIntent = { id: 'pi_123', status: 'succeeded' };
        loadStripe.mockResolvedValue(fakeStripe(vi.fn().mockResolvedValue({ paymentIntent })));

        const wrapper = mount(StripeCardForm, {
            props: { publishableKey: 'pk_test', clientSecret: 'secret_123' },
        });
        await flushPromises();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('succeeded')).toEqual([[paymentIntent]]);
        expect(wrapper.emitted('error')).toBeUndefined();
    });

    it('shows the decline message, emits error, and re-enables the button when the card is declined', async () => {
        const { loadStripe } = await import('@stripe/stripe-js');
        const error = { message: 'Your card was declined.' };
        loadStripe.mockResolvedValue(fakeStripe(vi.fn().mockResolvedValue({ error })));

        const wrapper = mount(StripeCardForm, {
            props: { publishableKey: 'pk_test', clientSecret: 'secret_123' },
        });
        await flushPromises();

        await wrapper.find('button').trigger('click');
        await flushPromises();

        expect(wrapper.emitted('error')).toEqual([['Your card was declined.']]);
        expect(wrapper.text()).toContain('Your card was declined.');
        expect(wrapper.find('button').attributes('disabled')).toBeUndefined();
    });
});
