<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    echo 'API Pelindo Report - Pelaporan';
});

$router->group(['prefix' => 'dashboard', 'middleware' => ['jwt.auth', 'role.super']], function() use ($router) {
    $router->get('/', 'DashboardController@getDashboard');
});

$router->group(['prefix' => 'superadmin', 'middleware' => ['jwt.auth', 'role.superadmin']], function() use ($router) {
    // get data laporan
    $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->get('/', 'LaporanController@getLaporan');
        $router->get('/details/{id}', 'LaporanController@detailLaporan');
        $router->get('/shift', 'LaporanController@getCatatanShift');
    });
});

$router->group(['prefix' => 'laporan', 'middleware' => ['jwt.auth', 'role.super']], function() use ($router) {
    // get data laporan
    // $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->get('/', 'LaporanController@getLaporan');
        $router->get('/details/{id}', 'LaporanController@detailLaporan');
        $router->get('/shift', 'LaporanController@getCatatanShift');
    // });
});

$router->group(['prefix' => 'utils'], function() use ($router) {
    $router->get('/kategori', 'LaporanController@getFormJenis');
});

$router->group(['prefix' => 'eos', 'middleware' => ['jwt.auth', 'role.eos']], function() use ($router) {
    // kirim laporan lewat mobile
    $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->post('/shift', 'LaporanMobileController@catatanShift');
        $router->post('/form', 'LaporanMobileController@formIsian');
    });

});