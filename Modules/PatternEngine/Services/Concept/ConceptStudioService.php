<?php

namespace Modules\Atelier\Services\Concept;

use InvalidArgumentException;
use Modules\Atelier\Models\DesignCard;
use Modules\Atelier\Models\Pattern;
use Modules\Atelier\Services\Concept\Contracts\ConceptImageGeneratorContract;
use Throwable;

/**
 * AI Konsept Stüdyosu orkestratörü (Kol 2).
 *
 * İki akış, tek veri modeli (yol haritası §9.2):
 *  - Akış X (pattern_first): kullanıcı önce kalıbı seçer → konsept baştan kalıba bağlı doğar.
 *  - Akış Y (concept_first): önce konsept üretilir (pattern_id null) → sonra kalıpla eşlenir.
 */
class ConceptStudioService
{
    public function __construct(private ConceptImageGeneratorContract $generator) {}

    /**
     * Tariften 'üretiliyor' bir tasarım kartı oluşturur (anında listede görünür).
     * Asıl görsel üretimi arka planda GenerateConceptJob → runGeneration ile yapılır.
     */
    public function create(ConceptRequest $request, ?int $createdBy = null): DesignCard
    {
        // Akış X: kalıp baştan seçili olmalı ve gerçekten var olmalı.
        if ($request->source === DesignCard::SOURCE_PATTERN_FIRST) {
            if (! $request->patternId || ! Pattern::whereKey($request->patternId)->exists()) {
                throw new InvalidArgumentException('Akış X (önce kalıp) için geçerli bir kalıp seçilmelidir.');
            }
        }

        return DesignCard::create([
            'source'            => $request->source,
            'prompt'            => $request->description,
            'product_type'      => $request->productType,
            'target_size'       => $request->sizeRange,
            'generated_images'  => [],
            'pattern_id'        => $request->source === DesignCard::SOURCE_PATTERN_FIRST ? $request->patternId : null,
            'status'            => DesignCard::STATUS_DRAFT,
            'generation_status' => DesignCard::GENERATION_PROCESSING,
            'request_params'    => $request->toArray(),
            'created_by'        => $createdBy,
        ]);
    }

    /**
     * Kartın saklı tarifinden görselleri üretir ve kartı 'generated'/'done' yapar.
     * Hata olursa 'failed' işaretler ve fırlatır (kuyruk yeniden dener).
     */
    public function runGeneration(DesignCard $card): DesignCard
    {
        $request = ConceptRequest::fromArray($card->request_params ?? []);

        try {
            $images = $this->generator->generate($request);

            $card->update([
                'generated_images'  => $images,
                'status'            => DesignCard::STATUS_GENERATED,
                'generation_status' => DesignCard::GENERATION_DONE,
                'generation_error'  => null,
            ]);
        } catch (Throwable $e) {
            $card->update([
                'generation_status' => DesignCard::GENERATION_FAILED,
                'generation_error'  => mb_substr($e->getMessage(), 0, 1000),
            ]);

            throw $e;
        }

        return $card->fresh();
    }

    /**
     * Akış Y: üretilmiş konsepti önerilen kalıpla eşler.
     */
    public function matchPattern(DesignCard $card, Pattern $pattern): DesignCard
    {
        $card->update([
            'pattern_id' => $pattern->id,
            'status'     => DesignCard::STATUS_MATCHED,
        ]);

        return $card->fresh();
    }
}
