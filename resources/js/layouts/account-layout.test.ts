import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AccountLayout from './account-layout.vue';

describe('account navigation', () => {
    it.each([false, true])(
        'only offers account administration when the server permits it (%s)',
        (canManageAccounts) => {
            const wrapper = mount(AccountLayout, {
                props: {
                    user: {
                        id: 1,
                        name: 'Person',
                        email: 'person@example.com',
                    },
                    canManageAccounts,
                },
            });
            expect(wrapper.find('a[href="/admin/accounts"]').exists()).toBe(
                canManageAccounts,
            );
            expect(wrapper.find('a[href="/account"]').exists()).toBe(true);
            expect(wrapper.text()).toContain('Sign out');
        },
    );
});
