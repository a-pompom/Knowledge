<?php

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'routing',
], function () {
    Route::get('/hello', function () {
        return 'Hello Routing';
    });

    // ルートパラメータ
    Route::get('/user/{id}', function (string $id) {
        return "Hello, {$id}";
    });
});

