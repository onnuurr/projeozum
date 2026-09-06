<?php

namespace Modules\Tenant\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Tenant\Http\Requests\RejectTenantApplicationRequest;
use Modules\Tenant\Http\Requests\StoreTenantApplicationRequest;
use Modules\Tenant\Models\TenantApplication;
use Modules\Tenant\Models\TenantApplicationDocument;
use Modules\Tenant\Services\TenantApplicationService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantApplicationController extends Controller
{
    public function __construct(private TenantApplicationService $service) {}

    public function create(): Response
    {
        return Inertia::render('Tenant::TenantApplicationForm');
    }

    public function store(StoreTenantApplicationRequest $request): RedirectResponse
    {
        $data = $request->safe()->only([
            'name', 'legal_name', 'tax_number', 'tax_office',
            'contact_person', 'contact_email', 'contact_phone',
            'address', 'city', 'district', 'country', 'postal_code',
            'message',
        ]);

        $files = [
            TenantApplicationDocument::TYPE_TAX_CERTIFICATE        => $request->file('vergi_levhasi'),
            TenantApplicationDocument::TYPE_TRADE_REGISTRY_GAZETTE => $request->file('ticaret_sicil_gazetesi'),
            TenantApplicationDocument::TYPE_SIGNATURE_CIRCULAR     => $request->file('imza_sirkuleri'),
        ];

        $this->service->submit($data, $files, $request->ip());

        return redirect()->route('tenant-applications.create')
            ->with('success', 'Başvurunuz alındı. Belgeleriniz incelendikten sonra sizinle iletişime geçilecektir.');
    }

    public function index(Request $request): Response
    {
        $query = TenantApplication::query()->with(['documents', 'reviewer:id,name']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $applications = $query->orderByDesc('id')->get()->map(fn (TenantApplication $a) => [
            'id'               => $a->id,
            'name'             => $a->name,
            'legal_name'       => $a->legal_name,
            'tax_number'       => $a->tax_number,
            'tax_office'       => $a->tax_office,
            'contact_person'   => $a->contact_person,
            'contact_email'    => $a->contact_email,
            'contact_phone'    => $a->contact_phone,
            'address'          => $a->address,
            'city'             => $a->city,
            'district'         => $a->district,
            'message'          => $a->message,
            'status'           => $a->status,
            'rejection_reason' => $a->rejection_reason,
            'reviewer'         => $a->reviewer?->name,
            'reviewedAt'       => optional($a->reviewed_at)->format('Y-m-d H:i'),
            'convertedTenantId'=> $a->converted_tenant_id,
            'documents'        => $a->documents->map(fn (TenantApplicationDocument $d) => [
                'id'   => $d->id,
                'type' => $d->type,
                'name' => $d->original_filename,
            ])->values(),
            'createdAt'        => $a->created_at->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Tenant::TenantApplications', [
            'applications' => $applications,
            'filters'      => ['status' => $request->query('status', '')],
        ]);
    }

    public function approve(TenantApplication $application): RedirectResponse
    {
        $this->service->approve($application, auth()->user());

        return back();
    }

    public function reject(RejectTenantApplicationRequest $request, TenantApplication $application): RedirectResponse
    {
        $this->service->reject($application, $request->user(), $request->validated('rejection_reason'));

        return back();
    }

    public function downloadDocument(TenantApplication $application, TenantApplicationDocument $document): StreamedResponse
    {
        abort_unless($document->tenant_application_id === $application->id, 404);
        abort_unless(Storage::exists($document->path), 404);

        return Storage::download($document->path, $document->original_filename);
    }
}
