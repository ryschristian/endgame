<script setup lang="ts">
import { Button } from '@/components/ui/button';
import RolePicker from './role-picker.vue';
import ProjectConfirm from './project-confirm.vue';
import type { MemberRow, Option } from '@/types/projects';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<{
    member: MemberRow;
    projectId: number;
    revision: number;
    roleOptions: Option<number>[];
    disabled: boolean;
}>();
const editing = ref(false);
const removing = ref(false);
const form = useForm({
    role_ids: [...props.member.role_ids],
    revision: props.revision,
});
function save() {
    form.transform((data) => ({ ...data, revision: props.revision })).patch(
        `/projects/${props.projectId}/members/${props.member.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editing.value = false;
            },
        },
    );
}
function remove() {
    form.transform(() => ({ revision: props.revision })).delete(
        `/projects/${props.projectId}/members/${props.member.id}`,
        {
            preserveScroll: true,
            onSuccess: () => {
                removing.value = false;
            },
        },
    );
}
</script>
<template>
    <article class="space-y-4 border-t p-5">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="font-medium">
                    {{ member.name
                    }}<span
                        v-if="!member.is_active"
                        class="ml-2 text-xs text-muted-foreground"
                        >Suspended</span
                    >
                </h3>
                <p class="text-sm text-muted-foreground">{{ member.email }}</p>
                <p class="mt-2 text-sm">
                    {{ member.roleNames.join(', ') || 'No roles assigned' }}
                </p>
            </div>
            <div v-if="member.canManage" class="flex gap-2">
                <Button
                    variant="outline"
                    :disabled="disabled || form.processing"
                    @click="editing = !editing"
                    >Edit roles</Button
                ><Button
                    variant="outline"
                    :disabled="disabled || form.processing"
                    @click="removing = true"
                    >Remove</Button
                >
            </div>
        </div>
        <form
            v-if="editing"
            class="space-y-4 rounded-lg bg-muted/40 p-4"
            @submit.prevent="save"
        >
            <RolePicker
                v-model="form.role_ids"
                :options="roleOptions"
                :disabled="disabled || form.processing"
            /><Button type="submit" :disabled="disabled || form.processing"
                >Save member roles</Button
            >
        </form>
        <p
            v-if="!removing"
            v-for="(message, key) in form.errors"
            :key="key"
            role="alert"
            class="text-sm text-destructive"
        >
            {{ message }}
        </p>
        <ProjectConfirm
            :open="removing"
            title="Remove project member"
            :description="`Remove ${member.name} from this project? Their history will be retained.`"
            :busy="form.processing"
            @close="removing = false"
            @confirm="remove"
            ><p
                v-for="(message, key) in form.errors"
                :key="key"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p></ProjectConfirm
        >
    </article>
</template>
