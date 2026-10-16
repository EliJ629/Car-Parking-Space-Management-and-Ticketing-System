<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends ManagerController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['q'] ?? '');
        $logs = ActivityLog::query()->with('user')
            ->when($filters['date'] ?? null, fn ($query, string $date) => $query->whereDate('created_at', $date))
            ->when($filters['user_id'] ?? null, fn ($query, int $id) => $query->where('user_id', $id))
            ->when($filters['action'] ?? null, fn ($query, string $action) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('subject', 'like', "%{$search}%")
                ->orWhere('action', 'like', "%{$search}%")
                ->orWhere('area', 'like', "%{$search}%")))
            ->latest('created_at')->paginate(20)->withQueryString();

        return $this->managerPage('Activity log', 'activity', view('staff.manager.activity.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::query()->whereIn('role', User::STAFF_ROLES)->orderBy('name')->get(['id', 'name']),
            'actions' => ActivityLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]));
    }

    public function show(ActivityLog $activityLog): View
    {
        return $this->managerPage('Activity detail', 'activity', view('staff.manager.activity.show', [
            'entry' => $activityLog->load('user'),
        ]));
    }
}
