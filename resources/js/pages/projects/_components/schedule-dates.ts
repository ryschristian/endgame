export function addDays(date: string, days: number): string {
    const value = new Date(`${date}T00:00:00Z`);
    value.setUTCDate(value.getUTCDate() + days);
    return value.toISOString().slice(0, 10);
}
export function monthDays(month: string): string[] {
    const first = `${month}-01`;
    const day = new Date(`${first}T00:00:00Z`).getUTCDay();
    const start = addDays(first, -((day + 6) % 7));
    return Array.from({ length: 42 }, (_, index) => addDays(start, index));
}
export function moveDates(
    ticket: { start_date?: string | null; due_date: string | null },
    start: string,
) {
    const oldStart = ticket.start_date ?? ticket.due_date;
    const length =
        oldStart && ticket.due_date
            ? Math.max(
                  0,
                  Math.round(
                      (Date.parse(ticket.due_date) - Date.parse(oldStart)) /
                          86400000,
                  ),
              )
            : 0;
    return { start_date: start, due_date: addDays(start, length) };
}
