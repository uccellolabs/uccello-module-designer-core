<?php

Route::name('uccello.module_designer.')->group(function () {

    // Adapt params if we use or not multi domains
    if (!uccello()->useMultiDomains()) {
        $domainParam = '';
        $domainAndModuleParams = '/{module}';
    } else {
        $domainParam = '{domain}';
        $domainAndModuleParams = '{domain}/{module}';
    }

    // Default routes
    Route::get($domainAndModuleParams . '/module_designer/install', 'DesignerController@install')->name('install');
    Route::get($domainAndModuleParams . '/module_designer/create', 'DesignerController@create')->name('create');
    Route::get($domainAndModuleParams . '/module_designer/update', 'DesignerController@update')->name('update');
    Route::get($domainAndModuleParams . '/module_designer/settings', 'DesignerController@settings')->name('settings');
});