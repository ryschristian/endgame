<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AccessChangeDialog from '@/features/accounts/access-change-dialog.vue';
import FormField from '@/features/accounts/form-field.vue';
import PaginationControls from '@/features/accounts/pagination-controls.vue';
import AccountLayout from '@/layouts/account-layout.vue';
import type {
    AccountRow,
    InvitationRow,
    Paginated,
    SharedPageProps,
} from '@/types/accounts';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    users: Paginated<AccountRow>;
    invitations: Paginated<InvitationRow>;
    statusOptions: { value: string; label: string }[];
    filters: { q: string; status: string };
}>();
const page = usePage<SharedPageProps>();
const filters = ref({ ...props.filters });
const invite = useForm<{ email: string; delivery?: string }>({ email: '' });
const invitationAction = useForm<{ invitation?: string; delivery?: string }>(
    {},
);
const selected = ref<AccountRow | null>(null);
const kind = ref<'status' | 'administrator'>('status');

function filter() {
    router.get('/admin/accounts', filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
}
function change(user: AccountRow, action: 'status' | 'administrator') {
    kind.value = action;
    selected.value = user;
}
function actOnInvitation(
    invitation: InvitationRow,
    action: 'resend' | 'revoke',
) {
    invitationAction.clearErrors();
    invitationAction.post(`/admin/invitations/${invitation.id}/${action}`, {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head title="Accounts" />
    <AccountLayout
        v-if="page.props.auth.user"
        :user="page.props.auth.user"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <header>
            <h1 class="text-3xl font-semibold tracking-tight">Accounts</h1>
            <p class="mt-2 text-muted-foreground">
                Invite your team and manage application access.
            </p>
        </header>
        <section
            class="rounded-xl border bg-card p-5 sm:p-6"
            aria-labelledby="invite-title"
        >
            <h2 id="invite-title" class="text-[14.5px] font-semibold">
                Invite someone
            </h2>
            <p class="mt-1 text-[12.5px] text-muted-foreground">
                They’ll receive a link to create their account.
            </p>
            <form
                class="mt-5 flex flex-col items-start gap-3 sm:flex-row sm:items-end"
                @submit.prevent="
                    invite.post('/admin/invitations', {
                        preserveScroll: true,
                        onSuccess: () => invite.reset(),
                    })
                "
            >
                <div class="w-full sm:max-w-sm">
                    <FormField
                        id="invite-email"
                        v-model="invite.email"
                        label="Email"
                        type="email"
                        autocomplete="off"
                        :error="invite.errors.email"
                        required
                    />
                </div>
                <Button
                    type="submit"
                    class="min-h-9 sm:min-h-7.5"
                    :disabled="invite.processing"
                    >{{
                        invite.processing ? 'Sending…' : 'Send invitation'
                    }}</Button
                >
            </form>
            <p
                v-if="invite.errors.delivery"
                role="alert"
                class="mt-3 text-[12.5px] text-destructive"
            >
                {{ invite.errors.delivery }}
            </p>
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card"
            aria-labelledby="accounts-title"
        >
            <div class="space-y-4 border-b p-5 sm:p-6">
                <h2 id="accounts-title" class="text-[14.5px] font-semibold">
                    Team accounts
                    <span
                        class="text-[12.5px] font-normal text-muted-foreground"
                        >({{ users.total }})</span
                    >
                </h2>
                <form
                    class="flex flex-wrap items-end gap-3"
                    @submit.prevent="filter"
                >
                    <div class="min-w-0 flex-1 space-y-2">
                        <label
                            for="account-search"
                            class="block text-[12.5px] font-medium"
                            >Search name or email</label
                        ><input
                            id="account-search"
                            v-model="filters.q"
                            type="search"
                            class="h-9 w-full rounded-md border border-input bg-card px-2.5 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/10 sm:h-7.5 sm:text-[13.5px]"
                        />
                    </div>
                    <div class="space-y-2">
                        <label
                            for="account-status"
                            class="block text-[12.5px] font-medium"
                            >Status</label
                        ><select
                            id="account-status"
                            v-model="filters.status"
                            class="h-9 rounded-md border border-input bg-card px-2.5 text-base outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/10 sm:h-7.5 sm:text-[13.5px]"
                        >
                            <option
                                v-for="option in statusOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                    <Button
                        type="submit"
                        variant="outline"
                        class="min-h-9 sm:min-h-7.5"
                        >Apply filters</Button
                    >
                </form>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-[12.5px]">
                    <caption class="sr-only">
                        Application accounts and available access changes
                    </caption>
                    <thead class="border-b bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="px-5 py-3 font-medium">Person</th>
                            <th class="px-5 py-3 font-medium">Status</th>
                            <th class="px-5 py-3 font-medium">Access</th>
                            <th class="px-5 py-3 font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="user in users.data"
                            :key="user.id"
                            class="border-b last:border-0"
                        >
                            <td class="px-5 py-4">
                                <span class="block font-medium">{{
                                    user.name
                                }}</span
                                ><span class="text-muted-foreground">{{
                                    user.email
                                }}</span>
                            </td>
                            <td class="px-5 py-4">{{ user.statusLabel }}</td>
                            <td class="px-5 py-4 whitespace-nowrap">
                                {{ user.authorityLabel }}
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        class="min-h-9 sm:min-h-7.5"
                                        :disabled="!user.canChangeStatus"
                                        :title="
                                            !user.canChangeStatus
                                                ? (user.restriction ??
                                                  undefined)
                                                : undefined
                                        "
                                        @click="change(user, 'status')"
                                        >{{
                                            user.is_active
                                                ? 'Suspend'
                                                : 'Activate'
                                        }}</Button
                                    ><Button
                                        variant="outline"
                                        class="min-h-9 sm:min-h-7.5"
                                        :disabled="!user.canChangeAdministrator"
                                        :title="
                                            !user.canChangeAdministrator
                                                ? (user.restriction ??
                                                  undefined)
                                                : undefined
                                        "
                                        @click="change(user, 'administrator')"
                                        >{{
                                            user.is_system_admin
                                                ? 'Remove admin'
                                                : 'Make admin'
                                        }}</Button
                                    >
                                </div>
                                <p
                                    v-if="user.restriction"
                                    class="mt-2 max-w-xs text-xs text-muted-foreground"
                                >
                                    {{ user.restriction }}
                                </p>
                            </td>
                        </tr>
                        <tr v-if="!users.data.length">
                            <td
                                colspan="4"
                                class="px-5 py-10 text-center text-muted-foreground"
                            >
                                No accounts match your filters.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <PaginationControls
                :current-page="users.current_page"
                :last-page="users.last_page"
                :previous="users.prev_page_url"
                :next="users.next_page_url"
                label="Account pages"
            />
        </section>

        <section
            class="overflow-hidden rounded-xl border bg-card"
            aria-labelledby="invitations-title"
        >
            <div class="border-b p-5 sm:p-6">
                <h2 id="invitations-title" class="text-[14.5px] font-semibold">
                    Invitations
                </h2>
                <p class="mt-1 text-[12.5px] text-muted-foreground">
                    Resending replaces the previous link. Revoking prevents
                    acceptance.
                </p>
                <p
                    v-if="
                        invitationAction.errors.invitation ||
                        invitationAction.errors.delivery
                    "
                    role="alert"
                    class="mt-3 text-[12.5px] text-destructive"
                >
                    {{
                        invitationAction.errors.invitation ||
                        invitationAction.errors.delivery
                    }}
                </p>
            </div>
            <ul class="divide-y">
                <li
                    v-for="invitation in invitations.data"
                    :key="invitation.id"
                    class="flex flex-wrap items-center justify-between gap-4 px-5 py-4"
                >
                    <div class="min-w-0">
                        <p class="font-medium break-all">
                            {{ invitation.email }}
                        </p>
                        <p class="mt-1 text-[12.5px] text-muted-foreground">
                            {{ invitation.statusLabel }} · Expires
                            {{
                                new Date(invitation.expires_at).toLocaleString()
                            }}
                        </p>
                    </div>
                    <div class="flex gap-2">
                        <Button
                            variant="outline"
                            class="min-h-9 sm:min-h-7.5"
                            :disabled="invitationAction.processing"
                            :aria-label="`Resend invitation to ${invitation.email}`"
                            @click="actOnInvitation(invitation, 'resend')"
                            >Resend</Button
                        ><Button
                            variant="outline"
                            class="min-h-9 sm:min-h-7.5"
                            :disabled="
                                !invitation.canRevoke ||
                                invitationAction.processing
                            "
                            :aria-label="`Revoke invitation to ${invitation.email}`"
                            @click="actOnInvitation(invitation, 'revoke')"
                            >Revoke</Button
                        >
                    </div>
                </li>
                <li
                    v-if="!invitations.data.length"
                    class="px-5 py-10 text-center text-[12.5px] text-muted-foreground"
                >
                    No pending invitations. Invite someone above to get started.
                </li>
            </ul>
            <PaginationControls
                :current-page="invitations.current_page"
                :last-page="invitations.last_page"
                :previous="invitations.prev_page_url"
                :next="invitations.next_page_url"
                label="Invitation pages"
            />
        </section>
        <AccessChangeDialog
            v-if="selected"
            :key="`${selected.id}-${kind}`"
            :target="selected"
            :kind="kind"
            @close="selected = null"
        />
    </AccountLayout>
</template>
