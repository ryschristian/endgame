import type { Paginated } from './accounts';

export interface Option<T = string> {
    value: T;
    label: string;
}
export interface ProjectRow {
    id: number;
    key: string;
    name: string;
    description: string | null;
    timezone: string;
    archived_at: string | null;
    revision: number;
    statusLabel?: string;
}
export interface ProjectAccess {
    view: boolean;
    update: boolean;
    members: boolean;
    roles: boolean;
    archive: boolean;
    restore: boolean;
}
export interface ProjectListRow extends Omit<ProjectRow, 'revision'> {
    statusLabel: string;
    updated_at: string;
    members_count: number;
    memberPreviews: { id: number; name: string }[];
}
export interface MemberRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    role_ids: number[];
    roleNames: string[];
    canManage: boolean;
}
export interface RoleRow {
    id: number;
    name: string;
    permission_keys: string[];
    permissionLabels: string[];
    members_count: number;
    canManage: boolean;
}
export interface ProjectInvitationRow {
    id: number;
    email: string;
    role_ids: number[];
    roleNames: string[];
    statusLabel: string;
    canRevoke: boolean;
    canResend: boolean;
}
export interface ProjectPageProps {
    project: ProjectRow;
    can: ProjectAccess;
}
export type ProjectMembers = Paginated<MemberRow>;
