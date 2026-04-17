require("dotenv").config();
const mix = require("laravel-mix");
const webpack = require("webpack");

mix.webpackConfig({
    plugins: [
        new webpack.DefinePlugin({
            __VUE_OPTIONS_API__: false,
            __VUE_PROD_DEVTOOLS__: false,
        }),
    ],
});
if (mix.inProduction()) {
    mix.version()
        .sourceMaps(false)
        .options({
            terser: {
                terserOptions: {
                    compress: {
                        drop_console: true,
                    },
                },
            },
        });
}

mix.js("resources/js/app.js", "public/js")
    .vue()
    .sass("resources/sass/app.scss", "public/css");

mix.copy("node_modules/chart.js/dist/chart.js", "public/chart.js/chart.js");
