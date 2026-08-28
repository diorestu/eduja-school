# School Role Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menambahkan permission menu tambahan per role dan per sekolah yang diterapkan pada sidebar serta route EDUJA.

**Architecture:** `MenuHelper` menjadi katalog permission, `PermissionService` menggabungkan role bawaan dengan grant sekolah, dan middleware permission menjaga route. Controller admin menyinkronkan grant untuk sekolah aktif dan satu view Blade menyediakan matriks role-menu.

**Tech Stack:** Laravel 12, PHP 8, Blade, Tailwind CSS, Pest, MySQL/SQLite test database.

**Spec:** `docs/superpowers/specs/2026-08-28-school-role-permissions-design.md`

## Global Constraints

- Permission hanya menambah akses; akses bawaan role tidak dapat dicabut.
- Semua query grant wajib dibatasi dengan `active_school_id`.
- Super admin/kepsek memiliki akses penuh dan pengaturan permission tidak dapat didelegasikan.
- Menu dan route harus menggunakan permission key yang sama.

---

### Task 1: Kontrak data dan service permission

**Files:**
- Create: `database/migrations/2026_08_28_000001_create_school_role_permissions_table.php`
- Create: `app/Models/SchoolRolePermission.php`
- Modify: `app/Models/School.php`
- Modify: `app/Services/PermissionService.php`
- Test: `tests/Feature/SchoolRolePermissionTest.php`

**Interfaces:**
- Produces: `PermissionService::can(User $user, string $permission, array $defaultRoles = []): bool`
- Produces: `PermissionService::grantsForRole(int $schoolId, string $role): array`

- [ ] Write failing tests for table existence, additive grant, default-role access, super-admin bypass, and cross-school isolation.
- [ ] Run `php artisan test tests/Feature/SchoolRolePermissionTest.php` and confirm failure because schema/service do not exist.
- [ ] Add migration, model, relationship, and minimal service methods.
- [ ] Run the focused test and confirm it passes.

### Task 2: Katalog menu dan route middleware

**Files:**
- Modify: `app/Helpers/MenuHelper.php`
- Create: `app/Http/Middleware/EnsureUserHasPermission.php`
- Modify: `bootstrap/app.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SchoolRolePermissionTest.php`

**Interfaces:**
- Produces: `MenuHelper::permissionCatalog(): array`
- Produces: middleware alias `permission`

- [ ] Add failing tests proving a granted role sees only the granted submenu and can open its direct route, while an ungranted role receives 403.
- [ ] Run the focused test and confirm the expected failures.
- [ ] Add stable menu keys, submenu filtering, middleware, alias, and replace static role gates with matching permission gates.
- [ ] Run the focused test and existing foundation tests.

### Task 3: Admin permission management

**Files:**
- Create: `app/Http/Controllers/SchoolRolePermissionController.php`
- Create: `resources/views/pages/settings/permissions.blade.php`
- Modify: `routes/web.php`
- Modify: `app/Helpers/MenuHelper.php`
- Test: `tests/Feature/SchoolRolePermissionTest.php`

**Interfaces:**
- Produces: named routes `settings.permissions.index` and `settings.permissions.update`

- [ ] Add failing tests for admin-only page access, input validation, sync behavior, and active-school ownership.
- [ ] Run the focused test and confirm failure.
- [ ] Implement controller, routes, settings menu, and compact role-menu matrix view.
- [ ] Run focused tests and view compilation.

### Task 4: Full verification

**Files:**
- Verify all changed files.

- [ ] Run `php artisan test` and record exact pass/fail counts.
- [ ] Run `php artisan route:list` and inspect permission routes/middleware.
- [ ] Run `php artisan view:cache`.
- [ ] Run `npm run build`.
- [ ] Review the diff against every requirement in the spec.
