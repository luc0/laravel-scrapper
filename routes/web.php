<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Article;

Route::get('/', ['as' => 'users.list', 'uses' => 'ArticleController@index']);

Route::get('/update', ['as' => 'users.list', 'uses' => 'ArticleController@update']);
