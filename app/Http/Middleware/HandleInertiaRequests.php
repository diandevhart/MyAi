<?php

namespace App\Http\Middleware;

use App\Models\InternalRfqRequest;
use App\Models\SupplierQuoteRequest;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $pendingInternal = 0;
        $overdueRfqs = 0;
        $notificationsPreview = [];
        $unreadNotifications = 0;

        if ($request->user()) {
            $pendingInternal = InternalRfqRequest::where('status', 'pending')->count();
            $overdueRfqs = SupplierQuoteRequest::whereIn('status', ['sent', 'quoted'])
                ->whereDate('due_date', '<', now()->toDateString())
                ->count();
            $unreadNotifications = $request->user()->unreadNotifications()->count();
            $notificationsPreview = $request->user()->notifications()
                ->latest()
                ->limit(5)
                ->get(['id', 'data', 'read_at', 'created_at'])
                ->map(fn($n) => [
                    'id' => $n->id,
                    'data' => $n->data,
                    'read_at' => $n->read_at,
                    'created_at' => $n->created_at,
                ])
                ->toArray();
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'opsAlerts' => [
                'pending_internal_requests' => $pendingInternal,
                'overdue_rfqs' => $overdueRfqs,
                'total' => $pendingInternal + $overdueRfqs,
            ],
            'notifications' => [
                'unread_count' => $unreadNotifications,
                'preview' => $notificationsPreview,
            ],
            'flash' => [
                'accessDenied' => fn() => $request->session()->get('accessDenied'),
            ],
        ];
    }
}
