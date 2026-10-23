<script setup lang="ts">
import WorkspaceIcon from '@/components/workspace-icon.vue';
import type { ProjectPageProps } from '@/types/projects';
import { Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    Columns3,
    GanttChart,
    LayoutGrid,
    List,
    Settings2,
    Table2,
} from '@lucide/vue';
defineProps<ProjectPageProps>();
const page = usePage<{ errors: Record<string, string> }>();
const views = [
    { key: '', label: 'Overview', icon: LayoutGrid },
    { key: 'board', label: 'Board', icon: Columns3 },
    { key: 'list', label: 'List', icon: List },
    { key: 'table', label: 'Table', icon: Table2 },
    { key: 'calendar', label: 'Calendar', icon: CalendarDays },
    { key: 'timeline', label: 'Timeline', icon: GanttChart },
];
</script>
<template>
    <div class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <span
                class="flex size-9 items-center justify-center rounded-lg bg-[#eae7fc] text-[#7770cd]"
                ><WorkspaceIcon name="globe" class="size-5"
            /></span>
            <h1 class="text-xl! font-semibold">{{ project.name }}</h1>
            <span
                v-if="project.statusLabel"
                class="flex items-center gap-1.5 rounded border px-2 py-1 text-[11px]"
                ><span
                    class="size-1.5 rounded-full"
                    :class="
                        project.archived_at ? 'bg-stone-400' : 'bg-[#6285c4]'
                    "
                />{{ project.statusLabel }}</span
            ><span
                class="ml-auto font-mono text-[10px] text-muted-foreground"
                >{{ project.key }}</span
            >
        </div>
        <nav
            aria-label="Project navigation"
            class="flex gap-5 overflow-x-auto border-b text-xs whitespace-nowrap"
        >
            <Link
                v-for="view in views.filter((v) => !v.key || can.tickets)"
                :key="view.key"
                :href="`/projects/${project.id}${view.key ? '/' + view.key : ''}`"
                :aria-current="
                    page.url?.split('?')[0] ===
                        `/projects/${project.id}${view.key ? '/' + view.key : ''}` ||
                    (view.key === 'table' && page.url?.includes('/tickets'))
                        ? 'page'
                        : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                ><component :is="view.icon" class="size-3.5" />{{
                    view.label
                }}</Link
            >
            <Link
                v-if="can.update"
                :href="`/projects/${project.id}/workflow`"
                :aria-current="
                    page.url?.split('?')[0] ===
                    `/projects/${project.id}/workflow`
                        ? 'page'
                        : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                ><Settings2 class="size-3.5" />Workflow setup</Link
            >
            <Link
                v-if="can.members"
                :href="'/projects/' + project.id + '/members'"
                :aria-current="
                    page.url?.includes('/members') ? 'page' : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                ><WorkspaceIcon name="users" class="size-3.5" />Members</Link
            >
            <Link
                v-if="can.roles"
                :href="'/projects/' + project.id + '/roles'"
                :aria-current="
                    page.url?.includes('/roles') ? 'page' : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                ><WorkspaceIcon name="shield" class="size-3.5" />Roles</Link
            >
        </nav>
        <p
            v-if="project.archived_at"
            role="status"
            class="rounded-md border bg-muted px-3 py-2 text-xs"
        >
            This project is archived. Restore it to make changes.
        </p>
        <template
            v-for="key in [
                'project',
                'revision',
                'manager',
                'role',
                'invitation',
                'delivery',
            ]"
            :key="key"
            ><p
                v-if="page.props.errors?.[key]"
                role="alert"
                class="text-sm text-destructive"
            >
                {{ page.props.errors[key] }}
            </p></template
        >
    </div>
</template>
