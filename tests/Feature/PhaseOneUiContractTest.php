<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exposes phase one auth and identity UI states', function () {
    $this->get('/signin')->assertOk()->assertSee('Email atau nomor HP')->assertSee('Masuk ke dasbor');
    $this->get('/signup')->assertOk()->assertSee('Nomor HP')->assertSee('Saya mendaftar sebagai');
    $this->view('pages.portal.empty-identity', ['title' => 'Portal Siswa', 'message' => 'Belum terhubung', 'action' => 'Hubungi admin'])
        ->assertSee('Belum terhubung')->assertSee('Hubungi admin')->assertSee('Buka profil akun');
});
