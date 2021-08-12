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

$router->group(['prefix' => 'superadmin', 'middleware' => ['jwt.auth', 'role.superadmin']], function() use ($router) {

});

$router->group(['prefix' => 'supervisor', 'middleware' => ['jwt.auth', 'role.supervisor']], function() use ($router) {
    
});

$router->group(['prefix' => 'eos', 'middleware' => ['jwt.auth', 'role.eos']], function() use ($router) {
    // kirim laporan
    $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->post('/shift', 'LaporanController@catatanShift');
        $router->post('/form', 'LaporanController@formIsian');
    });

});
