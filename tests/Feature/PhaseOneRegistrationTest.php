<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a normalized phone number during registration', function () {
    $this->post('/signup', [
        'registration_type' => 'guru', 'fname' => 'Guru', 'lname' => 'Baru',
        'email' => 'guru-phone@example.test', 'phone' => '0812 3456 7890', 'password' => 'password',
    ])->assertRedirect();

    expect(User::where('email', 'guru-phone@example.test')->value('phone_normalized'))->toBe('6281234567890');
});
