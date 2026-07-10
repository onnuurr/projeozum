<?php

namespace Modules\Creative\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Modules\Creative\Models\Mannequin;
use Modules\Creative\Models\ReviewChat;
use Modules\Creative\Models\TryonResult;
use Modules\Creative\Services\Ai\Drivers\Gemini\GeminiClient;

/**
 * Reddedilen bir manken/giydirme görseli için düzeltme sohbeti.
 *
 * Ret gerekçesini ve orijinal üretim parametrelerini bağlama katarak Gemini ile
 * (GeminiClient::generateText, ek sağlayıcı gerekmez) diyalog kurar. Her asistan
 * yanıtı, ayrıştırılabilir bir "FINAL_INSTRUCTION:" satırıyla biter — bu, en son
 * önerilen düzeltme talimatı olarak subject'in meta'sında saklanır ve "yeniden
 * üret" aksiyonu bunu kullanır (bkz. ReviewChatController::apply).
 */
class ReviewChatService
{
    public function __construct(private GeminiClient $client) {}

    /**
     * Kullanıcı mesajını kaydeder, Gemini'den yanıt alır, asistan mesajını kaydeder.
     *
     * @return array{reply: string, suggested_instruction: ?string}
     */
    public function converse(Mannequin|TryonResult $subject, User $user, string $message): array
    {
        $message = trim($message);

        $this->store($subject, $user, ReviewChat::ROLE_USER, $message);

        $prompt = $this->buildPrompt($subject, $message);
        $raw    = $this->client->generateText($prompt);

        [$reply, $suggestion] = $this->parseReply($raw);

        $this->store($subject, $user, ReviewChat::ROLE_ASSISTANT, $reply);

        if ($suggestion !== null) {
            $subject->update(['meta' => array_merge($subject->meta ?? [], [
                'chat_suggested_instruction' => $suggestion,
            ])]);
        }

        return ['reply' => $reply, 'suggested_instruction' => $suggestion];
    }

    /**
     * @return Collection<int,ReviewChat>
     */
    public function history(Mannequin|TryonResult $subject): Collection
    {
        return $subject->reviewChats()->orderBy('created_at')->get();
    }

    /**
     * En son sohbetten çıkan, henüz uygulanmamış düzeltme talimatı (varsa).
     */
    public function latestSuggestion(Mannequin|TryonResult $subject): ?string
    {
        $value = $subject->meta['chat_suggested_instruction'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function store(Mannequin|TryonResult $subject, User $user, string $role, string $content): ReviewChat
    {
        return $subject->reviewChats()->create([
            'user_id'    => $user->id,
            'role'       => $role,
            'content'    => $content,
            'created_at' => now(),
        ]);
    }

    private function buildPrompt(Mannequin|TryonResult $subject, string $newMessage): string
    {
        $type    = $subject instanceof Mannequin ? 'sanal manken kimlik görseli' : 'ürün giydirme (try-on) görseli';
        $context = $subject instanceof Mannequin ? $this->mannequinContext($subject) : $this->tryonContext($subject);

        $history = $this->history($subject)
            ->map(fn (ReviewChat $c) => ($c->role === ReviewChat::ROLE_USER ? 'Kullanıcı' : 'Asistan') . ': ' . $c->content)
            ->implode("\n");

        return <<<PROMPT
            Sen bir görsel üretim prompt mühendisisin. Bir yönetici, üretilen bir {$type} görselini
            şu gerekçeyle REDDETTİ: "{$subject->review_note}".

            Orijinal üretim parametreleri:
            {$context}

            Görevin: konuşan kişiyle (görseli üreten hesap ya da bir yönetici olabilir) sohbet ederek
            sorunu netleştirmek; sonunda görsel üretim modeline (Gemini image) doğrudan EKLENEBİLECEK,
            İngilizce, kısa ve somut bir düzeltme talimatı üretmek. Bu talimat mevcut yapıyı (kimlik,
            ölçü, ürün, poz) BOZMAMALI — sadece ret gerekçesindeki sorunu (ışık, detay, açı, giydirme
            doğruluğu vb.) hedeflemeli. Yanıtların Türkçe, ürettiğin talimat İngilizce olsun.

            Her yanıtının EN SONUNA, ayrı bir satırda, o ana kadarki bilgiye göre en iyi düzeltme
            talimatını şu formatta ekle (yeterli bilgi yoksa bu satırı hiç yazma):
            FINAL_INSTRUCTION: <İngilizce, tek cümlelik somut talimat>

            Konuşma geçmişi:
            {$history}

            Kullanıcı: {$newMessage}
            Asistan:
            PROMPT;
    }

    private function mannequinContext(Mannequin $m): string
    {
        $lines = [
            "- Cinsiyet: {$m->gender}",
            "- Yaş aralığı: {$m->age_range}",
            "- Ten tonu: {$m->skin_tone}",
            "- Vücut tipi: {$m->body_type}",
            "- Saç: {$m->hair}",
            "- Yüz tarifi: {$m->face}",
            "- Ölçüler: boy {$m->height_cm}cm, göğüs {$m->bust_cm}cm, bel {$m->waist_cm}cm, kalça {$m->hips_cm}cm",
            "- Mevcut ek tarif (extras): " . ($m->extras ?: '—'),
        ];

        return implode("\n", $lines);
    }

    private function tryonContext(TryonResult $r): string
    {
        $r->loadMissing(['product:id,name', 'mannequin:id,name', 'pose:id,label,prompt']);

        $lines = [
            '- Ürün: ' . ($r->product?->name ?? '—'),
            '- Manken: ' . ($r->mannequin?->name ?? '—'),
            '- Poz: ' . ($r->pose?->label ?? '—') . ' (' . ($r->pose?->prompt ?? '—') . ')',
            '- Mevcut ek talimat: ' . (($r->meta['extra_instructions'] ?? null) ?: '—'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @return array{0: string, 1: ?string}  [görünür yanıt, FINAL_INSTRUCTION (varsa)]
     */
    private function parseReply(string $raw): array
    {
        if (preg_match('/^FINAL_INSTRUCTION:\s*(.+)$/mi', $raw, $matches)) {
            $suggestion = trim($matches[1]);
            $visible    = trim(preg_replace('/^FINAL_INSTRUCTION:.*$/mi', '', $raw));

            return [$visible !== '' ? $visible : $raw, $suggestion !== '' ? $suggestion : null];
        }

        return [$raw, null];
    }
}
