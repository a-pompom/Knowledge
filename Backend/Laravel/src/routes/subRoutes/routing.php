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

    // Optional パラメータ
    Route::get('/category/{name?}', function (string $name='all') {
        return "Category is {$name}";
    });

    // Named Route
    Route::get('/named-route/{id}', function (string $id) {
        return "named route from {$id}";
    })->name('named-route');

    Route::get('/redirect-route', function () {
        return redirect()->route('named-route', ['id' => 'redirectRoute']);
    });
});

