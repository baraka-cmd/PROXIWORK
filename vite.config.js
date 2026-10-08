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
                'resources/css/pages/public/search.css','resources/js/pages/public/search.js',
                'resources/css/pages/public/professionals.css','resources/js/pages/public/professionals.js',
                'resources/css/pages/professional/profile.css','resources/css/pages/professional/services.css',
                'resources/css/pages/client/favorites-requests-quotes.css','resources/js/pages/client/favorites-requests-quotes.js',
                'resources/css/pages/client/orders-payments.css','resources/js/pages/client/orders-payments.js',
                'resources/css/pages/client/profile.css','resources/css/pages/client/addresses.css','resources/js/pages/client/addresses.js',
                'resources/css/pages/client/notifications.css','resources/css/pages/client/messages.css','resources/js/pages/client/messages.js',
                'resources/css/pages/professional/dashboard.css',
                'resources/css/pages/professional/orders-revenues.css','resources/js/pages/professional/orders-revenues.js',
                'resources/css/pages/professional/wallet-withdrawals.css','resources/js/pages/professional/wallet-withdrawals.js',
                'resources/css/pages/professional/reviews-messages.css','resources/js/pages/professional/reviews-messages.js',
                'resources/css/pages/professional/notifications.css','resources/js/pages/professional/notifications.js',
            ],
            refresh: true,
            fonts: [bunny('Poppins',{weights:[400,500,600,700]})],
        }),
        tailwindcss(),
    ],
    server: {watch:{ignored:['**/storage/framework/views/**']}},
});
