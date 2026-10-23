import type { Paginated } from '@/types/accounts';
import type { ProjectPageProps } from '@/types/projects';
import type { Workflow } from '@/pages/projects/_components/workspace-types';

export interface Choice {
    value: number;
    label: string;
    disabled?: boolean;
    default?: boolean;
}
export interface Catalog {
    id: number;
    name: string;
    position: number;
    is_active: boolean | number;
    is_default: boolean | number;
}
export interface TicketRow {
    id: number;
    project_id: number;
    number: number;
    key: string;
    summary: string;
    work_type_id: number;
    priority_id: number;
    assignee_id: number | null;
    estimate: string | null;
    due_date: string | null;
    updated_at: string;
    revision: number;
    stateLabel: string;
    state?: string;
    stateColor?: string;
    completed?: boolean;
    start_date?: string | null;
    resolution?: string | null;
    completed_at?: string | null;
    workflow_version_id?: number;
    board_rank?: number;
    allowedStates?: string[];
    workType: { id: number; name: string };
    priority: { id: number; name: string };
    assignee: { id: number; name: string } | null;
}
export interface TicketDetail extends TicketRow {
    description: string | null;
    acceptance_criteria: string | null;
    reporter: { id: number; name: string };
    assigneeAvailable: boolean;
}
export interface HistoryEvent {
    id: number;
    actor: string;
    action: string;
    created_at: string;
    changes: {
        reason?: string | null;
        before: Record<string, unknown> | null;
        after: Record<string, unknown>;
    };
}
export interface TicketProps extends ProjectPageProps {
    workflow?: Workflow;
    boardRevision?: number;
    selectedAssignee?: { id: number; name: string } | null;
    ticketCan: {
        view: boolean;
        create: boolean;
        update: boolean;
        configure: boolean;
    };
    options: { workTypes: Choice[]; priorities: Choice[] };
    defaults: { work_type_id: number; priority_id: number };
    fieldLabels: Record<string, string>;
    catalogs: { workTypes: Catalog[]; priorities: Catalog[] };
    catalogRevision: number;
    selectedTicket: TicketDetail | null;
    history: Paginated<HistoryEvent> | null;
}
export interface Filters {
    q: string;
    work_type_id: string | number;
    priority_id: string | number;
    assignee_id: string | number;
    due_from: string;
    due_to: string;
    sort: string;
    direction: string;
    per_page?: number;
}
export function teamDate(value: string | null, time = false): string {
    if (!value) return '—';
    return new Intl.DateTimeFormat('en-PH', {
        timeZone: 'Asia/Manila',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        ...(time
            ? { hour: 'numeric' as const, minute: '2-digit' as const }
            : {}),
    }).format(
        new Date(value.length === 10 ? `${value}T00:00:00+08:00` : value),
    );
}
