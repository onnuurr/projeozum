<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

use Modules\Creative\Services\Ai\Contracts\MockupGeneratorContract;
use Modules\Creative\Services\Ai\Support\ImageFile;
use RuntimeException;

/**
 * Mokap giydirmenin Gemini sürücüsü. Görsel adımlar config('creative.mockup.image_model')
 * (varsayılan gemini-3.1-flash-image, 3:4, 2K) ile; stilist görsel analizle (analyzeImage).
 */
class GeminiMockupGenerator implements MockupGeneratorContract
{
    public function __construct(
        private GeminiClient $client,
        private MockupPromptBuilder $prompts,
    ) {}

    public function stylist(string $productImagePath, array $context): array
    {
        $raw  = $this->client->analyzeImage($this->prompts->stylist($context), $productImagePath);
        $json = json_decode(trim((string) preg_replace('/^```(json)?|```$/m', '', trim($raw))), true);
        if (! is_array($json)) {
            throw new RuntimeException(__('creative::messages.mockup.stylist_invalid'));
        }

        return $json;
    }

    public function masterBase(string $mannequinReferencePath, string $poseTemplatePath, array $outfit): string
    {
        return $this->image($this->prompts->masterBase($this->outfitText($outfit)), [$mannequinReferencePath, $poseTemplatePath], 'base_image_size');
    }

    public function derivedBase(string $masterPath, string $poseTemplatePath, array $outfit): string
    {
        return $this->image($this->prompts->derivedBase($this->outfitText($outfit)), [$masterPath, $poseTemplatePath], 'base_image_size');
    }

    /** @param array{sentence:string,summary:string,region:string,length:string} $o */
    private function outfitText(array $o): string
    {
        return $this->prompts->outfitText($o['sentence'], $o['summary'], $o['region'], $o['length']);
    }

    public function tryOn(string $cropPath, array $productImagePaths, string $region, string $length, ?string $correction = null): string
    {
        return $this->image($this->prompts->tryOn($region, $length, count($productImagePaths), $correction), [$cropPath, ...$productImagePaths], 'tryon_image_size');
    }

    public function modelIdentifier(): string
    {
        return (string) config('creative.mockup.image_model');
    }

    /**
     * @param  array<int,string>  $images
     * @param  string  $sizeKey  config('creative.mockup.*') çözünürlük anahtarı (base_image_size | tryon_image_size)
     */
    private function image(string $prompt, array $images, string $sizeKey): string
    {
        $bytes = $this->client->generateImage($prompt, $images, $this->modelIdentifier(), GeminiClient::buildImageConfig(
            (string) config('creative.mockup.aspect_ratio', '3:4'),
            (string) config("creative.mockup.{$sizeKey}", '1K'),
        ));

        return ImageFile::temp($bytes, 'png');
    }
}
