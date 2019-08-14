const mix = require('laravel-mix');
const path = require('path');

var autoload = {
    jquery: [ '$', 'jQuery', 'jquery']
}

mix.autoload(autoload);

mix.setPublicPath('public');

mix.js('./resources/assets/js/app.js', 'public/js')
   .sass('./resources/assets/sass/app.scss', 'public/css');

mix.version();

// Copy all compiled files into main project (auto publishing)
mix.copyDirectory('public', '../../../public/vendor/uccello/module-designer');