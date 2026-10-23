<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/pages/_components/form-field.vue';
import type { Option, RoleRow } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';
const props = defineProps<{
    projectId: number;
    revision: number;
    role?: RoleRow;
    permissionOptions: Option[];
    defaultPermissionKeys: string[];
    disabled: boolean;
}>();
const emit = defineEmits<{ saved: [] }>();
const form = useForm({
    name: props.role?.name ?? '',
    permission_keys: [
        ...(props.role?.permission_keys ?? props.defaultPermissionKeys),
    ],
    revision: props.revision,
});
function submit() {
    if (props.disabled) return;
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            emit('saved');
        },
    };
    form.transform((data) => ({ ...data, revision: props.revision }));
    if (props.role)
        form.patch(
            `/projects/${props.projectId}/roles/${props.role.id}`,
            options,
        );
    else form.post(`/projects/${props.projectId}/roles`, options);
}
</script>
<template>
    <form class="space-y-4" @submit.prevent="submit">
        <fieldset
            :disabled="disabled || form.processing"
            class="space-y-4 disabled:opacity-60"
        >
            <FormField
                :id="`role-name-${role?.id ?? 'new'}`"
                v-model="form.name"
                label="Role name"
                :error="form.errors.name"
                required
            />
            <fieldset class="space-y-3">
                <legend class="mb-3 text-[12.5px] font-medium">
                    Permissions
                </legend>
                <label
                    v-for="option in permissionOptions"
                    :key="option.value"
                    class="flex items-center gap-3 text-[12.5px]"
                >
                    <input
                        v-model="form.permission_keys"
                        type="checkbox"
                        :value="option.value"
                        class="size-4 accent-primary"
                    />{{ option.label }}
                </label>
            </fieldset>
            <p
                v-if="form.errors.permission_keys"
                role="alert"
                class="text-[12.5px] text-destructive"
            >
                {{ form.errors.permission_keys }}
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
            role ? 'Save role' : 'Create role'
        }}</Button>
    </form>
</template>
