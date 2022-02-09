const mix = require('./resources/mix');

if (!mix.inProduction()) {
    mix.webpackConfig({
        devtool: 'source-map'
    }).sourceMaps(false);
}

mix
    .js('resources/admin/start.js', 'assets/admin/js/start.js').vue({ version: 3 })
    .js('resources/customer_portal/portal.js', 'assets/portal/js/app.js').vue({ version: 3 })
    .js('resources/admin/firebase_notify.js', 'assets/admin/js/firebase_notify.js')
    .js('resources/admin/global_admin.js', 'assets/admin/js/global_admin.js')
    .js('resources/customer_portal/login_helper.js', 'assets/portal/js/login_helper.js')
    .sass('resources/customer_portal/app.scss', 'assets/portal/css/app.css')
    .sass('resources/scss/alpha-admin.scss', 'assets/admin/css/alpha-admin.css')
    .sass('resources/scss/all_public.scss', 'assets/admin/css/all_public.css')
    .copy('resources/images', 'assets/images')
    .copy('node_modules/element-plus/lib/theme-chalk/fonts', 'assets/admin/css/fonts')
    .copy('node_modules/element-plus/lib/theme-chalk/fonts', 'assets/portal/css/fonts');
