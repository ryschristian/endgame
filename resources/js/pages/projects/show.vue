<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectHeader from '@/features/projects/project-header.vue';
import ProjectForm from '@/features/projects/project-form.vue';
import ProjectConfirm from '@/features/projects/project-confirm.vue';
import PaginationControls from '@/features/accounts/pagination-controls.vue';
import type { SharedPageProps, Paginated } from '@/types/accounts';
import type { ProjectPageProps } from '@/types/projects';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
const props = defineProps<
    ProjectPageProps & {
        members: Paginated<{ id: number; name: string; roles: string[] }>;
    }
>();
const page = usePage<SharedPageProps>();
const confirming = ref(false);
const lifecycle = useForm({ revision: props.project.revision });
function changeArchive() {
    lifecycle
        .transform(() => ({ revision: props.project.revision }))
        .post(
            `/projects/${props.project.id}/${props.project.archived_at ? 'restore' : 'archive'}`,
            {
                preserveScroll: true,
                onSuccess: () => {
                    confirming.value = false;
                },
            },
        );
}
</script>
<template>
    <Head :title="project.name" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <ProjectHeader :project="project" :can="can" />
        <div
            class="grid items-start gap-6 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]"
        >
            <section class="space-y-5 rounded-xl border bg-card p-6">
                <h2 class="text-lg font-semibold">Project settings</h2>
                <ProjectForm
                    v-if="can.update"
                    :key="project.revision"
                    :project="project"
                    :team-timezone="page.props.teamTimezone"
                />
                <dl v-else class="space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Description</dt>
                        <dd class="mt-1 whitespace-pre-wrap">
                            {{ project.description || 'No description' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">Timezone</dt>
                        <dd class="mt-1">{{ project.timezone }}</dd>
                    </div>
                </dl>
            </section>
            <section class="overflow-hidden rounded-xl border bg-card">
                <h2 class="p-5 text-lg font-semibold">Project members</h2>
                <p
                    v-if="!members.data.length"
                    class="border-t p-5 text-sm text-muted-foreground"
                >
                    No visible members.
                </p>
                <ul class="divide-y border-t">
                    <li
                        v-for="member in members.data"
                        :key="member.id"
                        class="space-y-1 p-5"
                    >
                        <p class="text-sm font-medium">{{ member.name }}</p>
                        <p class="text-sm text-muted-foreground">
                            {{ member.roles.join(', ') || 'No roles assigned' }}
                        </p>
                    </li>
                </ul>
                <PaginationControls
                    :current-page="members.current_page"
                    :last-page="members.last_page"
                    :previous="members.prev_page_url"
                    :next="members.next_page_url"
                    label="Member overview pagination"
                />
            </section>
        </div>
        <section
            v-if="project.archived_at ? can.restore : can.archive"
            class="flex flex-wrap items-center justify-between gap-4 rounded-xl border bg-card p-6"
        >
            <h2 class="font-semibold">
                {{
                    project.archived_at ? 'Restore project' : 'Archive project'
                }}
            </h2>
            <Button variant="outline" @click="confirming = true">{{
                project.archived_at ? 'Restore project' : 'Archive project'
            }}</Button>
        </section>
        <ProjectConfirm
            :open="confirming"
            :title="project.archived_at ? 'Restore project' : 'Archive project'"
            :description="
                project.archived_at
                    ? 'Allow changes to this project again?'
                    : 'Keep this project readable and stop changes until it is restored?'
            "
            :busy="lifecycle.processing"
            @close="confirming = false"
            @confirm="changeArchive"
            ><p
                v-for="(message, key) in lifecycle.errors"
                :key="key"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ message }}
            </p></ProjectConfirm
        >
    </AccountLayout>
</template>
