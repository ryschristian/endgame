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
                class="fixed bottom-0 left-0 z-50 max-h-[92dvh] w-full overflow-y-auto rounded-t-[14px] border bg-card shadow-xl outline-none sm:top-[10vh] sm:bottom-auto sm:left-1/2 sm:max-h-[80dvh] sm:max-w-[560px] sm:-translate-x-1/2 sm:rounded-xl"
                ><div class="relative space-y-1 border-b px-4 py-3.5">
                    <DialogTitle class="text-[12.5px] font-semibold"
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
                <div class="p-4">
                    <ProjectForm
                        :team-timezone="timezone"
                    /></div></DialogContent></DialogPortal
    ></DialogRoot>
</template>
