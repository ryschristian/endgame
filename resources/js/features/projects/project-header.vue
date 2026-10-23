<script setup lang="ts">
import WorkspaceIcon from '@/components/workspace-icon.vue';
import type { ProjectPageProps } from '@/types/projects';
import { Link, usePage } from '@inertiajs/vue3';
defineProps<ProjectPageProps>();
const page = usePage<{ errors: Record<string, string> }>();
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
            class="flex gap-5 border-b text-xs"
        >
            <Link
                v-if="can.tickets"
                :href="`/projects/${project.id}/tickets`"
                :aria-current="
                    page.url?.includes('/tickets') ? 'page' : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                >Tickets</Link
            >
            <Link
                :href="'/projects/' + project.id"
                :aria-current="
                    page.url?.split('?')[0] === '/projects/' + project.id
                        ? 'page'
                        : undefined
                "
                class="flex items-center gap-1.5 border-b-2 border-transparent pb-3 text-muted-foreground hover:text-foreground aria-[current=page]:border-foreground aria-[current=page]:text-foreground"
                ><WorkspaceIcon name="grid" class="size-3.5" />Overview</Link
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
