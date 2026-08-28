<?php

namespace App\Http\Controllers;

use App\Helpers\MenuHelper;
use App\Models\SchoolRolePermission;
use App\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SchoolRolePermissionController extends Controller
{
    private const ROLE_LABELS = [
        'kepsek' => 'Kepala Sekolah',
        'wakasek' => 'Wakil Kepala Sekolah',
        'bendahara' => 'Bendahara',
        'tu' => 'Tata Usaha',
        'staf_tu' => 'Staf Tata Usaha',
        'guru' => 'Guru',
        'wali_kelas' => 'Wali Kelas',
        'siswa' => 'Siswa',
        'orang_tua' => 'Orang Tua',
        'dinas' => 'Dinas Pendidikan',
        'yayasan' => 'Yayasan',
        'alumni' => 'Alumni',
    ];

    public function index(Request $request, PermissionService $permissionService): View
    {
        $schoolId = (int) session('active_school_id');
        $roles = $this->availableRoles($schoolId);
        $selectedRole = $request->string('role')->toString();

        if (! array_key_exists($selectedRole, $roles)) {
            $selectedRole = array_key_exists('guru', $roles) ? 'guru' : array_key_first($roles);
        }

        $catalog = MenuHelper::permissionCatalog();

        return view('pages.settings.permissions', [
            'title' => 'Permission Role',
            'roles' => $roles,
            'selectedRole' => $selectedRole,
            'catalog' => $catalog,
            'grantedPermissions' => $permissionService->grantsForRole($schoolId, $selectedRole),
            'defaultPermissions' => $this->defaultPermissionsForRole($catalog, $selectedRole),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $schoolId = (int) session('active_school_id');
        $roles = $this->availableRoles($schoolId);
        $catalog = MenuHelper::permissionCatalog();
        $permissionKeys = $this->permissionKeys($catalog);

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in(array_keys($roles))],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ]);

        $role = $validated['role'];
        $defaultPermissions = $this->defaultPermissionsForRole($catalog, $role);
        $permissions = array_values(array_diff(
            array_unique($validated['permissions'] ?? []),
            $defaultPermissions,
        ));

        DB::transaction(function () use ($schoolId, $role, $permissions): void {
            SchoolRolePermission::query()
                ->where('school_id', $schoolId)
                ->where('role', $role)
                ->delete();

            foreach ($permissions as $permission) {
                SchoolRolePermission::create([
                    'school_id' => $schoolId,
                    'role' => $role,
                    'permission' => $permission,
                    'is_allowed' => true,
                ]);
            }
        });

        return redirect()
            ->route('settings.permissions.index', ['role' => $role])
            ->with('success', 'Permission tambahan untuk role berhasil disimpan.');
    }

    private function availableRoles(int $schoolId): array
    {
        $schoolRoles = DB::table('school_user_roles')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->pluck('role')
            ->all();

        $schoolRoles = array_map(fn (string $role) => match ($role) {
            'staf_tu' => 'tu',
            'super_admin' => 'kepsek',
            default => $role,
        }, $schoolRoles);

        $roles = array_unique(array_merge(array_keys(self::ROLE_LABELS), $schoolRoles));
        $roles = array_values(array_filter(
            $roles,
            fn (string $role) => ! in_array($role, ['super_admin', 'kepsek', 'staf_tu'], true),
        ));

        $result = [];
        foreach ($roles as $role) {
            $result[$role] = self::ROLE_LABELS[$role] ?? str($role)->replace('_', ' ')->title()->toString();
        }

        asort($result);

        return $result;
    }

    private function permissionKeys(array $catalog): array
    {
        return collect($catalog)
            ->flatMap(fn (array $group) => collect($group['items'])->pluck('permission'))
            ->unique()
            ->values()
            ->all();
    }

    private function defaultPermissionsForRole(array $catalog, string $role): array
    {
        $equivalentRoles = match ($role) {
            'tu' => ['tu', 'staf_tu'],
            'staf_tu' => ['staf_tu', 'tu'],
            default => [$role],
        };

        return collect($catalog)
            ->flatMap(fn (array $group) => $group['items'])
            ->filter(fn (array $item) => count(array_intersect($equivalentRoles, $item['roles'])) > 0)
            ->pluck('permission')
            ->unique()
            ->values()
            ->all();
    }
}
