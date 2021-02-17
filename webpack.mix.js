const mix = require('./resources/mix');

if (!mix.inProduction()) {
    mix.webpackConfig({
        devtool: 'source-map'
    }).sourceMaps(false);
}

mix
    .js('resources/admin/boot.js', 'assets/admin/js/boot.js').vue({ version: 3 })
    .js('resources/admin/start.js', 'assets/admin/js/start.js').vue({ version: 3 })
    .sass('resources/scss/alpha-admin.scss', 'assets/admin/css/alpha-admin.css')
    .copy('resources/images', 'assets/images');
