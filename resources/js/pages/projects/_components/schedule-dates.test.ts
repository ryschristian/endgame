import { expect, it } from 'vitest';
import { addDays, monthDays, moveDates } from './schedule-dates';

it('uses date-only arithmetic across month and year boundaries', () => {
    expect(addDays('2026-12-31', 1)).toBe('2027-01-01');
    expect(monthDays('2026-10')).toHaveLength(42);
    expect(monthDays('2026-10')[0]).toBe('2026-09-28');
    expect(
        moveDates(
            { start_date: '2026-10-01', due_date: '2026-10-04' },
            '2026-11-01',
        ),
    ).toEqual({ start_date: '2026-11-01', due_date: '2026-11-04' });
    expect(
        moveDates({ start_date: null, due_date: null }, '2026-11-01'),
    ).toEqual({ start_date: '2026-11-01', due_date: '2026-11-01' });
});
