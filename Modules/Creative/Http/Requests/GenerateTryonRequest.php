<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateTryonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'product_id'    => ['required', 'integer', 'exists:products,id'],
            'mannequin_id'  => ['required', 'integer', 'exists:creative_mannequins,id'],
            'pose_ids'      => ['required', 'array', 'min:1'],
            'pose_ids.*'    => ['integer', 'exists:creative_poses,id'],
            // Ürünün kendi fotoğrafı yoksa (veya kullanıcı farklı bir görsel giydirmek
            // istiyorsa) burada ayrıca bir giysi görseli yüklenebilir.
            'garment_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            // Opsiyonel detay görselleri (arkadan/yandan/yaka-dikiş/kumaş vb.) — AI
            // giydirme sırasında EK referans olarak kullanılır, ayrı bir giysi olarak
            // değil aynı ürünün farklı açıları/detayları olarak değerlendirilir.
            'garment_details'         => ['nullable', 'array', 'max:6'],
            'garment_details.*.image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'garment_details.*.label' => ['nullable', 'string', 'max:60'],
            // Yerel CLIP sınıflandırıcının (classify-detail uç noktası) o görsel için
            // bulduğu TÜM aday etiketler — raporlama/detay sayfasında ayrı ayrı
            // gösterilir (bkz. TryonController::classifyDetail, CreativeTryonDetail.vue).
            'garment_details.*.detected_labels'          => ['nullable', 'array', 'max:10'],
            'garment_details.*.detected_labels.*.key'     => ['required_with:garment_details.*.detected_labels', 'string', 'max:40'],
            'garment_details.*.detected_labels.*.display' => ['required_with:garment_details.*.detected_labels', 'string', 'max:60'],
            'garment_details.*.detected_labels.*.score'    => ['required_with:garment_details.*.detected_labels', 'numeric', 'between:0,1'],
        ];
    }
}
