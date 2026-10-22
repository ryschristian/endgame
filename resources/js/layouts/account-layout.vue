<script setup lang="ts">
import { Button } from '@/components/ui/button';
import WorkspaceIcon from '@/components/workspace-icon.vue';
import WorkspaceSidebar from './workspace-sidebar.vue';
import type { AccountUser, SharedPageProps } from '@/types/accounts';
import type { ProjectRow } from '@/types/projects';
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    DialogContent,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
    DialogDescription,
} from 'reka-ui';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
defineProps<{
    user: AccountUser;
    canManageAccounts: boolean;
    status?: string | null;
}>();
const page = usePage<SharedPageProps>();
const mobileOpen = ref(false);
let desktopBreakpoint: MediaQueryList | undefined;
const collapsed = ref(false);
const search = ref('');
const searchInput = ref<HTMLInputElement | null>(null);
const workspace = computed(
    () => page.props?.workspace ?? { projects: [], canCreateProjects: false },
);
const currentProject = computed(
    () => page.props?.project as ProjectRow | undefined,
);
const breadcrumb = computed(() =>
    page.url?.startsWith('/admin')
        ? 'Accounts'
        : page.url?.startsWith('/account')
          ? 'My account'
          : 'Projects',
);
const initials = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
function searchProjects() {
    router.get(
        '/projects',
        search.value.trim() ? { q: search.value.trim() } : {},
    );
}
function shortcut(event: KeyboardEvent) {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        searchInput.value?.focus();
    }
}
function closeMobileOnDesktop(event: MediaQueryListEvent) {
    if (event.matches) mobileOpen.value = false;
}
onMounted(() => {
    document.addEventListener('keydown', shortcut);
    desktopBreakpoint = window.matchMedia('(min-width: 1024px)');
    desktopBreakpoint.addEventListener('change', closeMobileOnDesktop);
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', shortcut);
    desktopBreakpoint?.removeEventListener('change', closeMobileOnDesktop);
});
</script>
<template>
    <div class="min-h-screen bg-background text-foreground">
        <a
            href="#main-content"
            class="sr-only z-60 rounded bg-background p-3 focus:not-sr-only focus:fixed focus:top-2 focus:left-2"
            >Skip to content</a
        >
        <aside
            v-if="!collapsed"
            class="fixed inset-y-0 left-0 z-30 hidden w-56 border-r border-sidebar-border lg:block"
        >
            <WorkspaceSidebar
                :user="user"
                :can-manage-accounts="canManageAccounts"
                :projects="workspace.projects"
                :url="page.url ?? '/projects'"
                @collapse="collapsed = true"
            />
        </aside>
        <DialogRoot v-model:open="mobileOpen"
            ><DialogPortal
                ><DialogOverlay
                    class="fixed inset-0 z-40 bg-black/30 lg:hidden" /><DialogContent
                    class="fixed inset-y-0 left-0 z-50 w-64 border-r border-sidebar-border bg-sidebar focus:outline-none lg:hidden"
                    ><DialogTitle class="sr-only"
                        >Workspace navigation</DialogTitle
                    ><DialogDescription class="sr-only"
                        >Projects and account settings</DialogDescription
                    ><WorkspaceSidebar
                        :user="user"
                        :can-manage-accounts="canManageAccounts"
                        :projects="workspace.projects"
                        :url="page.url ?? '/projects'"
                        @navigate="mobileOpen = false"
                        @collapse="
                            mobileOpen = false
                        " /></DialogContent></DialogPortal
        ></DialogRoot>
        <div
            class="min-w-0 transition-[padding] duration-200 motion-reduce:transition-none"
            :class="!collapsed ? 'lg:pl-56' : ''"
        >
            <header
                class="flex h-13 items-center justify-between gap-3 border-b bg-background px-4 sm:px-6"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        aria-label="Open navigation"
                        :aria-expanded="mobileOpen"
                        class="rounded-md p-1.5 text-muted-foreground hover:bg-muted lg:hidden"
                        @click="mobileOpen = true"
                    >
                        <WorkspaceIcon name="menu" /></button
                    ><button
                        v-if="collapsed"
                        type="button"
                        aria-label="Expand navigation"
                        class="hidden rounded-md p-1.5 text-muted-foreground hover:bg-muted lg:inline-flex"
                        @click="collapsed = false"
                    >
                        <WorkspaceIcon name="panel" /></button
                    ><span class="truncate text-xs">{{ breadcrumb }}</span
                    ><template v-if="currentProject"
                        ><span class="text-xs text-muted-foreground">/</span
                        ><WorkspaceIcon
                            name="globe"
                            class="hidden size-3.5 text-[#7770c9] sm:block"
                        /><span class="truncate text-xs font-medium">{{
                            currentProject.name
                        }}</span></template
                    >
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <form
                        class="relative hidden items-center sm:flex"
                        role="search"
                        @submit.prevent="searchProjects"
                    >
                        <WorkspaceIcon
                            name="search"
                            class="pointer-events-none absolute left-2.5 size-3.5 text-muted-foreground"
                        /><label for="workspace-search" class="sr-only"
                            >Search all projects</label
                        ><input
                            id="workspace-search"
                            ref="searchInput"
                            v-model="search"
                            type="search"
                            placeholder="Search projects…"
                            maxlength="255"
                            class="h-7.5 w-48 rounded-md border border-border bg-muted/50 pr-12 pl-8 text-xs outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/20 xl:w-56"
                        /><kbd
                            class="pointer-events-none absolute right-2 rounded border bg-white px-1 font-sans text-[9px] text-muted-foreground"
                            >Ctrl K</kbd
                        >
                    </form>
                    <Button
                        v-if="workspace.canCreateProjects"
                        as-child
                        variant="outline"
                        class="h-7.5 min-h-0 gap-1.5 rounded-md px-2.5 text-xs font-medium shadow-xs"
                        ><Link
                            href="/projects/create"
                            aria-label="Create project"
                            ><WorkspaceIcon
                                name="plus"
                                class="size-3.5"
                            />New</Link
                        ></Button
                    ><Link
                        href="/account"
                        aria-label="Account settings"
                        class="flex size-7 items-center justify-center rounded-full bg-[#e8e6fb] text-[9px] font-medium text-[#6257aa]"
                        >{{ initials(user.name) }}</Link
                    >
                </div>
            </header>
            <main
                id="main-content"
                class="space-y-6 px-4 py-6 sm:px-6 [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:tracking-[-0.035em]"
            >
                <p
                    v-if="status"
                    role="status"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800"
                >
                    {{ status }}
                </p>
                <slot />
            </main>
        </div>
    </div>
</template>
