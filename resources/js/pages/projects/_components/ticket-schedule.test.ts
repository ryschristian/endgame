import { mount } from '@vue/test-utils';
import { expect, it } from 'vitest';
import TicketSchedule from './ticket-schedule.vue';
import type { TicketRow } from '@/pages/tickets/_components/types';

it('schedules an unscheduled ticket by dropping it on its timeline date lane', async () => {
    const ticket = {
        id: 9,
        key: 'WEB-9',
        summary: 'Unscheduled work',
        due_date: null,
        start_date: null,
    } as TicketRow;
    const wrapper = mount(TicketSchedule, {
        props: {
            rows: [ticket],
            month: '2026-10',
            today: '2026-10-23',
            timeline: true,
            canUpdate: true,
            busy: false,
        },
    });
    await wrapper.get('[draggable="true"]').trigger('dragstart', {
        dataTransfer: { setData: () => {}, effectAllowed: '' },
    });
    await wrapper
        .get('[data-timeline-ticket="9"] [data-timeline-date="2026-10-25"]')
        .trigger('drop');
    expect(wrapper.emitted('schedule')?.[0]).toEqual([
        ticket,
        '2026-10-25',
        '2026-10-25',
    ]);
});
