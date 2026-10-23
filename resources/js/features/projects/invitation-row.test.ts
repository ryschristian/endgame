import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import InvitationRow from './invitation-row.vue';

describe('project invitation reissue', () => {
    it('offers resend for an accepted invitation when the backend allows rejoining', () => {
        const wrapper = mount(InvitationRow, {
            props: {
                invitation: {
                    id: 1,
                    email: 'removed@example.test',
                    role_ids: [],
                    roleNames: [],
                    statusLabel: 'Accepted',
                    canRevoke: false,
                    canResend: true,
                },
                projectId: 1,
                revision: 3,
                roleOptions: [],
                disabled: false,
            },
        });
        expect(
            wrapper.findAll('button').map((button) => button.text()),
        ).toContain('Resend');
        expect(
            wrapper.findAll('button').map((button) => button.text()),
        ).not.toContain('Revoke');
    });
});
