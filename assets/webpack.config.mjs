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

export default await Encore.getWebpackConfig();
