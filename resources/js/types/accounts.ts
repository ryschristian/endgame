export interface AccountUser {
    id: number;
    name: string;
    email: string;
}

export interface SharedPageProps {
    [key: string]: unknown;
    appName: string;
    auth: { user: AccountUser | null; canManageAccounts: boolean };
    flash: { status: string | null };
    passwordHint: string;
}

export interface AccountRow extends AccountUser {
    is_active: boolean;
    is_system_admin: boolean;
    statusLabel: string;
    authorityLabel: string;
    canChangeStatus: boolean;
    canChangeAdministrator: boolean;
    restriction: string | null;
}

export interface InvitationRow {
    id: number;
    email: string;
    expires_at: string;
    statusLabel: string;
    canRevoke: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}
