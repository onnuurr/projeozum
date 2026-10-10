<?php

namespace Modules\Product\Services\Ai;

use Illuminate\Support\Collection;
use Modules\Atelier\Services\MaterialSpecService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductDescriptionMaterial;
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
        return $this->lines($this->links->resolve($product));
    }

    /**
     * Formda (henüz kaydedilmemiş) seçili materyaller; seçim yoksa kayıtlı ürünün reçetesine düşer.
     *
     * @param  list<array{material_id: int, role: string, sort_order?: int|null}>  $rows
     * @return Collection<int, string>
     */
    public function forDraft(Product $draft, array $rows): Collection
    {
        if ($rows === []) {
            return $draft->id ? $this->forPrompt($draft) : collect();
        }

        // Kaydedilmeyen pivot kayıtları üzerinden yüklenir (Atelier modeline doğrudan bağımlılık yok).
        $links = (new EloquentCollection(collect($rows)
            ->sortBy(fn (array $r, int $i) => $r['sort_order'] ?? $i)
            ->map(fn (array $r) => new ProductDescriptionMaterial(['material_id' => (int) $r['material_id'], 'role' => $r['role']]))
            ->values()
            ->all()))->load('material');

        return $this->lines($links
            ->map(fn (ProductDescriptionMaterial $l) => ['material' => $l->material, 'role' => $l->role])
            ->filter(fn (array $r) => $r['material'] !== null)
            ->values()
            ->toBase());
    }

    /** @return Collection<int, string> */
    private function lines(Collection $rows): Collection
    {
        return $rows->map(function ($row) {
            $roleLabel = self::ROLE_LABELS[$row['role']] ?? 'Materyal';
            $spec      = $this->specs->formatForPrompt($row['material']);
            return "{$roleLabel}: {$spec}";
        });
    }
}
