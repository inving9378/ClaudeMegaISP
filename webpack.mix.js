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

// #9990491: publicación atómica del bundle JS. `deploy/circuito/npm-build.sh` compila a un
// staging DENTRO de public/ (env MIX_JS_STAGE_DIR) y hace el swap sólo si el build terminó OK,
// para que ninguna carga en curso reciba app.js/chunks a medio escribir durante un rebuild.
// Sin la env (build manual con `npm run dev`/`watch`), el destino es EXACTAMENTE el de siempre.
const jsStage = process.env.MIX_JS_STAGE_DIR;
const jsOut = jsStage ? `public/${jsStage}/js` : "public/js";

mix.js("resources/js/app.js", jsOut)
    .vue()
    .sass("resources/sass/app.scss", "public/css")
    .version();

mix.copy("node_modules/chart.js/dist/chart.js", "public/chart.js/chart.js");
