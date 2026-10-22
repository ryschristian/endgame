<?php

use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->withoutVite();
});

it('renders the Vue application with the configured application name', function () {
    config(['app.name' => 'Endgame Test']);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('appName', 'Endgame Test')
        );
});

it('returns Inertia page data for client navigation', function () {
    $this->get('/')->assertOk();

    $this->get('/', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => Inertia::getVersion(),
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertJsonPath('component', 'welcome')
        ->assertJsonPath('props.appName', config('app.name'));
});

it('requests a full reload when the client asset version is stale', function () {
    $this->get('/', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => 'stale-version',
        'X-Requested-With' => 'XMLHttpRequest',
    ])
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/'));
});

it('serves the health endpoint without a database connection', function () {
    $this->get('/up')->assertOk();
});
