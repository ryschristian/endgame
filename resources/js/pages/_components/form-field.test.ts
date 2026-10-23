import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import FormField from './form-field.vue';

describe('account form fields', () => {
    it('associates errors with the input and emits edited values', async () => {
        const wrapper = mount(FormField, {
            props: {
                id: 'email',
                label: 'Email',
                modelValue: '',
                type: 'email',
                error: 'Enter a valid email.',
            },
        });
        expect(wrapper.get('label').attributes('for')).toBe('email');
        expect(wrapper.get('input').attributes('aria-invalid')).toBe('true');
        expect(wrapper.get('input').attributes('aria-describedby')).toBe(
            'email-error',
        );
        expect(wrapper.get('#email-error').text()).toBe('Enter a valid email.');
        await wrapper.get('input').setValue('person@example.com');
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
            'person@example.com',
        ]);
    });

    it('keeps read-only identities immutable in the form', () => {
        const wrapper = mount(FormField, {
            props: {
                id: 'identity',
                label: 'Email',
                modelValue: 'fixed@example.com',
                readonly: true,
            },
        });
        expect(wrapper.get('input').attributes()).toHaveProperty('readonly');
    });
});
