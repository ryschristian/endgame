<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AccountLayout from '@/layouts/account-layout.vue';
import type { SharedPageProps } from '@/types/accounts';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
const props = defineProps<{
    valid: boolean;
    token?: string;
    invitation?: { projectName: string; roles: string[] };
}>();
const page = usePage<SharedPageProps>();
const form = useForm({});
function accept() {
    if (props.valid && props.token)
        form.post(`/project-invitations/${props.token}`);
}
</script>
<template>
    <Head title="Project invitation" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <section
            class="mx-auto max-w-xl space-y-5 rounded-xl border bg-card p-6 sm:p-8"
        >
            <template v-if="valid && invitation"
                ><h1 class="text-2xl font-semibold">
                    Join {{ invitation.projectName }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{
                        invitation.roles.length
                            ? `Project roles: ${invitation.roles.join(', ')}`
                            : 'No roles are assigned yet. A project manager can grant access after you join.'
                    }}
                </p>
                <form class="space-y-4" @submit.prevent="accept">
                    <p
                        v-for="(message, key) in form.errors"
                        :key="key"
                        role="alert"
                        class="text-sm text-destructive"
                    >
                        {{ message }}
                    </p>
                    <Button type="submit" :disabled="form.processing"
                        >Accept project invitation</Button
                    >
                </form></template
            >
            <template v-else
                ><h1 class="text-2xl font-semibold">Invitation unavailable</h1>
                <p class="text-sm text-muted-foreground">
                    Sign in with the invited account or ask a project manager
                    for a new invitation.
                </p></template
            >
            <Link href="/projects" class="inline-block text-sm underline"
                >Back to projects</Link
            >
        </section>
    </AccountLayout>
</template>
