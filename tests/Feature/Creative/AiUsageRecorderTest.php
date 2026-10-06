<?php

namespace Tests\Feature\Creative;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Modules\Creative\Models\AiUsage;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\MockupOutfit;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;
use Modules\Creative\Services\Ai\Support\AiUsageRecorder;
use Modules\Product\Models\Product;
use Tests\TestCase;

/**
 * AI maliyet kaydı: her başarılı Gemini/fal çağrısı tahmini maliyetiyle creative_ai_usages'a
 * yazılır, iş bağlamına (forSubject) bağlanır; kayıt hatası üretimi bozmaz.
 */
class AiUsageRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function imageResponse(): array
    {
        return [
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'image/png', 'data' => base64_encode(UploadedFile::fake()->image('o.png', 4, 4)->getContent())]]]]]],
            'usageMetadata' => ['promptTokenCount' => 4000, 'candidatesTokenCount' => 1680],
        ];
    }

    public function test_image_call_is_priced_by_requested_size_plus_input_tokens(): void
    {
        app(AiUsageRecorder::class)->recordGemini('image', 'gemini-3.1-flash-image',
            ['generationConfig' => ['imageConfig' => ['imageSize' => '2K']]], $this->imageResponse());

        $u = AiUsage::sole();
        $this->assertSame('2K', $u->image_size);
        $this->assertSame(1, $u->images_out);
        // 1 × 0.101 (2K) + 4000 token × 0.50 $/M = 0.103
        $this->assertEqualsWithDelta(0.103, $u->est_cost_usd, 0.00001);
    }

    public function test_image_call_adds_thinking_and_text_output_at_text_price(): void
    {
        $response = $this->imageResponse();
        // Gerçek yanıt biçimi: ayrıntıda yalnız IMAGE; metin farkı toplamda (candidatesTokenCount).
        $response['usageMetadata'] = ['promptTokenCount' => 4000, 'candidatesTokenCount' => 101_680, 'thoughtsTokenCount' => 200_000,
            'candidatesTokensDetails' => [['modality' => 'IMAGE', 'tokenCount' => 1680]]];
        app(AiUsageRecorder::class)->recordGemini('image', 'gemini-3.1-flash-image',
            ['generationConfig' => ['imageConfig' => ['imageSize' => '2K']]], $response);

        // 0.101 + 4000 × 0.50/M + (200k düşünme + 100k metin) × 3/M = 0.103 + 0.90; görsel token'ı ikinci kez sayılmaz
        $this->assertEqualsWithDelta(1.003, AiUsage::sole()->est_cost_usd, 0.00001);
    }

    public function test_text_call_uses_token_prices_and_preview_model_matches_prefix(): void
    {
        app(AiUsageRecorder::class)->recordGemini('analyze', 'gemini-2.5-flash-preview-09', [], [
            'candidates' => [['content' => ['parts' => [['text' => '{}']]]]],
            'usageMetadata' => ['promptTokenCount' => 1_000_000, 'candidatesTokenCount' => 100_000, 'thoughtsTokenCount' => 100_000],
        ]);

        // 1M × 0.30 + 200k × 2.50/M = 0.80 (önek eşleşmesi: gemini-2.5-flash, -lite değil)
        $this->assertEqualsWithDelta(0.80, AiUsage::sole()->est_cost_usd, 0.00001);
    }

    public function test_calls_are_attached_to_the_innermost_subject(): void
    {
        $outfit = MockupOutfit::forceCreate(['product_id' => Product::factory()->create()->id,
            'mannequin_id' => Mannequin::create(['name' => 'M', 'status' => 'ready'])->id,
            'region' => 'bottom', 'length' => 'mini', 'length_source' => 'attribute']);
        $recorder = app(AiUsageRecorder::class);

        $recorder->forSubject($outfit, function () use ($recorder) {
            $recorder->recordFal('fal-ai/fashn/tryon/v1.6');
        });
        $recorder->recordFal('unknown-model');

        $rows = AiUsage::orderBy('id')->get();
        $this->assertSame($outfit->id, (int) $rows[0]->subject_id);
        $this->assertEqualsWithDelta(0.075, $rows[0]->est_cost_usd, 0.00001);
        $this->assertNull($rows[1]->subject_id);
        $this->assertEqualsWithDelta(0.05, $rows[1]->est_cost_usd, 0.00001);   // fal default fiyat
    }

    public function test_gemini_client_records_successful_calls_only(): void
    {
        config(['creative.ai.gemini.api_key' => 'test-key']);
        Http::fake([
            '*gemini-3.1-flash-image:generateContent' => Http::response($this->imageResponse()),
            '*gemini-2.5-flash:generateContent'       => Http::response(['error' => ['status' => 'PERMISSION_DENIED', 'message' => 'dunning']], 403),
        ]);
        $client = app(GeminiClient::class);

        $client->generateImage('p', [], 'gemini-3.1-flash-image', GeminiClient::buildImageConfig('3:4', '2K'));
        try {
            config(['creative.ai.gemini.text_model' => 'gemini-2.5-flash']);
            $client->generateText('ping');
        } catch (\Throwable) {
        }

        $this->assertSame(1, AiUsage::count());
        $this->assertSame('2K', AiUsage::sole()->image_size);
    }

    public function test_recording_failure_never_breaks_generation(): void
    {
        Schema::drop('creative_ai_usages');
        config(['creative.ai.gemini.api_key' => 'test-key']);
        Http::fake(['*' => Http::response($this->imageResponse())]);

        $path = app(GeminiClient::class)->generateImage('p', [], 'gemini-3.1-flash-image');

        $this->assertNotSame('', $path);
    }

    public function test_mockup_command_aborts_without_confirmation_and_makes_no_calls(): void
    {
        Queue::fake();

        $this->artisan('creative:mockup', ['product' => Product::factory()->create()->id, '--mannequin' => Mannequin::create(['name' => 'M', 'status' => 'ready'])->id])
            ->expectsOutputToContain('Tahmini maliyet: 6 görsel')
            ->expectsConfirmation('Devam edilsin mi?', 'no')
            ->assertFailed();

        $this->assertSame(0, MockupOutfit::count());
        $this->assertSame(0, AiUsage::count());
    }
}
