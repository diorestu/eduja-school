<?php

use App\Models\AcademicYear;
use App\Models\School;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows only the active school academic year in the header and keeps support on the sidebar info icon', function () {
    $school = School::create(['name' => 'Header School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    $other = School::create(['name' => 'Foreign School', 'level' => 'sma', 'ownership' => 'swasta', 'is_active' => true, 'status' => 'active']);
    AcademicYear::create(['school_id' => $other->id, 'year' => '2099/2100', 'semester' => 'Genap', 'is_active' => true]);
    AcademicYear::create(['school_id' => $school->id, 'year' => '2026/2027', 'semester' => 'Ganjil', 'is_active' => true]);
    $user = User::factory()->create(['role' => 'bendahara']);
    $school->roles()->create(['user_id' => $user->id, 'role' => 'bendahara', 'is_active' => true, 'membership_status' => 'active']);
    app(NotificationService::class)->notifyUsers($school->id, [$user->id], 'test.header', 'Pembayaran diterima', '/dashboard', [], 'header-test');
    $response = $this->actingAs($user)->withSession(['active_school_id' => $school->id])->get('/dashboard')->assertOk()
        ->assertDontSee('2099/2100')->assertDontSee('Hubungi Support');
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    expect($xpath->query('//header//*[contains(text(), "2026/2027")]')->length)->toBe(1)
        ->and($xpath->query('//aside//*[contains(text(), "Tahun Ajaran")]')->length)->toBe(0)
        ->and($xpath->query('//aside//a[@href="https://wa.me/628179792288"]')->length)->toBe(1)
        ->and($xpath->query('//header//button[@aria-label="Cari menu"]')->length)->toBe(1)
        ->and($xpath->query('//header//button[@aria-controls="school-notifications"]//span[contains(@class, "bg-red-600")]')->length)->toBe(1);
});
