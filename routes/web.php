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

$router->get('/tesdb', function () use ($router) {
    // Test database connection
    try {
        // DB::connection()->getPdo();
        if(DB::connection()->getDatabaseName())
        {
            echo "conncted sucessfully to database ".DB::connection()->getDatabaseName();
        } else {
            echo 'no';
        }
    } catch (\Exception $e) {
        die("Could not connect to the database.  Please check your configuration. error:" . $e );
    }
});

// , 'middleware' => ['jwt.auth', 'role.super']
$router->group(['prefix' => 'dashboard', 'middleware' => ['jwt.auth', 'role.all']], function() use ($router) {
    $router->get('/', 'DashboardController@getDashboard');
});

$router->group(['prefix' => 'superadmin', 'middleware' => ['jwt.auth', 'role.superadmin']], function() use ($router) {
    // get data laporan
    $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->get('/', 'LaporanController@getLaporan');
        $router->get('/details/{id}', 'LaporanController@detailLaporan');
        $router->get('/shift', 'LaporanController@getCatatanShift');

        $router->group(['prefix' => 'cetak'], function() use ($router) {
            $router->get('/', 'LaporanApprovalController@index');
            $router->post('/approve', 'LaporanApprovalController@approve');
        });
    });
});

$router->group(['prefix' => 'laporan', 'middleware' => ['jwt.auth', 'role.super']], function() use ($router) {
    // get data laporan
    // $router->group(['prefix' => 'laporan'], function() use ($router) {
        $router->get('/fct', 'LaporanController@getLaporanFct');
        $router->get('/cln', 'LaporanController@getLaporanCln');
        $router->get('/cctv', 'LaporanController@getLaporanCctv');
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
        $router->get('/fct', 'LaporanEOSController@getLaporanFct');
        $router->get('/cln', 'LaporanEOSController@getLaporanCln');
        $router->get('/cctv', 'LaporanEOSController@getLaporanCctv');
        $router->get('/details/{id}', 'LaporanMobileController@detailLaporan');
        
        $router->group(['prefix' => 'shift'], function() use ($router) {
            $router->post('/', 'LaporanMobileController@catatanShift');
            $router->put('/{id}', 'LaporanMobileController@updateCatatanShift');
        });        
        
        $router->post('/form', 'LaporanMobileController@formIsian');
        $router->post('/request-cetak', 'LaporanApprovalController@requestLaporan');

    });

});