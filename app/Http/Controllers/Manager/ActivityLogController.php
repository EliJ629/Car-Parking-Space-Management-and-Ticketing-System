<?php

namespace App\Http\Controllers\Manager;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Lot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends ManagerController
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'regex:/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'regex:/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/', 'after_or_equal:from'],
            'date' => ['nullable', 'date_format:Y-m-d', 'after:2000-01-01', 'regex:/^20[0-9]{2}-[0-9]{2}-[0-9]{2}$/'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:100'],
        ], [
            'from.date_format' => 'Enter the start date as year-month-day.',
            'from.after' => 'Choose a start date after the year 2000.',
            'from.regex' => 'Choose a start date between the years 2000 and 2099.',
            'to.date_format' => 'Enter the end date as year-month-day.',
            'to.after' => 'Choose an end date after the year 2000.',
            'to.regex' => 'Choose an end date between the years 2000 and 2099.',
            'to.after_or_equal' => 'The end date cannot be earlier than the start date.',
            'date.date_format' => 'Enter the date as year-month-day.',
            'date.regex' => 'Choose a date between the years 2000 and 2099.',
            'user_id.exists' => 'Choose a person from the list.',
            'q.max' => 'The search can have up to 100 characters.',
        ]);
        $search = trim($filters['q'] ?? '');
        $dateFrom = $filters['from'] ?? $filters['date'] ?? $filters['to'] ?? null;
        $dateTo = $filters['to'] ?? $dateFrom;
        $logs = ActivityLog::query()->with('user')
            ->when($dateFrom !== null, fn ($query) => $query->whereBetween('created_at', [Lot::parse($dateFrom), Lot::parse((string) $dateTo, '23:59:59')]))
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
            'dateFrom' => $dateFrom,
            'dateTo' => $filters['to'] ?? $dateFrom,
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
