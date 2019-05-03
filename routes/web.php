<?php

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
    return $router->app->version();
});

$router->group(['middleware' => 'auth'], function () use ($router) {
    $router->post('batch', 'BatchController@create');
    $router->post('batch/{batchId}/checkpoint', 'BatchController@addCheckpoint');
    $router->delete('batch/{batchId}', 'BatchController@delete');
    $router->get('snapshot/{id}', 'SnapshotController@index');
    $router->get('checkpoint/{checkpoint_id}', 'CheckpointController@get');
    $router->get('checkpoint/{checkpoint_id}/image', 'CheckpointController@image');
});
