import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { expect, it } from 'vitest';
import ActivityToasts from './activity-toasts.vue';

it('shows repeated successful actions and validation failures through Inertia events', async () => {
    const wrapper = mount(ActivityToasts, { attachTo: document.body });
    document.dispatchEvent(
        new CustomEvent('inertia:success', {
            detail: { page: { props: { flash: { status: 'Ticket saved.' } } } },
        }),
    );
    await nextTick();
    document.dispatchEvent(
        new CustomEvent('inertia:success', {
            detail: { page: { props: { flash: { status: 'Ticket saved.' } } } },
        }),
    );
    document.dispatchEvent(
        new CustomEvent('inertia:error', {
            detail: { errors: { summary: 'Required.' } },
        }),
    );
    await nextTick();
    expect(wrapper.findAll('[data-activity-toast]')).toHaveLength(3);
    expect(wrapper.text()).toContain('Ticket saved.');
    expect(wrapper.text()).toContain('Check the highlighted fields');
    wrapper.unmount();
    document.dispatchEvent(
        new CustomEvent('inertia:success', {
            detail: {
                page: { props: { flash: { status: 'After unmount.' } } },
            },
        }),
    );
    expect(document.body.textContent).not.toContain('After unmount.');
});
