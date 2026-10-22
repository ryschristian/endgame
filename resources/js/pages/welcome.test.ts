import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import Welcome from './welcome.vue';

describe('application foundation', () => {
    it('displays the server-provided application name and a working health link', () => {
        const wrapper = mount(Welcome, {
            props: { appName: 'Endgame Test' },
        });

        expect(wrapper.get('h1').text()).toBe('Endgame Test');
        const link = wrapper.get('a');
        expect(link.text()).toBe('Check application health');
        expect(link.attributes('href')).toBe('/up');
    });
});
