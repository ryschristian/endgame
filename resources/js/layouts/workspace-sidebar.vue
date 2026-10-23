<script setup lang="ts">
import WorkspaceIcon from '@/components/workspace-icon.vue';
import type { AccountUser, SharedPageProps } from '@/types/accounts';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{
    user: AccountUser;
    canManageAccounts: boolean;
    projects: SharedPageProps['workspace']['projects'];
    url: string;
}>();
defineEmits<{ navigate: []; collapse: [] }>();
const initials = computed(() =>
    props.user.name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase(),
);
const isArchive = computed(
    () =>
        new URL(
            props.url || '/projects',
            'http://workspace.local',
        ).searchParams.get('status') === 'archived',
);
</script>
<template>
    <div
        class="flex h-full min-h-0 flex-col bg-sidebar text-sidebar-foreground"
    >
        <div
            class="flex h-[var(--workspace-topbar-height)] shrink-0 items-center gap-2 px-3"
        >
            <Link
                href="/projects"
                class="flex min-w-0 flex-1 items-center gap-2.5 rounded-md p-1 text-[13px] font-semibold text-foreground"
                aria-label="Endgame workspace"
                @click="$emit('navigate')"
                ><span
                    class="flex size-6 items-center justify-center rounded-md bg-[#292825] text-[10px] font-bold tracking-tighter text-white"
                    >en.</span
                >Endgame
                <span class="ml-1 text-muted-foreground"
                    ><WorkspaceIcon name="down" class="size-3" /></span
            ></Link>
            <button
                type="button"
                aria-label="Collapse navigation"
                class="rounded p-1.5 text-muted-foreground hover:bg-sidebar-accent"
                @click="$emit('collapse')"
            >
                <WorkspaceIcon name="panel" />
            </button>
        </div>
        <nav
            aria-label="Workspace navigation"
            class="flex-1 space-y-4 overflow-y-auto px-2 pt-3 pb-4 text-[13px]"
        >
            <div class="space-y-1">
                <p
                    class="px-2 pb-1 text-[11px] font-semibold tracking-[0.06em] text-muted-foreground uppercase"
                >
                    Workspace
                </p>
                <Link
                    href="/projects"
                    :aria-current="
                        url.startsWith('/projects') && !isArchive
                            ? 'page'
                            : undefined
                    "
                    class="flex h-7.5 items-center gap-2.5 rounded-md border border-transparent px-2 transition-colors hover:bg-sidebar-accent"
                    :class="
                        url.startsWith('/projects') && !isArchive
                            ? 'border-border bg-card font-medium text-foreground shadow-xs'
                            : ''
                    "
                    @click="$emit('navigate')"
                    ><WorkspaceIcon
                        name="projects"
                        class="text-muted-foreground"
                    />Projects</Link
                >
                <Link
                    v-if="canManageAccounts"
                    href="/admin/accounts"
                    :aria-current="
                        url.startsWith('/admin') ? 'page' : undefined
                    "
                    class="flex h-7.5 items-center gap-2.5 rounded-md px-2 hover:bg-sidebar-accent"
                    :class="
                        url.startsWith('/admin')
                            ? 'bg-sidebar-accent font-medium'
                            : ''
                    "
                    @click="$emit('navigate')"
                    ><WorkspaceIcon
                        name="users"
                        class="text-muted-foreground"
                    />Accounts</Link
                >
                <Link
                    href="/projects?status=archived"
                    :aria-current="isArchive ? 'page' : undefined"
                    class="flex h-7.5 items-center gap-2.5 rounded-md px-2 hover:bg-sidebar-accent"
                    :class="isArchive ? 'bg-sidebar-accent font-medium' : ''"
                    @click="$emit('navigate')"
                    ><WorkspaceIcon
                        name="archive"
                        class="text-muted-foreground"
                    />Archive</Link
                >
            </div>
            <div class="space-y-1">
                <div class="flex items-center justify-between px-2 pb-1">
                    <p
                        class="text-[11px] font-semibold tracking-[0.06em] text-muted-foreground uppercase"
                    >
                        Projects
                    </p>
                    <Link
                        v-if="canManageAccounts"
                        href="/projects/create"
                        aria-label="Add a project"
                        class="rounded p-0.5 text-muted-foreground hover:bg-sidebar-accent"
                        @click="$emit('navigate')"
                        ><WorkspaceIcon name="plus" class="size-3.5"
                    /></Link>
                </div>
                <Link
                    v-for="project in projects"
                    :key="project.id"
                    :href="`/projects/${project.id}`"
                    class="flex min-h-7.5 items-center gap-2.5 rounded-md px-2 hover:bg-sidebar-accent"
                    :class="
                        url.split('?')[0] === `/projects/${project.id}` ||
                        url.startsWith(`/projects/${project.id}/`)
                            ? 'bg-card font-medium shadow-xs ring-1 ring-border'
                            : ''
                    "
                    @click="$emit('navigate')"
                    ><span
                        class="flex size-5 shrink-0 items-center justify-center rounded bg-[#e9e7fc] text-[#7770c9]"
                        ><WorkspaceIcon name="globe" class="size-3.5" /></span
                    ><span class="truncate">{{ project.name }}</span
                    ><span
                        class="ml-auto size-1.5 shrink-0 rounded-full bg-[#6382c1]"
                /></Link>
                <p
                    v-if="!projects.length"
                    class="px-2 py-2 text-xs text-muted-foreground"
                >
                    Your projects will appear here.
                </p>
            </div>
        </nav>
        <div
            class="shrink-0 space-y-1 border-t border-sidebar-border p-2 text-[13px]"
        >
            <Link
                href="/account"
                class="flex h-7.5 items-center gap-2.5 rounded-md px-2 hover:bg-sidebar-accent"
                @click="$emit('navigate')"
                ><WorkspaceIcon
                    name="settings"
                    class="text-muted-foreground"
                />My account</Link
            >
            <Link
                href="/logout"
                method="post"
                as="button"
                class="flex h-7.5 w-full items-center gap-2.5 rounded-md px-2 text-left hover:bg-sidebar-accent"
                ><WorkspaceIcon
                    name="logout"
                    class="text-muted-foreground"
                />Sign out</Link
            >
            <div class="flex items-center gap-2.5 px-2 pt-3 pb-1">
                <span
                    class="relative flex size-7 shrink-0 items-center justify-center rounded-full bg-[#e8e6fb] text-[10px] font-medium text-[#6257aa]"
                    >{{ initials
                    }}<span
                        class="absolute right-0 bottom-0 size-2 rounded-full border-2 border-sidebar bg-emerald-600" /></span
                ><span class="truncate font-medium">{{ user.name }}</span>
            </div>
        </div>
    </div>
</template>
