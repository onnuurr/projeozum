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
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'mannequin_id' => ['required', 'integer', 'exists:creative_mannequins,id'],
            'pose_ids'     => ['required', 'array', 'min:1'],
            'pose_ids.*'   => ['integer', 'exists:creative_poses,id'],
        ];
    }
}
