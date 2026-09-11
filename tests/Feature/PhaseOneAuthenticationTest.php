<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('authenticates with a normalized phone number', function () {
    User::factory()->create(['email' => 'phone@example.test', 'phone' => '0812 3456 7890', 'phone_normalized' => '6281234567890', 'password' => Hash::make('password'), 'role' => 'dinas']);

    $this->post('/signin', ['login' => '+62 812-3456-7890', 'password' => 'password'])->assertRedirect('/dinas');
});

it('rejects an account that is not active', function () {
    User::factory()->create(['email' => 'pending@example.test', 'password' => Hash::make('password'), 'onboarding_status' => 'pending']);

    $this->from('/signin')->post('/signin', ['login' => 'pending@example.test', 'password' => 'password'])
        ->assertRedirect('/signin')->assertSessionHasErrors('email');
});
