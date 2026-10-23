<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/features/accounts/form-field.vue';
import type { AccountRow } from '@/types/accounts';
import { useForm } from '@inertiajs/vue3';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
import { computed } from 'vue';

const props = defineProps<{
    target: AccountRow | null;
    kind: 'status' | 'administrator';
}>();
const emit = defineEmits<{ close: [] }>();
const form = useForm<{ current_password: string; account?: string }>({
    current_password: '',
});
const title = computed(() =>
    props.kind === 'status'
        ? props.target?.is_active
            ? 'Suspend account'
            : 'Activate account'
        : props.target?.is_system_admin
          ? 'Remove administrator access'
          : 'Grant administrator access',
);

function close(open: boolean) {
    if (!open && !form.processing) {
        form.reset();
        form.clearErrors();
        emit('close');
    }
}
function submit() {
    if (!props.target) return;
    const target = props.target;
    const path = props.kind === 'status' ? 'status' : 'administrator';
    form.transform((data) => ({
        ...data,
        ...(props.kind === 'status'
            ? { is_active: !target.is_active }
            : { is_system_admin: !target.is_system_admin }),
    })).patch(`/admin/accounts/${target.id}/${path}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
        onFinish: () => form.reset('current_password'),
    });
}
</script>

<template>
    <DialogRoot :open="target !== null" @update:open="close">
        <DialogPortal>
            <DialogOverlay class="fixed inset-0 z-40 bg-black/45" />
            <DialogContent
                class="fixed top-1/2 left-1/2 z-50 w-[calc(100%-2rem)] max-w-md -translate-x-1/2 -translate-y-1/2 space-y-4 rounded-xl border bg-background p-6 shadow-xl"
            >
                <DialogTitle class="text-xl font-semibold">{{
                    title
                }}</DialogTitle>
                <DialogDescription
                    class="text-[12.5px] leading-6 text-muted-foreground"
                    >Confirm this change for {{ target?.email }} with your
                    current password. Changes are recorded in the account
                    history.</DialogDescription
                >
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField
                        id="confirm-current-password"
                        v-model="form.current_password"
                        label="Current password"
                        type="password"
                        autocomplete="current-password"
                        :error="form.errors.current_password"
                        required
                    />
                    <p
                        v-if="form.errors.account"
                        role="alert"
                        class="text-[12.5px] text-destructive"
                    >
                        {{ form.errors.account }}
                    </p>
                    <div class="flex justify-end gap-3">
                        <DialogClose as-child
                            ><Button
                                type="button"
                                variant="outline"
                                class="min-h-9 sm:min-h-7.5"
                                :disabled="form.processing"
                                >Cancel</Button
                            ></DialogClose
                        >
                        <Button
                            type="submit"
                            class="min-h-9 sm:min-h-7.5"
                            :disabled="form.processing"
                            >{{
                                form.processing ? 'Updating…' : 'Confirm change'
                            }}</Button
                        >
                    </div>
                </form>
            </DialogContent>
        </DialogPortal>
    </DialogRoot>
</template>
