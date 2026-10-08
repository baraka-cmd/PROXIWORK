import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css','resources/js/app.js',
                'resources/css/pages/auth/login.css','resources/js/pages/auth/login.js',
                'resources/css/pages/auth/register.css','resources/js/pages/auth/register.js',
                'resources/css/pages/admin/users-roles.css','resources/js/pages/admin/users-roles.js',
                'resources/css/pages/admin/permissions-professionals.css','resources/js/pages/admin/permissions-professionals.js',
                'resources/css/pages/admin/services-requests.css','resources/js/pages/admin/services-requests.js',
                'resources/css/pages/admin/dashboard.css','resources/js/pages/admin/dashboard.js',
                'resources/css/pages/admin/orders.css','resources/css/pages/admin/payments.css',
                'resources/css/pages/admin/analytics.css','resources/js/pages/admin/analytics.js',
                'resources/css/pages/client/dashboard.css','resources/js/pages/client/dashboard.js',
            ],
            refresh: true,
            fonts: [bunny('Poppins',{weights:[400,500,600,700]})],
        }),
        tailwindcss(),
    ],
    server: {watch:{ignored:['**/storage/framework/views/**']}},
});
