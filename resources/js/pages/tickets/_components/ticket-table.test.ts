import { mount } from '@vue/test-utils';
import { it, expect } from 'vitest';
import TicketTable from './ticket-table.vue';
import type { TicketRow } from './types';

it('uses the supplied detail URL and delegates sorting without reordering server rows', async () => {
    const row: TicketRow = {
        id: 1,
        project_id: 2,
        number: 5,
        key: 'TEAM-00005',
        summary: 'Review the design',
        work_type_id: 1,
        priority_id: 1,
        assignee_id: null,
        estimate: null,
        due_date: null,
        updated_at: '2026-10-23T00:00:00Z',
        revision: 1,
        stateLabel: 'Open',
        workType: { id: 1, name: 'Story' },
        priority: { id: 1, name: 'Normal' },
        assignee: null,
    };
    const wrapper = mount(TicketTable, {
        props: {
            rows: [row],
            projectId: 2,
            sort: 'number',
            direction: 'asc',
            ticketUrl: (id) => `/projects/2/tickets?page=2&ticket=${id}`,
        },
    });
    expect(wrapper.get('a').attributes('href')).toContain('page=2&ticket=1');
    await wrapper
        .findAll('button')
        .find((button) => button.text().includes('Priority'))!
        .trigger('click');
    expect(wrapper.emitted('sort')?.[0]).toEqual(['priority']);
    expect(wrapper.text()).toContain('TEAM-00005');
});
