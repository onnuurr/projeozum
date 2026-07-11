<?php

namespace Modules\Creative\Services\Ai\Drivers\Gemini;

/**
 * Manken kimlik ve try-on prompt'larının paylaşılan SABİT cümleleri.
 *
 * Üç builder (kimlik, poz, try-on) aynı gerçekçilik/kamera/kumaş çapalarını
 * kullandığından tekrarı önlemek için tek kaynakta toplanır. Metinler config
 * (creative.ai.prompt) üzerinden ayarlanabilir.
 */
class PromptDirectives
{
    /**
     * "photorealistic" kelime-yasağı açık mı? (Flux/MJ/SD3 folkloru; Gemini için
     * varsayılan kapalı, A/B ile açılır — bkz. config.)
     */
    public static function banPhotorealisticWording(): bool
    {
        return (bool) config('creative.ai.prompt.ban_photorealistic_wording', false);
    }

    /**
     * Anti-AI (plastik/3D cila kırıcı) gerçekçilik çapası.
     *
     * Yasak açıkken "photorealistic" kelimesi kullanılmaz; her iki durumda da
     * "unedited raw studio photography" dili ve cilt/göz/el/anatomi çapaları kalır.
     */
    public static function realism(?bool $banWord = null): string
    {
        $ban = $banWord ?? self::banPhotorealisticWording();

        $lead = $ban
            ? 'The absolute core focus is unedited, raw studio photography realism — with absolutely zero plastic, airbrushed or 3D-rendered look.'
            : 'Rendered with unedited, raw studio photography realism and photorealistic detail — with absolutely zero plastic, airbrushed or 3D-rendered look.';

        $core = 'Skin shows real pores and fine texture, individual hair strands, lifelike eyes with '
            . 'natural catchlights, anatomically correct body and realistic hands with five fingers each, '
            . 'and true-to-life human proportions. It must look like a genuine, unretouched photograph of a '
            . 'real living person — not a 3D render, not CGI, not an illustration, not a painting, not a doll '
            . 'and not a store mannequin.';

        return $lead . ' ' . $core;
    }

    /**
     * Kumaş mühendisliği çapası: giysinin mankene "yapıştırılmış" durmasını önler,
     * ışığın kumaş katlarına gömülmesini (ambient occlusion) zorlar. Yalnız ürün
     * giydirmede (try-on) anlamlıdır.
     */
    public static function fabric(): string
    {
        return 'The garment must display identical, high-definition textile micro-textures, crisp fabric '
            . 'grain, accurate stitching seams and deep, realistic ambient occlusion shadows within the '
            . 'fabric folds caused naturally by the body posture. Unnatural uniform rolling or smooth digital '
            . 'gradients on the clothes are strictly prohibited; ensure realistic complex micro-creases at the '
            . 'rolled cuffs and seams.';
    }

    /**
     * Sabit ışık/kamera metadata dili (raw studio + Hasselblad). Config'ten okunur.
     */
    public static function camera(): string
    {
        return (string) config(
            'creative.ai.prompt.camera_directive',
            'Shot on a medium format Hasselblad H6D camera with an 85mm lens at f/4.0, '
            . 'lit by soft, directional natural window daylight from the side, on a neutral, '
            . 'warm-toned minimalist photo studio background with a shallow depth of field and '
            . 'a subtle, organic film grain.',
        );
    }
}
