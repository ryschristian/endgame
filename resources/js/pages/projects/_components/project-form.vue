<script setup lang="ts">
import { Button } from '@/components/ui/button';
import FormField from '@/pages/_components/form-field.vue';
import type { ProjectRow } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    project?: ProjectRow;
    teamTimezone: string;
}>();
const form = useForm({
    key: '',
    name: props.project?.name ?? '',
    description: props.project?.description ?? '',
    manager_email: '',
    revision: props.project?.revision,
});
const emit = defineEmits<{ saved: [] }>();
function submit() {
    if (props.project?.archived_at) return;
    if (props.project) {
        form.transform(
            ({ key: _key, manager_email: _manager, ...data }) => data,
        ).patch(`/projects/${props.project.id}`, {
            preserveScroll: true,
            onSuccess: () => emit('saved'),
        });
    } else {
        form.transform(({ revision: _revision, ...data }) => data).post(
            '/projects',
        );
    }
}
</script>

<template>
    <form class="space-y-4" @submit.prevent="submit">
        <fieldset
            :disabled="Boolean(project?.archived_at) || form.processing"
            class="space-y-4 disabled:opacity-60"
        >
            <FormField
                v-if="!project"
                id="project-key"
                v-model="form.key"
                label="Project key"
                hint="2–10 letters or numbers, starting with a letter. This key is permanent."
                :error="form.errors.key"
                required
            />
            <FormField
                id="project-name"
                v-model="form.name"
                label="Project name"
                :error="form.errors.name"
                required
            />
            <div class="space-y-1.5">
                <label
                    for="project-description"
                    class="block text-[12.5px] font-medium"
                    >Description</label
                >
                <textarea
                    id="project-description"
                    v-model="form.description"
                    rows="3"
                    maxlength="10000"
                    class="min-h-18 w-full resize-y rounded-md border border-input bg-card px-2.5 py-2 text-base leading-relaxed outline-none focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/10 sm:text-[13.5px]"
                    :aria-invalid="Boolean(form.errors.description)"
                    :aria-describedby="
                        form.errors.description
                            ? 'project-description-error'
                            : undefined
                    "
                />
                <p
                    v-if="form.errors.description"
                    id="project-description-error"
                    role="alert"
                    class="text-xs text-destructive"
                >
                    {{ form.errors.description }}
                </p>
            </div>
            <FormField
                v-if="!project"
                id="manager-email"
                v-model="form.manager_email"
                label="Initial manager email"
                type="email"
                hint="Choose an existing active account."
                :error="
                    form.errors.manager_email ||
                    (form.errors as Record<string, string>).email
                "
                required
            />
        </fieldset>
        <div
            class="flex items-center justify-between border-t pt-4 text-xs text-muted-foreground"
        >
            <span>Team timezone</span
            ><span>{{ teamTimezone.replace('_', ' ') }} · UTC+08:00</span>
        </div>
        <p
            v-if="form.errors.revision"
            role="alert"
            class="text-xs text-destructive"
        >
            {{ form.errors.revision }}
        </p>
        <Button
            type="submit"
            class="ml-auto flex"
            :disabled="form.processing || Boolean(project?.archived_at)"
            >{{
                form.processing
                    ? 'Saving…'
                    : project
                      ? 'Save settings'
                      : 'Create project'
            }}</Button
        >
    </form>
</template>
