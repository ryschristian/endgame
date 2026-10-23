import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { describe, it, expect, vi, afterEach } from 'vitest';
import TicketForm from './ticket-form.vue';

afterEach(() => vi.restoreAllMocks());
const props = {
    projectId: 1,
    disabled: false,
    options: {
        workTypes: [{ value: 3, label: 'Investigation' }],
        priorities: [{ value: 7, label: 'Normal' }],
    },
    defaults: { work_type_id: 3, priority_id: 7 },
    labels: {
        summary: 'Ticket summary',
        description: 'Description',
        acceptance_criteria: 'Acceptance criteria',
        estimate: 'Estimate (hours)',
        due_date: 'Due date',
        work_type_id: 'Type',
        priority_id: 'Priority',
        assignee_id: 'Assignee',
    },
};
describe('ticket form', () => {
    it('submits server defaults and editable fields without lifecycle or identity fields', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const wrapper = mount(TicketForm, { props });
        await wrapper.get('#ticket-summary').setValue('Investigate payment');
        await wrapper.get('form').trigger('submit');
        expect(post).toHaveBeenCalledWith(
            '/projects/1/tickets',
            expect.objectContaining({
                summary: 'Investigate payment',
                work_type_id: 3,
                priority_id: 7,
            }),
            expect.any(Object),
        );
        expect(post.mock.calls[0]?.[1]).not.toHaveProperty('state');
        expect(post.mock.calls[0]?.[1]).not.toHaveProperty('reporter_id');
    });
    it('disables writes for archived projects', () => {
        const wrapper = mount(TicketForm, {
            props: { ...props, disabled: true },
        });
        expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();
        expect(
            wrapper.get('button[type="submit"]').attributes('disabled'),
        ).toBeDefined();
    });
});
