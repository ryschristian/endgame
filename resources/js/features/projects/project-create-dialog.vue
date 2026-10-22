<script setup lang="ts">
import WorkspaceIcon from '@/components/workspace-icon.vue';
import ProjectForm from './project-form.vue';
import {
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogOverlay,
    DialogPortal,
    DialogRoot,
    DialogTitle,
} from 'reka-ui';
defineProps<{ open: boolean; timezone: string }>();
defineEmits<{ close: [] }>();
</script>
<template>
    <DialogRoot
        :open="open"
        @update:open="
            (value) => {
                if (!value) $emit('close');
            }
        "
        ><DialogPortal
            ><DialogOverlay
                class="fixed inset-0 z-40 bg-[#211d18]/30" /><DialogContent
                class="fixed top-1/2 left-1/2 z-50 max-h-[90dvh] w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 -translate-y-1/2 overflow-y-auto rounded-xl border border-white/80 bg-background shadow-xl outline-none"
                ><div class="relative space-y-1 border-b px-5 py-4">
                    <DialogTitle class="text-sm font-semibold"
                        >New project</DialogTitle
                    ><DialogDescription
                        class="pr-5 text-xs text-muted-foreground"
                        >A shared space for your team's work.</DialogDescription
                    ><DialogClose as-child
                        ><button
                            type="button"
                            aria-label="Close new project"
                            class="absolute top-4 right-4 rounded p-1 text-muted-foreground hover:bg-muted"
                        >
                            <WorkspaceIcon
                                name="close"
                                class="size-4"
                            /></button
                    ></DialogClose>
                </div>
                <div class="p-5">
                    <ProjectForm
                        :team-timezone="timezone"
                    /></div></DialogContent></DialogPortal
    ></DialogRoot>
</template>
