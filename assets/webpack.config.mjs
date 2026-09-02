import Encore from '@symfony/webpack-encore';

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('../public/build/')
    .setPublicPath('/build')
    .addEntry('app', './app.js')
    .enableStimulusBridge('./controllers.json')
    .enablePostCssLoader()
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // Encore 7 ships no CSS minifier of its own.
    .configureCssMinimizerPlugin((options, MinimizerPlugin) => {
        options.minify = MinimizerPlugin.cssnanoMinify;
    })
;

const config = await Encore.getWebpackConfig();

// @symfony/ux-live-component is linked from vendor/, so webpack has to keep the
// node_modules path to resolve the @hotwired/stimulus import it carries.
config.resolve.symlinks = false;

export default config;
