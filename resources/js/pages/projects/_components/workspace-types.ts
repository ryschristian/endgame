import type { TicketProps, TicketRow } from '@/pages/tickets/_components/types';
import type { Paginated } from '@/types/accounts';

export interface WorkflowStatus {
    key: string;
    label: string;
    color: string;
    completed: boolean;
}
export interface WorkflowDefinition {
    initial: string;
    statuses: WorkflowStatus[];
    transitions: { from: string; to: string }[];
    resolutions: { key: string; label: string }[];
}
export interface Workflow {
    id: number;
    version: number;
    definition: WorkflowDefinition;
}
export interface WorkspaceFilters {
    q: string;
    state: string;
    work_type_id: number | string;
    priority_id: number | string;
    assignee_id: number | string;
    sort: string;
    direction: string;
    month: string;
}
export interface WorkspaceProps extends TicketProps {
    workflow: Workflow;
    boardRevision: number;
    view: 'board' | 'list' | 'table' | 'calendar' | 'timeline';
    tickets: Paginated<TicketRow>;
    filters: WorkspaceFilters;
    statusCounts: Record<string, number>;
    sortOptions: { value: string; label: string }[];
}
export interface MoveRequest {
    ticket: TicketRow;
    state: string;
    beforeId: number | null;
}
