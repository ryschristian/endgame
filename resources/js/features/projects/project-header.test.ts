import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import ProjectHeader from './project-header.vue';

vi.mock('@inertiajs/vue3', async (importOriginal) => ({
    ...(await importOriginal<object>()),
    usePage: () => ({ props: { errors: {} } }),
}));

describe('project permissions', () => {
    it.each([false, true])(
        'uses server action availability for navigation (%s)',
        (permitted) => {
            const wrapper = mount(ProjectHeader, {
                props: {
                    project: {
                        id: 4,
                        key: 'TEAM',
                        name: 'Team',
                        description: '',
                        timezone: 'UTC',
                        archived_at: null,
                        revision: 1,
                    },
                    can: {
                        view: true,
                        update: false,
                        members: permitted,
                        roles: permitted,
                        archive: false,
                        restore: false,
                    },
                },
            });
            expect(wrapper.find('a[href="/projects/4/members"]').exists()).toBe(
                permitted,
            );
            expect(wrapper.find('a[href="/projects/4/roles"]').exists()).toBe(
                permitted,
            );
            expect(wrapper.find('a[href="/projects/4"]').exists()).toBe(true);
        },
    );
});
