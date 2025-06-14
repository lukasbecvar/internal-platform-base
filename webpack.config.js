/* internal-platform-base frontend webpack builder */
const Encore = require('@symfony/webpack-encore');

Encore
    // set build path
    .setOutputPath('public/assets/')
    .setPublicPath('/assets')

    // register css
    .addEntry('index-css', './assets/css/index.scss')

    // register js
    .addEntry('user-manager-js', './assets/js/user-manager.js')
    .addEntry('sidebar-element-js', './assets/js/sidebar-element.js')
    .addEntry('loading-component-js', './assets/js/loading-component.js')
    .addEntry('profile-photo-view-toggle-js', './assets/js/profile-photo-viewer.js')
    .addEntry('notifications-settings-js', './assets/js/component/notification/notifications-settings.js')
    .addEntry('notification-subscriber-js', './assets/js/component/notification/notification-subscriber.js')

    // copy static assets
    .copyFiles({
        from: './assets/images',
        to: 'images/[path][name].[ext]'
    })

    // other webpack configs
    .splitEntryChunks()
    .enableSassLoader()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // postcss configs (tailwindcss)
    .enablePostCssLoader((options) => {
        options.postcssOptions = {
            plugins: {
                tailwindcss: {
                    content: [
                        "./assets/**/*.js",
                        "./view/**/*.twig",
                    ],
                    theme: {
                        extend: {
                            screens: {
                                'phn': '340px',
                                'xs': { max: "265px" },
                                'short': { raw: "(max-height: 160px)" }
                            },

                            // animations config
                            keyframes: {
                                popIn: {
                                    "0%": { opacity: "0", transform: "scale(0.5)" },
                                    "100%": { opacity: "1", transform: "scale(1)" },
                                },
                            },
                            animation: {
                                popin: "popIn 0.1s ease-out",
                            },
                        },
                    },
                    plugins: [],
                    safelist: [
                        'text-white',
                        'text-red-400',
                        'text-blue-400',
                        'text-green-400',
                        'text-purple-400',
                        'text-yellow-400'
                    ],
                },
                autoprefixer: {},
            }
        };
    })
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })
;

module.exports = Encore.getWebpackConfig();
