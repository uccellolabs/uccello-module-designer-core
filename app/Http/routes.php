<?php

Route::name('uccello.module-designer.')->group(function () {

    // Adapt params if we use or not multi domains
    if (!uccello()->useMultiDomains()) {
        $domainParam = '';
        $domainAndModuleParams = '/{module}';
    } else {
        $domainParam = '{domain}';
        $domainAndModuleParams = '{domain}/{module}';
    }

    // Default routes
    Route::get($domainAndModuleParams . '/install', 'InstallController@process')->name('install');
    Route::get($domainAndModuleParams . '/create', 'CreateController@process')->name('create');
    Route::get($domainAndModuleParams . '/update', 'UpdateController@process')->name('update');
    Route::get($domainAndModuleParams . '/settings', 'SettingsController@process')->name('settings');
    Route::get($domainAndModuleParams . '/uitypes', 'CreateController@getUitypes')->name('uitypes');
});