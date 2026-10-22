<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/features/accounts/form-field.vue';
import AccountLayout from '@/layouts/account-layout.vue';
import type { SharedPageProps } from '@/types/accounts';
import { Head, useForm, usePage } from '@inertiajs/vue3';

const page = usePage<SharedPageProps>();
const profile = useForm({ name: page.props.auth.user?.name ?? '' });
const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
function changePassword() {
    password.put('/account/password', {
        preserveScroll: true,
        onFinish: () => password.reset(),
    });
}
</script>

<template>
    <Head title="My account" />
    <AccountLayout
        v-if="page.props.auth.user"
        :user="page.props.auth.user"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <header>
            <h1 class="text-3xl font-semibold tracking-tight">My account</h1>
            <p class="mt-2 text-muted-foreground">
                Manage your profile and keep your account secure.
            </p>
        </header>
        <div class="grid items-start gap-6 md:grid-cols-2">
            <section
                class="space-y-6 rounded-xl border bg-card p-6"
                aria-labelledby="profile-title"
            >
                <div>
                    <h2 id="profile-title" class="text-lg font-semibold">
                        Profile
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Your name is visible to your team.
                    </p>
                </div>
                <form
                    class="space-y-5"
                    @submit.prevent="
                        profile.patch('/account', { preserveScroll: true })
                    "
                >
                    <FormField
                        id="name"
                        v-model="profile.name"
                        label="Name"
                        autocomplete="name"
                        :error="profile.errors.name"
                        required
                    />
                    <div class="space-y-2">
                        <label for="account-email" class="text-sm font-medium"
                            >Email</label
                        ><input
                            id="account-email"
                            :value="page.props.auth.user.email"
                            type="email"
                            readonly
                            class="h-11 w-full rounded-md border bg-muted px-3 text-muted-foreground"
                        />
                        <p class="text-sm text-muted-foreground">
                            Your sign-in email is fixed for this account.
                        </p>
                    </div>
                    <Button
                        type="submit"
                        class="min-h-11"
                        :disabled="profile.processing"
                        >{{
                            profile.processing ? 'Saving…' : 'Save profile'
                        }}</Button
                    >
                </form>
            </section>
            <section
                class="space-y-6 rounded-xl border bg-card p-6"
                aria-labelledby="password-title"
            >
                <div>
                    <h2 id="password-title" class="text-lg font-semibold">
                        Change password
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Other sessions will be signed out after this change.
                    </p>
                </div>
                <form class="space-y-5" @submit.prevent="changePassword">
                    <FormField
                        id="current_password"
                        v-model="password.current_password"
                        label="Current password"
                        type="password"
                        autocomplete="current-password"
                        :error="password.errors.current_password"
                        required
                    />
                    <FormField
                        id="new_password"
                        v-model="password.password"
                        label="New password"
                        type="password"
                        autocomplete="new-password"
                        :hint="page.props.passwordHint"
                        :error="password.errors.password"
                        required
                    />
                    <FormField
                        id="password_confirmation"
                        v-model="password.password_confirmation"
                        label="Confirm new password"
                        type="password"
                        autocomplete="new-password"
                        :error="password.errors.password_confirmation"
                        required
                    />
                    <Button
                        type="submit"
                        class="min-h-11"
                        :disabled="password.processing"
                        >{{
                            password.processing
                                ? 'Changing…'
                                : 'Change password'
                        }}</Button
                    >
                </form>
            </section>
        </div>
    </AccountLayout>
</template>
