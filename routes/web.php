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

$router->group(['middleware' => 'auth:repo'], function () use ($router) {
    $router->post('batch', 'BatchController@create');
    $router->post('batch/{batchId}/checkpoint', 'BatchController@addCheckpoint');
    $router->delete('batch/{batchId}', 'BatchController@delete');
});

$router->group([], function () use ($router) {
    $router->get('snapshot/{id}', ['as' => 'snapshot.show', 'uses' => 'SnapshotController@show']);
    $router->get('checkpoint/{id}', ['as' => 'checkpoint.show', 'uses' => 'CheckpointController@show']);
    $router->put('checkpoint/{id}/approve', ['as' => 'checkpoint.approve', 'uses' => 'CheckpointController@approve']);
    $router->put('checkpoint/{id}/reject', ['as' => 'checkpoint.reject', 'uses' => 'CheckpointController@reject']);
    $router->put('checkpoint/{id}/ignore', ['as' => 'checkpoint.ignore', 'uses' => 'CheckpointController@ignore']);
    $router->post('diff', 'DiffController@submit');
});
