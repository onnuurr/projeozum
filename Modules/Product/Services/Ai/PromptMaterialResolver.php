<?php

namespace Modules\Product\Services\Ai;

use Illuminate\Support\Collection;
use Modules\Atelier\Services\MaterialSpecService;
use Modules\Product\Models\Product;
use Modules\Product\Services\ProductMaterialLinkService;

/**
 * Ürüne bağlı materyalleri (BOM veya pivot) prompt'a hazır satır dizisine çevirir.
 */
class PromptMaterialResolver
{
    private const ROLE_LABELS = [
        'primary_fabric' => 'Ana kumaş',
        'secondary'      => 'Ek kumaş',
        'trim'           => 'Aksesuar/tela',
        'accessory'      => 'Süsleme',
        'label'          => 'Etiket',
    ];

    public function __construct(
        private ProductMaterialLinkService $links,
        private MaterialSpecService $specs,
    ) {}

    /**
     * @return Collection<int, string>  Her satır: "Ana kumaş: Pamuklu Süprem — %100 pamuk, 180 gsm"
     */
    public function forPrompt(Product $product): Collection
    {
        $rows = $this->links->resolve($product);

        return $rows->map(function ($row) {
            $roleLabel = self::ROLE_LABELS[$row['role']] ?? 'Materyal';
            $spec      = $this->specs->formatForPrompt($row['material']);
            return "{$roleLabel}: {$spec}";
        });
    }
}
