<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProcurementAccessController extends Controller
{
    public function index(): InertiaResponse
    {
        $this->authorizeAdmin();

        $users = User::with(['roles:id,name', 'managedWarehouses:id,name,code'])
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $warehouses = Warehouse::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return Inertia::render('Admin/ProcurementAccess', [
            'users' => $users,
            'warehouses' => $warehouses,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'warehouse_ids' => ['nullable', 'array'],
            'warehouse_ids.*' => ['integer', 'exists:warehouses,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);
        $user->managedWarehouses()->sync($validated['warehouse_ids'] ?? []);

        return response()->json(['message' => 'Warehouse access updated.']);
    }

    protected function authorizeAdmin(): void
    {
        $user = Auth::user();

        if ($user && (int) $user->id === 1) {
            return;
        }

        if (!$user || !$user->hasAnyRole(['Super Admin', 'Admin'])) {
            $message = 'Only admin users can manage procurement access.';

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
