<?php

namespace Modules\Atelier\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Atelier\Models\Assignment;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\AssignmentService;

class AssignmentController extends Controller
{
    public function __construct(private AssignmentService $service) {}

    public function index(Request $request): Response
    {
        $disk    = (string) config('atelier.conversion.disk', 'public');
        $userId  = $request->user()?->id;
        $mine    = $request->boolean('mine');
        $status  = (string) $request->input('status', '');

        $assignments = Assignment::query()
            ->with(['assignee:id,name', 'assigner:id,name', 'pattern:id,name,product_type', 'designCard:id,product_type'])
            ->when($mine, fn ($q) => $q->where('assigned_to', $userId))
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->latest()
            ->limit(150)
            ->get()
            ->map(fn (Assignment $a) => [
                'id'            => $a->id,
                'title'         => $a->title,
                'kind'          => $a->kind,
                'status'        => $a->status,
                'instructions'  => $a->instructions,
                'dueDate'       => optional($a->due_date)->format('Y-m-d'),
                'isLate'        => $a->due_date && $a->due_date->isPast() && $a->isOpen(),
                'assignee'      => $a->assignee ? ['id' => $a->assignee->id, 'name' => $a->assignee->name] : null,
                'assigner'      => $a->assigner?->name,
                'pattern'       => $a->pattern ? ['id' => $a->pattern->id, 'name' => $a->pattern->name] : null,
                'designCardId'  => $a->design_card_id,
                'deliveredUrl'  => $a->delivered_file_path ? Storage::disk($disk)->url($a->delivered_file_path) : null,
                'deliveredAt'   => optional($a->delivered_at)->format('Y-m-d H:i'),
                'reviewNote'    => $a->review_note,
                'isMine'        => $a->assigned_to === $userId,
                'createdAt'     => $a->created_at?->format('Y-m-d'),
            ]);

        $counts = Assignment::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return Inertia::render('Atelier::Assignments', [
            'assignments' => $assignments,
            'filters'     => ['mine' => $mine, 'status' => $status],
            'counts'      => $counts,
            'canManage'   => $request->user()?->can('atelier.assignment.manage') ?? false,
            'assignees'   => User::query()->orderBy('name')->get(['id', 'name']),
            'patterns'    => Pattern::query()->where('status', Pattern::STATUS_APPROVED)
                ->orderBy('name')->get(['id', 'name', 'product_type']),
            'designCards' => DesignCard::query()->latest()->limit(50)
                ->get(['id', 'product_type', 'prompt'])
                ->map(fn ($c) => ['id' => $c->id, 'label' => trim(($c->product_type ?? 'konsept') . ' · ' . \Illuminate\Support\Str::limit($c->prompt, 30))]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'design_card_id' => ['nullable', 'integer', Rule::exists('design_cards', 'id')],
            'pattern_id'     => ['nullable', 'integer', Rule::exists('patterns', 'id')],
            'kind'           => ['required', Rule::in([Assignment::KIND_PATTERN_MAKER, Assignment::KIND_DESIGNER])],
            'assigned_to'    => ['required', 'integer', Rule::exists('users', 'id')],
            'title'          => ['required', 'string', 'max:191'],
            'instructions'   => ['nullable', 'string', 'max:2000'],
            'due_date'       => ['nullable', 'date'],
        ]);

        try {
            $this->service->assign($data, $request->user()?->id);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['pattern_id' => $e->getMessage()]);
        }

        return redirect()->route('atelier.assignments.index')->with('success', 'İş atandı.');
    }

    public function start(Request $request, Assignment $assignment): RedirectResponse
    {
        return $this->runTransition($request, $assignment, fn () => $this->service->start($assignment), actorAllowed: true);
    }

    public function deliver(Request $request, Assignment $assignment): RedirectResponse
    {
        $request->validate([
            'file' => ['nullable', 'file', 'max:51200'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->runTransition(
            $request, $assignment,
            fn () => $this->service->deliver($assignment, $request->file('file'), $request->input('note')),
            actorAllowed: true,
        );
    }

    public function accept(Request $request, Assignment $assignment): RedirectResponse
    {
        return $this->runTransition($request, $assignment, fn () => $this->service->accept($assignment, $request->input('note')), actorAllowed: false);
    }

    public function reject(Request $request, Assignment $assignment): RedirectResponse
    {
        return $this->runTransition($request, $assignment, fn () => $this->service->reject($assignment, $request->input('note')), actorAllowed: false);
    }

    /**
     * Geçiş çalıştırıcısı + yetki kapısı.
     *
     * $actorAllowed=true ise atanan kişi de yapabilir (başla/teslim); değilse
     * yalnızca koordinatör (kabul/ret). Geçersiz durum geçişi withErrors döner.
     */
    private function runTransition(Request $request, Assignment $assignment, callable $action, bool $actorAllowed): RedirectResponse
    {
        $user      = $request->user();
        $canManage = $user?->can('atelier.assignment.manage') ?? false;
        $isActor   = $actorAllowed && $assignment->assigned_to === $user?->id;

        if (! $canManage && ! $isActor) {
            abort(403);
        }

        try {
            $action();
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('atelier.assignments.index')->with('success', 'Atama güncellendi.');
    }
}
