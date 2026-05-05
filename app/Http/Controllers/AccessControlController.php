<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ProcurementAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AccessControlController extends Controller
{
    public function __construct(
        protected ProcurementAuditService $auditService
    ) {}

    public function index(): InertiaResponse
    {
        $this->authorizeAdmin();

        $users = User::with(['roles:id,name', 'permissions:id,name'])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $roles = Role::query()->orderBy('name')->get(['id', 'name']);
        $permissions = Permission::query()->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Admin/AccessControl', [
            'users' => $users,
            'roles' => $roles,
            'permissions' => $permissions,
            'roleTemplates' => $this->buildRoleTemplates($roles->pluck('name')->all(), $permissions->pluck('name')->all()),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['string', 'exists:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        $beforeRoles = $user->getRoleNames()->values()->all();
        $beforePermissions = $user->getDirectPermissions()->pluck('name')->values()->all();

        $user->syncRoles($validated['roles'] ?? []);
        $user->syncPermissions($validated['permissions'] ?? []);

        $afterRoles = $user->fresh()->getRoleNames()->values()->all();
        $afterPermissions = $user->fresh()->getDirectPermissions()->pluck('name')->values()->all();

        $this->auditService->log(
            Auth::id(),
            'user_access_updated',
            'user',
            $user->id,
            null,
            null,
            [
                'target_user_id' => $user->id,
                'target_user_email' => $user->email,
                'before_roles' => $beforeRoles,
                'after_roles' => $afterRoles,
                'before_permissions' => $beforePermissions,
                'after_permissions' => $afterPermissions,
            ]
        );

        return response()->json(['message' => 'Roles and permissions updated successfully.']);
    }

    protected function buildRoleTemplates(array $roleNames, array $permissionNames): array
    {
        $templates = [];

        if (in_array('Super Admin', $roleNames, true)) {
            $templates[] = [
                'key' => 'super_admin',
                'label' => 'Super Admin (Full Access)',
                'roles' => ['Super Admin'],
                'permissions' => [],
            ];
        }

        if (in_array('Admin', $roleNames, true)) {
            $templates[] = [
                'key' => 'admin',
                'label' => 'Admin (Full Admin Access)',
                'roles' => ['Admin'],
                'permissions' => [],
            ];
        }

        $procurementPermissions = array_values(array_filter($permissionNames, fn($p) => str_starts_with($p, 'rfq.')));
        if (in_array('Procurement Manager', $roleNames, true) || !empty($procurementPermissions)) {
            $templates[] = [
                'key' => 'procurement_manager',
                'label' => 'Procurement Manager',
                'roles' => in_array('Procurement Manager', $roleNames, true) ? ['Procurement Manager'] : [],
                'permissions' => $procurementPermissions,
            ];
        }

        return $templates;
    }

    protected function authorizeAdmin(): void
    {
        $user = Auth::user();

        if ($user && (int) $user->id === 1) {
            return;
        }

        if (!$user || !$user->hasAnyRole(['Super Admin', 'Admin'])) {
            $message = 'Only admin users can manage roles and permissions.';

            if (request()->expectsJson()) {
                throw new HttpResponseException(response()->json([
                    'message' => $message,
                ], 403));
            }

            throw new HttpResponseException(
                redirect()->route('dashboard')->with('accessDenied', [
                    'title' => 'Only admin has access',
                    'status' => 403,
                    'message' => $message,
                ])
            );
        }
    }
}
