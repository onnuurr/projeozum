<?php

namespace Modules\Superadmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class LogViewerController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->get('tab', 'activity');

        // ── Activity log sorgusu ────────────────────────────────────────────
        $activityQuery = ActivityLog::query()->with('causer:id,name');

        if ($module = $request->string('module')->toString()) {
            $activityQuery->where('module', $module);
        }
        if ($level = $request->string('level')->toString()) {
            $activityQuery->where('level', $level);
        }
        if ($action = $request->string('action')->toString()) {
            $activityQuery->where('action', 'like', "%{$action}%");
        }
        if ($q = $request->string('q')->toString()) {
            $activityQuery->where(function ($qb) use ($q) {
                $qb->where('description', 'like', "%{$q}%")
                   ->orWhere('action', 'like', "%{$q}%");
            });
        }
        if ($dateFrom = $request->string('date_from')->toString()) {
            $activityQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->string('date_to')->toString()) {
            $activityQuery->whereDate('created_at', '<=', $dateTo);
        }
        if ($userId = $request->integer('user')) {
            $activityQuery->where('causer_id', $userId);
        }

        $activity = $activityQuery
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityLog $log) => [
                'id'           => $log->id,
                'module'       => $log->module,
                'action'       => $log->action,
                'description'  => $log->description,
                'level'        => $log->level,
                'causer'       => $log->causer ? ['id' => $log->causer->id, 'name' => $log->causer->name] : null,
                'causerLabel'  => $log->causer_label,
                'ip_address'   => $log->ip_address,
                'properties'   => $log->properties,
                'created_at'   => optional($log->created_at)->format('Y-m-d H:i:s'),
            ]);

        // ── Error log sorgusu ───────────────────────────────────────────────
        $errorQuery = ErrorLog::query()->with('causer:id,name');

        if ($module = $request->string('module')->toString()) {
            $errorQuery->where('module', $module);
        }
        if ($level = $request->string('level')->toString()) {
            $errorQuery->where('level', $level);
        }
        if ($q = $request->string('q')->toString()) {
            $errorQuery->where(function ($qb) use ($q) {
                $qb->where('message', 'like', "%{$q}%")
                   ->orWhere('exception_class', 'like', "%{$q}%");
            });
        }
        if ($dateFrom = $request->string('date_from')->toString()) {
            $errorQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo = $request->string('date_to')->toString()) {
            $errorQuery->whereDate('created_at', '<=', $dateTo);
        }

        $errors = $errorQuery
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ErrorLog $log) => [
                'id'              => $log->id,
                'module'          => $log->module,
                'level'           => $log->level,
                'message'         => $log->message,
                'exception_class' => $log->exception_class,
                'file'            => $log->file,
                'line'            => $log->line,
                'trace'           => $log->trace,
                'url'             => $log->url,
                'method'          => $log->method,
                'causer'          => $log->causer ? ['id' => $log->causer->id, 'name' => $log->causer->name] : null,
                'ip_address'      => $log->ip_address,
                'context'         => $log->context,
                'occurred_at'     => optional($log->occurred_at)->format('Y-m-d H:i:s'),
                'created_at'      => optional($log->created_at)->format('Y-m-d H:i:s'),
            ]);

        // ── Modül listesi (her iki tablodan distinct) ──────────────────────
        $activityModules = ActivityLog::query()
            ->whereNotNull('module')
            ->distinct()
            ->pluck('module');

        $errorModules = ErrorLog::query()
            ->whereNotNull('module')
            ->distinct()
            ->pluck('module');

        $modules = $activityModules->merge($errorModules)->unique()->sort()->values();

        return Inertia::render('Superadmin::LogViewer', [
            'activity'  => $activity,
            'errors'    => $errors,
            'modules'   => $modules,
            'activeTab' => $tab,
            'filters'   => [
                'tab'       => $tab,
                'module'    => $request->string('module')->toString(),
                'level'     => $request->string('level')->toString(),
                'action'    => $request->string('action')->toString(),
                'q'         => $request->string('q')->toString(),
                'date_from' => $request->string('date_from')->toString(),
                'date_to'   => $request->string('date_to')->toString(),
                'user'      => $request->integer('user') ?: null,
            ],
        ]);
    }
}
