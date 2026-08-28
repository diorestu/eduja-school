<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('renders the authenticated mobile header logo inside explicit size constraints', function () {
    $user = User::factory()->create(['role' => 'super_admin']);
    $schoolId = DB::table('schools')->insertGetId([
        'name' => 'Sekolah Header Mobile',
        'level' => 'sma',
        'ownership' => 'swasta',
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('school_user_roles')->insert([
        'school_id' => $schoolId,
        'user_id' => $user->id,
        'role' => 'kepsek',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $html = $this->actingAs($user)
        ->withSession(['active_school_id' => $schoolId])
        ->get('/dashboard')
        ->assertOk()
        ->getContent();

    $document = new DOMDocument;
    @$document->loadHTML($html);
    $xpath = new DOMXPath($document);
    $logoLink = $xpath->query('//header//a[@href="/dashboard" and contains(concat(" ", normalize-space(@class), " "), " xl:hidden ")]')->item(0);
    $logo = $xpath->query('.//img[contains(concat(" ", normalize-space(@class), " "), " dark:hidden ")]', $logoLink)->item(0);

    expect(preg_split('/\s+/', trim($logoLink->getAttribute('class'))))
        ->toContain('flex-1', 'min-w-0', 'justify-center');
    expect(preg_split('/\s+/', trim($logo->getAttribute('class'))))
        ->toContain('h-7', 'w-auto', 'max-w-full', 'object-contain');
});
