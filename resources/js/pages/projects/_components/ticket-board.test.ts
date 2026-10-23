import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import TicketBoard from './ticket-board.vue';
import type { TicketRow } from '@/pages/tickets/_components/types';

const ticket = {
    id: 1,
    key: 'WEB-1',
    summary: 'Homepage',
    state: 'queued',
    stateLabel: 'Queued',
    priority: { id: 1, name: 'Medium' },
    workType: { id: 1, name: 'Task' },
    assignee: null,
    due_date: null,
    allowedStates: ['review'],
    revision: 1,
} as TicketRow;
it('renders server stages and requests a permitted move through its keyboard control', async () => {
    const wrapper = mount(TicketBoard, {
        props: {
            rows: [ticket],
            statuses: [
                {
                    key: 'queued',
                    label: 'Queued',
                    color: '#aaaaaa',
                    completed: false,
                },
                {
                    key: 'review',
                    label: 'QA check',
                    color: '#888888',
                    completed: false,
                },
            ],
            counts: { queued: 1 },
            canUpdate: true,
            canCreate: true,
            busy: false,
            manual: true,
        },
    });
    expect(wrapper.text()).toContain('QA check');
    await wrapper.get('select[aria-label="Move WEB-1"]').setValue('review');
    expect(wrapper.emitted('move')?.[0]).toEqual([
        { ticket, state: 'review', beforeId: null },
    ]);
    await wrapper.get('[aria-label="Open WEB-1: Homepage"]').trigger('click');
    expect(wrapper.emitted('open')?.[0]).toEqual([1]);
});

it('hides mutation controls for readers', () => {
    const wrapper = mount(TicketBoard, {
        props: {
            rows: [ticket],
            statuses: [
                {
                    key: 'queued',
                    label: 'Queued',
                    color: '#aaaaaa',
                    completed: false,
                },
            ],
            counts: {},
            canUpdate: false,
            canCreate: false,
            busy: false,
            manual: true,
        },
    });
    expect(wrapper.find('select').exists()).toBe(false);
    expect(wrapper.find('[draggable="true"]').exists()).toBe(false);
    expect(wrapper.text()).not.toContain('Add ticket');
});
