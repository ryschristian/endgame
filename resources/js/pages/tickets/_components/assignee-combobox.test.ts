import { mount } from '@vue/test-utils';
import { it, expect } from 'vitest';
import AssigneeCombobox from './assignee-combobox.vue';
import { Combobox } from '@/components/ui/combobox';

it('normalizes Unassigned to null instead of an invalid account id', async () => {
    const wrapper = mount(AssigneeCombobox, {
        props: {
            projectId: 1,
            modelValue: 4,
            initial: { id: 4, name: 'Current owner' },
        },
    });
    wrapper.findComponent(Combobox).vm.$emit('update:modelValue', 0);
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([null]);
});
