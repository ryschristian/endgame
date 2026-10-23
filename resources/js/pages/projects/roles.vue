<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from '@/features/projects/project-header.vue';
import RoleForm from '@/features/projects/role-form.vue';
import ProjectConfirm from '@/features/projects/project-confirm.vue';
import PaginationControls from '@/features/accounts/pagination-controls.vue';
import type { SharedPageProps, Paginated } from '@/types/accounts';
import type { ProjectPageProps, RoleRow, Option } from '@/types/projects';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<
    ProjectPageProps & {
        roles: Paginated<RoleRow>;
        permissionOptions: Option[];
        defaultPermissionKeys: string[];
    }
>();
const page = usePage<SharedPageProps>();
const editing = ref<number | null>(null);
const deleting = ref<RoleRow | null>(null);
const deletion = useForm({ revision: props.project.revision });

function remove() {
    if (!deleting.value) return;
    deletion
        .transform(() => ({ revision: props.project.revision }))
        .delete(`/projects/${props.project.id}/roles/${deleting.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                deleting.value = null;
            },
        });
}
</script>
<template>
    <Head :title="`${project.name} · Roles`" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <ProjectHeader :project="project" :can="can" />
        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
            <section class="space-y-5 rounded-xl border bg-card p-6">
                <h2 class="text-lg font-semibold">Create role</h2>
                <RoleForm
                    :key="project.revision"
                    :project-id="project.id"
                    :revision="project.revision"
                    :permission-options="permissionOptions"
                    :default-permission-keys="defaultPermissionKeys"
                    :disabled="Boolean(project.archived_at)"
                />
            </section>
            <section class="overflow-hidden rounded-xl border bg-card">
                <h2 class="p-5 text-lg font-semibold">Project roles</h2>
                <article v-for="role in roles.data" :key="role.id" class="space-y-4 border-t p-5">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h3 class="font-semibold">{{ role.name }}</h3>
                            <p class="mt-1 text-sm text-muted-foreground">{{ role.members_count }} assigned members </p>
                        </div>
                        <div v-if="role.canManage" class="flex gap-2">
                            <Button variant="outline" :disabled="Boolean(project.archived_at)" @click=" editing =
                                        editing === role.id ? null : role.id">Edit</Button>
                            <Button variant="outline" :disabled=" Boolean(project.archived_at) || role.members_count > 0"
                                @click="deleting = role;deletion.clearErrors();">Delete</Button
                            >
                        </div>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ role.permissionLabels.join(' · ') }}</p>
                    <RoleForm
                        v-if="editing === role.id"
                        :key="`${project.revision}-${role.id}`"
                        :project-id="project.id"
                        :revision="project.revision"
                        :role="role"
                        :permission-options="permissionOptions"
                        :default-permission-keys="defaultPermissionKeys"
                        :disabled="Boolean(project.archived_at)"
                        @saved="editing = null"
                    />
                </article>
                <PaginationControls :current-page="roles.current_page"
                    :last-page="roles.last_page"
                    :previous="roles.prev_page_url"
                    :next="roles.next_page_url"
                    label="Roles pagination"
                />
            </section>
        </div>
        <ProjectConfirm
            :open="deleting !== null"
            title="Delete project role"
            :description="`Delete ${deleting?.name ?? 'this role'}? Assigned roles cannot be deleted.`"
            :busy="deletion.processing"
            @close="deleting = null"
            @confirm="remove">
            <p v-for="(message, key) in deletion.errors" key="key" role="alert" class="text-sm text-destructive">
                {{ message }}
            </p></ProjectConfirm
        >
    </AccountLayout>
</template>
