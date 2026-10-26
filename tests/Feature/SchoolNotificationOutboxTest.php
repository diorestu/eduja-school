<?php

use App\Models\School;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\SchoolNotificationFeed;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('persists an internal notification once and exposes it only to its recipient in the correct school', function () {
    $school = School::create(['name' => 'Sekolah Notifikasi', 'is_active' => true, 'status' => 'active']);
    $other = School::create(['name' => 'Sekolah Lain', 'is_active' => true, 'status' => 'active']);
    $user = User::factory()->create(['role' => 'guru']);
    $stranger = User::factory()->create(['role' => 'guru']);
    foreach ([$user, $stranger] as $person) {
        $school->roles()->create(['user_id' => $person->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active']);
        $other->roles()->create(['user_id' => $person->id, 'role' => 'guru', 'is_active' => true, 'membership_status' => 'active']);
    }
    $service = app(NotificationService::class);
    $service->notifyUsers($school->id, [$user->id], 'attendance_decision', 'Permohonan disetujui', '/attendance/requests', [], 'test-once');
    $service->notifyUsers($school->id, [$user->id], 'attendance_decision', 'Permohonan disetujui', '/attendance/requests', [], 'test-once');
    $this->assertDatabaseCount('school_notifications', 1);
    session(['active_school_id' => $school->id]);
    expect(app(SchoolNotificationFeed::class)->forUser($user)->pluck('title')->all())->toContain('Permohonan disetujui');
    expect(app(SchoolNotificationFeed::class)->forUser($stranger))->toHaveCount(0);
    session(['active_school_id' => $other->id]);
    expect(app(SchoolNotificationFeed::class)->forUser($user))->toHaveCount(0);
});
