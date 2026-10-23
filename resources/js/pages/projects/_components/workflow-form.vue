<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import type { Workflow, WorkflowStatus } from './workspace-types';
const props = defineProps<{
    workflow: Workflow;
    stageDefaults: Omit<WorkflowStatus, 'key'>;
    projectId: number;
    ticketCount: number;
    disabled: boolean;
}>();
const definition = JSON.parse(
    JSON.stringify(props.workflow.definition),
) as Workflow['definition'];
const form = useForm({
    version_id: props.workflow.id,
    definition,
    mapping: Object.fromEntries(
        definition.statuses.map((stage) => [stage.key, stage.key]),
    ) as Record<string, string>,
});
let nextStage = 0;
function addStage() {
    form.definition.statuses.push({
        key: `stage_${Date.now()}_${++nextStage}`,
        ...props.stageDefaults,
    });
}
function reorder(index: number, offset: number) {
    const statuses = form.definition.statuses;
    const other = index + offset;
    if (other < 0 || other >= statuses.length) return;
    [statuses[index], statuses[other]] = [statuses[other]!, statuses[index]!];
}
function removeStage(key: string) {
    form.definition.statuses = form.definition.statuses.filter(
        (s) => s.key !== key,
    );
    form.definition.transitions = form.definition.transitions.filter(
        (edge) => edge.from !== key && edge.to !== key,
    );
    for (const source of Object.keys(form.mapping))
        if (form.mapping[source] === key) form.mapping[source] = '';
}
function toggle(from: string, to: string, checked: boolean) {
    if (checked) form.definition.transitions.push({ from, to });
    else
        form.definition.transitions = form.definition.transitions.filter(
            (edge) => edge.from !== from || edge.to !== to,
        );
}
function publish() {
    form.post(`/projects/${props.projectId}/workflow`, {
        preserveScroll: true,
    });
}
</script>
<template>
    <form class="mx-auto max-w-5xl space-y-6" @submit.prevent="publish">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold">Workflow setup</h2>
                <p class="mt-1 text-xs text-muted-foreground">
                    Published version {{ workflow.version }} · Changes take
                    effect when published.
                </p>
            </div>
            <Button type="submit" :disabled="disabled || form.processing">{{
                form.processing ? 'Publishing…' : 'Publish workflow'
            }}</Button>
        </div>
        <fieldset
            :disabled="disabled || form.processing"
            class="space-y-6 disabled:opacity-60"
        >
            <section class="rounded-xl border bg-card">
                <div
                    class="flex items-center justify-between border-b px-5 py-4"
                >
                    <h3 class="text-sm font-medium">Stages</h3>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="form.definition.statuses.length >= 20"
                        @click="addStage"
                        ><Plus class="size-3.5" />Add stage</Button
                    >
                </div>
                <div class="space-y-3 p-5">
                    <div
                        v-for="(stage, index) in form.definition.statuses"
                        :key="stage.key"
                        class="flex flex-wrap items-center gap-2 rounded-lg border p-3"
                    >
                        <label class="shrink-0"
                            ><span class="sr-only"
                                >Color for {{ stage.label }}</span
                            ><input
                                v-model="stage.color"
                                type="color"
                                class="size-8 cursor-pointer rounded border" /></label
                        ><label class="min-w-40 flex-1"
                            ><span class="sr-only"
                                >Stage {{ index + 1 }} name</span
                            ><input
                                v-model="stage.label"
                                required
                                maxlength="60"
                                class="h-9 w-full rounded-md border bg-card px-3 text-sm" /></label
                        ><label class="flex items-center gap-2 px-2 text-xs"
                            ><input
                                v-model="stage.completed"
                                type="checkbox"
                                :disabled="
                                    stage.key === form.definition.initial
                                "
                            />Completed stage</label
                        ><Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Move ${stage.label} up`"
                            :disabled="index === 0"
                            @click="reorder(index, -1)"
                            ><ArrowUp class="size-4" /></Button
                        ><Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Move ${stage.label} down`"
                            :disabled="
                                index === form.definition.statuses.length - 1
                            "
                            @click="reorder(index, 1)"
                            ><ArrowDown class="size-4" /></Button
                        ><Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            :aria-label="`Remove ${stage.label}`"
                            :disabled="
                                form.definition.statuses.length <= 2 ||
                                stage.key === form.definition.initial
                            "
                            @click="removeStage(stage.key)"
                            ><Trash2 class="size-4 text-destructive"
                        /></Button>
                    </div>
                    <label
                        class="flex flex-wrap items-center gap-3 pt-2 text-xs font-medium"
                        >New tickets start in<select
                            v-model="form.definition.initial"
                            class="h-9 rounded-md border bg-card px-3 text-xs"
                        >
                            <option
                                v-for="stage in form.definition.statuses.filter(
                                    (s) => !s.completed,
                                )"
                                :key="stage.key"
                                :value="stage.key"
                            >
                                {{ stage.label }}
                            </option>
                        </select></label
                    >
                </div>
            </section>
            <section class="overflow-hidden rounded-xl border bg-card">
                <div class="border-b px-5 py-4">
                    <h3 class="text-sm font-medium">Allowed transitions</h3>
                    <p class="mt-1 text-xs text-muted-foreground">
                        Rows are the current stage. Select the stages tickets
                        can move to.
                    </p>
                </div>
                <div class="overflow-x-auto p-5">
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr>
                                <th class="p-2 font-medium">From / To</th>
                                <th
                                    v-for="stage in form.definition.statuses"
                                    :key="stage.key"
                                    class="max-w-28 p-2 text-center font-medium"
                                >
                                    {{ stage.label }}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="from in form.definition.statuses"
                                :key="from.key"
                                class="border-t"
                            >
                                <th class="p-3 font-normal">
                                    {{ from.label }}
                                </th>
                                <td
                                    v-for="to in form.definition.statuses"
                                    :key="to.key"
                                    class="p-3 text-center"
                                >
                                    <span
                                        v-if="from.key === to.key"
                                        class="text-muted-foreground"
                                        >—</span
                                    ><input
                                        v-else
                                        type="checkbox"
                                        :aria-label="`${from.label} to ${to.label}`"
                                        :checked="
                                            form.definition.transitions.some(
                                                (edge) =>
                                                    edge.from === from.key &&
                                                    edge.to === to.key,
                                            )
                                        "
                                        @change="
                                            toggle(
                                                from.key,
                                                to.key,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
            <section class="rounded-xl border bg-card p-5">
                <h3 class="mb-4 text-sm font-medium">Resolution outcomes</h3>
                <div
                    v-for="outcome in form.definition.resolutions"
                    :key="outcome.key"
                    class="mb-2"
                >
                    <label class="block"
                        ><span class="sr-only">Resolution outcome name</span
                        ><input
                            v-model="outcome.label"
                            required
                            maxlength="60"
                            class="h-9 w-full max-w-sm rounded-md border bg-card px-3 text-xs"
                    /></label>
                </div>
            </section>
            <section class="rounded-xl border bg-card p-5">
                <h3 class="text-sm font-medium">Map existing tickets</h3>
                <p class="mt-1 mb-4 text-xs text-muted-foreground">
                    {{ ticketCount }} tickets will move to this version. Active
                    and completed categories must remain the same.
                </p>
                <div
                    v-for="stage in workflow.definition.statuses"
                    :key="stage.key"
                    class="mb-3 grid items-center gap-2 sm:grid-cols-2"
                >
                    <label :for="`mapping-${stage.key}`" class="text-xs">{{
                        stage.label
                    }}</label
                    ><select
                        :id="`mapping-${stage.key}`"
                        v-model="form.mapping[stage.key]"
                        required
                        class="h-9 rounded-md border bg-card px-3 text-xs"
                    >
                        <option value="" disabled>Choose new stage</option>
                        <option
                            v-for="target in form.definition.statuses.filter(
                                (s) => s.completed === stage.completed,
                            )"
                            :key="target.key"
                            :value="target.key"
                        >
                            {{ target.label }}
                        </option>
                    </select>
                </div>
            </section>
        </fieldset>
        <div
            v-if="Object.keys(form.errors).length"
            role="alert"
            class="space-y-1 rounded-lg border border-destructive/25 p-4 text-xs text-destructive"
        >
            <p v-for="(message, key) in form.errors" :key="key">
                {{ message }}
            </p>
        </div>
    </form>
</template>
