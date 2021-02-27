const mix = require('./resources/mix');

if (!mix.inProduction()) {
    mix.webpackConfig({
        devtool: 'source-map'
    }).sourceMaps(false);
}

mix
    .js('resources/admin/boot.js', 'assets/admin/js/boot.js').vue({ version: 3 })
    .js('resources/admin/start.js', 'assets/admin/js/start.js').vue({ version: 3 })
    .js('resources/customer_portal/portal.js', 'assets/portal/js/app.js').vue({ version: 3 })
    .sass('resources/customer_portal/app.scss', 'assets/portal/css/app.css')
    .sass('resources/scss/alpha-admin.scss', 'assets/admin/css/alpha-admin.css')
    .sass('resources/scss/all_public.scss', 'assets/admin/css/all_public.css')
    .copy('resources/images', 'assets/images');
