<?php

use Tests\TestCase;

uses(TestCase::class)->in('Feature');

uses()->beforeEach(function () {
    $this->withoutVite();
    $connection = config('database.default');
    $database = config("database.connections.$connection.database");
    if (! ($connection === 'sqlite' && $database === ':memory:') && ! str_starts_with($database, 'endgame_p01_test_')) {
        throw new RuntimeException('Account tests require an isolated in-memory database or an endgame_p01_test_ database.');
    }
    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    Illuminate\Support\Facades\DB::beginTransaction();
})->afterEach(function () {
    while (Illuminate\Support\Facades\DB::transactionLevel() > 0) {
        Illuminate\Support\Facades\DB::rollBack();
    }
})->in('Feature/Accounts');
