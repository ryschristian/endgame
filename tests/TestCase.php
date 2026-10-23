<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function signIn(\App\Models\User $user): static
    {
        $this->actingAs($user);
        return $this->withSession(['auth_version' => $user->auth_version, \Illuminate\Support\Facades\Auth::guard()->getName() => $user->id]);
    }
}
