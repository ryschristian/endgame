import '../css/app.css';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import type { DefineComponent } from 'vue';
import ActivityToasts from './pages/_components/activity-toasts.vue';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent<DefineComponent>(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>([
                './pages/**/*.vue',
                '!./pages/**/_components/**',
            ]),
        ),
    setup({ el, App, props, plugin }) {
        createApp({
            render: () => [
                h(App, props),
                h(ActivityToasts, {
                    initialStatus: (
                        props.initialPage.props.flash as
                            { status?: string } | undefined
                    )?.status,
                }),
            ],
        })
            .use(plugin)
            .mount(el);
    },
});
