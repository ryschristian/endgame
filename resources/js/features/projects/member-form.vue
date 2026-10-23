<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/features/accounts/form-field.vue';
import RolePicker from './role-picker.vue';
import type { Option } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    projectId: number;
    revision: number;
    roleOptions: Option<number>[];
    disabled: boolean;
}>();
const mode = ref<'add' | 'invite'>('add');
const form = useForm({
    email: '',
    role_ids: [] as number[],
    revision: props.revision,
});
function submit() {
    if (props.disabled) return;
    form.transform((data) => ({ ...data, revision: props.revision })).post(
        `/projects/${props.projectId}/${mode.value === 'add' ? 'members' : 'invitations'}`,
        { preserveScroll: true, onSuccess: () => form.reset() },
    );
}
</script>
<template>
    <form class="space-y-4" @submit.prevent="submit">
        <h2 class="text-[14.5px] font-semibold">Add or invite a member</h2>
        <fieldset
            :disabled="disabled || form.processing"
            class="space-y-4 disabled:opacity-60"
        >
            <FormField
                id="member-email"
                v-model="form.email"
                label="Account email"
                type="email"
                :error="form.errors.email"
                required
            />
            <RolePicker v-model="form.role_ids" :options="roleOptions" />
            <fieldset class="flex flex-wrap gap-5 text-[12.5px]">
                <legend class="sr-only">Membership method</legend>
                <label class="flex items-center gap-2"
                    ><input v-model="mode" type="radio" value="add" />Add
                    immediately</label
                >
                <label class="flex items-center gap-2"
                    ><input v-model="mode" type="radio" value="invite" />Send
                    invitation</label
                >
            </fieldset>
            <p class="text-[12.5px] text-muted-foreground">
                Members need an active Endgame account. A System Admin can
                invite new accounts.
            </p>
        </fieldset>
        <p
            v-for="(message, key) in form.errors"
            :key="key"
            role="alert"
            class="text-[12.5px] text-destructive"
        >
            {{ message }}
        </p>
        <Button type="submit" :disabled="disabled || form.processing">{{
            mode === 'add' ? 'Add member' : 'Send invitation'
        }}</Button>
    </form>
</template>
