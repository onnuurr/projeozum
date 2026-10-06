<?php

namespace Modules\Creative\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Reddedilen mokap sonucunu ret nedeninin aşamasından yeniden üret (ücretli AI). */
class RerunMockupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('creative.asset.manage') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
