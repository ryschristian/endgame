import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ProjectForm from './project-form.vue';
import RoleForm from './role-form.vue';

afterEach(() => vi.restoreAllMocks());

describe('project forms', () => {
    it('submits creation fields without offering or submitting timezone choices', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const wrapper = mount(ProjectForm, {
            props: {
                teamTimezone: 'Asia/Manila',
            },
        });
        await wrapper.get('#project-key').setValue('WEB');
        await wrapper.get('#project-name').setValue('Website');
        await wrapper.get('#manager-email').setValue('manager@example.test');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.find('select').exists()).toBe(false);
        expect(post).toHaveBeenCalledWith(
            '/projects',
            expect.objectContaining({
                key: 'WEB',
                name: 'Website',
                manager_email: 'manager@example.test',
            }),
            expect.any(Object),
        );
        expect(post.mock.calls[0]?.[1]).not.toHaveProperty('timezone');
    });

    it('disables archived settings and excludes immutable identity from edits', () => {
        const wrapper = mount(ProjectForm, {
            props: {
                project: {
                    id: 1,
                    key: 'END',
                    name: 'Endgame',
                    description: '',
                    timezone: 'UTC',
                    archived_at: '2026-10-22',
                    revision: 4,
                },
                teamTimezone: 'Asia/Manila',
            },
        });
        expect(wrapper.find('#project-key').exists()).toBe(false);
        expect(wrapper.get('fieldset').attributes('disabled')).toBeDefined();
        expect(
            wrapper.get('button[type="submit"]').attributes('disabled'),
        ).toBeDefined();
    });

    it('renders only server-provided permissions and submits selected role grants', async () => {
        const post = vi.spyOn(router, 'post').mockImplementation(() => {});
        const wrapper = mount(RoleForm, {
            props: {
                projectId: 3,
                revision: 7,
                disabled: false,
                permissionOptions: [
                    { value: 'project.view', label: 'Read this project' },
                    { value: 'project.update', label: 'Change settings' },
                ],
                defaultPermissionKeys: ['project.view'],
            },
        });
        await wrapper.get('input[type="text"]').setValue('Editor');
        await wrapper.get('input[value="project.update"]').setValue(true);
        await wrapper.get('form').trigger('submit');
        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
        expect(post).toHaveBeenCalledWith(
            '/projects/3/roles',
            expect.objectContaining({
                name: 'Editor',
                revision: 7,
                permission_keys: ['project.view', 'project.update'],
            }),
            expect.any(Object),
        );
    });
});
