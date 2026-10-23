<script setup lang="ts">
import { Button } from '@/components/ui/button';
import WorkspaceIcon from '@/components/workspace-icon.vue';
import AccountLayout from '@/layouts/account-layout.vue';
import ProjectCard from '@/pages/projects/_components/project-card.vue';
import ProjectCreateDialog from '@/pages/projects/_components/project-create-dialog.vue';
import PaginationControls from '@/pages/_components/pagination-controls.vue';
import type { SharedPageProps, Paginated } from '@/types/accounts';
import type { Option, ProjectListRow } from '@/types/projects';
import { Head, router, usePage } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
const props = defineProps<{
    projects: Paginated<ProjectListRow>;
    filters: { q: string; status: string };
    statusOptions: Option[];
    canCreate: boolean;
}>();
const page = usePage<SharedPageProps>();
const filters = reactive({ ...props.filters });
const view = ref<'grid' | 'list'>('grid');
const creating = ref(false);
function search() {
    router.get('/projects', filters, { preserveState: true, replace: true });
}
</script>
<template>
    <Head title="Projects" />
    <AccountLayout
        :user="page.props.auth.user!"
        :can-manage-accounts="page.props.auth.canManageAccounts"
        :status="page.props.flash.status"
    >
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-1.5">
                <h1>Projects</h1>
                <p class="text-xs text-muted-foreground">
                    {{ projects.total }}
                    {{ projects.total === 1 ? 'project' : 'projects' }}
                </p>
            </div>
            <Button
                v-if="canCreate"
                class="h-8 min-h-0 gap-1.5 rounded-md px-3 text-xs font-medium shadow-none"
                @click="creating = true"
                ><WorkspaceIcon name="plus" class="size-3.5" />New
                project</Button
            >
        </div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <form
                class="flex w-full flex-wrap items-center gap-2 sm:w-auto"
                role="search"
                aria-label="Filter projects"
                @submit.prevent="search"
            >
                <div class="relative min-w-40 flex-1 sm:w-52">
                    <WorkspaceIcon
                        name="search"
                        class="pointer-events-none absolute top-2 left-2.5 size-3.5 text-muted-foreground"
                    /><label for="project-search" class="sr-only"
                        >Search projects</label
                    ><input
                        id="project-search"
                        v-model="filters.q"
                        type="search"
                        placeholder="Search projects"
                        maxlength="255"
                        class="h-8 w-full rounded-md border border-input bg-background pr-3 pl-8 text-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20"
                    />
                </div>
                <label for="project-status" class="sr-only"
                    >Project status</label
                ><select
                    id="project-status"
                    v-model="filters.status"
                    class="h-8 rounded-md border border-input bg-background px-2 text-xs"
                    @change="search"
                >
                    <option
                        v-for="option in statusOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <Button
                    variant="outline"
                    type="submit"
                    class="h-8 min-h-0 rounded-md px-2.5 text-xs font-normal shadow-none"
                    >Apply filters</Button
                >
            </form>
            <div
                class="flex rounded-md bg-muted p-0.5"
                role="group"
                aria-label="Project view"
            >
                <button
                    type="button"
                    aria-label="Grid view"
                    :aria-pressed="view === 'grid'"
                    class="flex h-6.5 items-center gap-1.5 rounded px-2 text-[11px]"
                    :class="
                        view === 'grid'
                            ? 'bg-white text-foreground shadow-xs'
                            : 'text-muted-foreground'
                    "
                    @click="view = 'grid'"
                >
                    <WorkspaceIcon name="grid" class="size-3" />Grid</button
                ><button
                    type="button"
                    aria-label="List view"
                    :aria-pressed="view === 'list'"
                    class="flex h-6.5 items-center gap-1.5 rounded px-2 text-[11px]"
                    :class="
                        view === 'list'
                            ? 'bg-white text-foreground shadow-xs'
                            : 'text-muted-foreground'
                    "
                    @click="view = 'list'"
                >
                    <WorkspaceIcon name="list" class="size-3" />List
                </button>
            </div>
        </div>
        <div
            v-if="projects.data.length"
            :class="
                view === 'grid'
                    ? 'grid grid-cols-1 gap-3 min-[1800px]:grid-cols-5 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4'
                    : 'space-y-3'
            "
        >
            <ProjectCard
                v-for="project in projects.data"
                :key="project.id"
                :project="project"
                :timezone="page.props.teamTimezone"
                :list="view === 'list'"
            />
        </div>
        <section
            v-else
            class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed px-6 text-center"
        >
            <span
                class="mb-3 flex size-10 items-center justify-center rounded-xl bg-muted"
                ><WorkspaceIcon
                    name="projects"
                    class="size-5 text-muted-foreground"
            /></span>
            <h2 class="text-sm font-medium">No projects here yet</h2>
            <p class="mt-1 max-w-sm text-xs leading-5 text-muted-foreground">
                {{
                    filters.q
                        ? 'Try a different search or status filter.'
                        : canCreate
                          ? 'Create your first project to bring your team’s work together.'
                          : 'Projects will appear here when your team grants you access.'
                }}
            </p>
            <Button
                v-if="canCreate && !filters.q"
                variant="outline"
                class="mt-4 h-8 min-h-0 text-xs"
                @click="creating = true"
                >Create a project</Button
            >
        </section>
        <PaginationControls
            :current-page="projects.current_page"
            :last-page="projects.last_page"
            :previous="projects.prev_page_url"
            :next="projects.next_page_url"
            label="Projects pagination"
        />
        <ProjectCreateDialog
            :open="creating"
            :timezone="page.props.teamTimezone"
            @close="creating = false"
        />
    </AccountLayout>
</template>
