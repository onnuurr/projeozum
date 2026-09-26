<?php

namespace Modules\Bagisto\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;
use Modules\Bagisto\Exceptions\OrderReturnException;
use Modules\Bagisto\Services\BagistoOrderSyncService;
use Modules\Bagisto\Services\BagistoReturnService;
use Modules\Product\Models\OrderReturn;

/**
 * Bayi portalı (Bagisto ozmbayi) iade sayfası → iade faturası yükleme köprüsü.
 * Bagisto oturumdaki müşterinin e-postasını gönderir; iade o bayiye ait değilse 404
 * (başkasının iadesinin varlığı da sızdırılmaz).
 */
class ReturnInvoiceApiController extends Controller
{
    public function __construct(
        private BagistoReturnService $returns,
        private BagistoOrderSyncService $orders,
    ) {}

    public function store(Request $request, string $rmaId): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'invoice_no' => ['required', 'string', 'max:64'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'invoice_amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'file' => ['required', File::default()->max(10 * 1024)->extensions(['pdf', 'xml', 'jpg', 'jpeg', 'png'])],
        ]);

        $return = $this->findForDealer($rmaId, $data['email']);

        try {
            $return = $this->returns->recordInvoice(
                $return,
                $request->file('file'),
                $data['invoice_no'],
                $data['invoice_date'],
                (float) $data['invoice_amount'],
            );
        } catch (OrderReturnException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json(['data' => $this->returns->statePayload($return)]);
    }

    private function findForDealer(string $rmaId, string $email): OrderReturn
    {
        $tenant = $this->orders->resolveTenantForEmail($email);

        abort_unless($tenant, 404, 'İade bulunamadı.');

        return OrderReturn::query()
            ->where('bagisto_rma_id', $rmaId)
            ->where('tenant_id', $tenant->id)
            ->firstOr(fn () => abort(404, 'İade bulunamadı.'));
    }
}
