<script setup lang="ts">
import WorkspaceIcon from '@/components/workspace-icon.vue';
import type { ProjectListRow } from '@/types/projects';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
const props = defineProps<{
    project: ProjectListRow;
    timezone: string;
    list?: boolean;
}>();
const accents = [
    'bg-[#eae7fc] text-[#7770cd]',
    'bg-[#e4f0ed] text-[#469585]',
    'bg-[#f8ead2] text-[#b78a39]',
    'bg-[#f5e4eb] text-[#bc6184]',
];
const accent = computed(() => accents[(props.project.id - 1) % accents.length]);
const date = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
        timeZone: props.timezone,
        month: 'short',
        day: 'numeric',
    }).format(new Date(props.project.updated_at)),
);
const initials = (name: string) =>
    name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase();
</script>
<template>
    <Link
        :href="`/projects/${project.id}`"
        class="group flex min-w-0 flex-col rounded-[10px] border bg-card p-4 transition-[border-color,box-shadow] hover:border-[#c9c4bc] hover:shadow-sm focus-visible:ring-2 focus-visible:ring-primary/30 focus-visible:outline-none"
        :class="list ? 'sm:flex-row sm:items-center sm:gap-6' : 'min-h-44'"
    >
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2.5">
                <span
                    class="flex size-7 shrink-0 items-center justify-center rounded-md"
                    :class="accent"
                    ><WorkspaceIcon name="globe" class="size-4"
                /></span>
                <h2
                    class="truncate text-[13px] leading-5 font-semibold text-foreground"
                >
                    {{ project.name }}
                </h2>
                <span
                    class="ml-auto flex shrink-0 items-center gap-1.5 rounded border border-border px-1.5 py-0.5 text-[10px] leading-3 whitespace-nowrap text-foreground"
                    ><span
                        class="size-1.25 rounded-full"
                        :class="
                            project.archived_at
                                ? 'bg-stone-400'
                                : 'bg-[#6285c4]'
                        "
                    />{{ project.statusLabel }}</span
                >
            </div>
            <p
                class="mt-3 line-clamp-2 min-h-9 text-xs leading-[1.55] text-[#77746d]"
            >
                {{ project.description }}
            </p>
        </div>
        <div
            class="mt-4 flex items-center gap-3 border-t border-border/70 pt-3 text-[10px] text-muted-foreground"
            :class="
                list ? 'sm:mt-0 sm:w-64 sm:shrink-0 sm:border-0 sm:pt-0' : ''
            "
        >
            <span class="font-mono text-[10px]">{{ project.key }}</span
            ><span
                class="flex items-center gap-1"
                :title="`${project.members_count} project members`"
                ><WorkspaceIcon name="users" class="size-3" />{{
                    project.members_count
                }}</span
            ><span
                class="flex items-center gap-1"
                :title="`Updated ${date} in Manila time`"
                ><WorkspaceIcon name="clock" class="size-3" />{{ date }}</span
            >
            <div class="ml-auto flex -space-x-1.5">
                <span
                    v-for="(member, index) in project.memberPreviews"
                    :key="member.id"
                    :title="member.name"
                    class="flex size-6 items-center justify-center rounded-full border-2 border-white text-[8px] font-medium"
                    :class="accents[index % accents.length]"
                    >{{ initials(member.name) }}</span
                ><span
                    v-if="project.members_count > project.memberPreviews.length"
                    class="flex size-6 items-center justify-center rounded-full border-2 border-white bg-muted text-[8px]"
                    >+{{
                        project.members_count - project.memberPreviews.length
                    }}</span
                >
            </div>
        </div>
    </Link>
</template>
