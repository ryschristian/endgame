<script setup lang="ts">
import { Button } from '@/components/ui/button';
import RolePicker from './role-picker.vue';
import type { Option, ProjectInvitationRow } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{
    invitation: ProjectInvitationRow;
    projectId: number;
    revision: number;
    roleOptions: Option<number>[];
    disabled: boolean;
}>();
const form = useForm({
    revision: props.revision,
    role_ids: props.invitation.role_ids.filter((id) =>
        props.roleOptions.some((option) => option.value === id),
    ),
});
function submit(action: 'resend' | 'revoke') {
    form.transform((data) => ({ ...data, revision: props.revision })).post(
        `/projects/${props.projectId}/invitations/${props.invitation.id}/${action}`,
        { preserveScroll: true },
    );
}
</script>
<template>
    <article class="space-y-4 border-t p-5">
        <div class="flex flex-wrap justify-between gap-2">
            <h3 class="font-medium">{{ invitation.email }}</h3>
            <span class="text-sm text-muted-foreground">{{
                invitation.statusLabel
            }}</span>
        </div>
        <p class="text-sm text-muted-foreground">
            {{ invitation.roleNames.join(', ') || 'No roles selected' }}
        </p>
        <template v-if="invitation.canResend || invitation.canRevoke">
            <RolePicker
                v-model="form.role_ids"
                :options="roleOptions"
                :disabled="disabled || form.processing"
            />
            <div class="flex gap-2">
                <Button
                    v-if="invitation.canResend"
                    variant="outline"
                    :disabled="disabled || form.processing"
                    @click="submit('resend')"
                    >Resend</Button
                ><Button
                    v-if="invitation.canRevoke"
                    variant="outline"
                    :disabled="disabled || form.processing"
                    @click="submit('revoke')"
                    >Revoke</Button
                >
            </div>
        </template>
        <p
            v-for="(message, key) in form.errors"
            :key="key"
            role="alert"
            class="text-sm text-destructive"
        >
            {{ message }}
        </p>
    </article>
</template>
