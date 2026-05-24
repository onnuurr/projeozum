<?php

namespace Modules\Product\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserAddress extends Model
{
    protected $table = 'user_addresses';

    protected $fillable = [
        'user_id',
        'label',
        'name',
        'phone',
        'street',
        'district',
        'city',
        'postal_code',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toCheckoutArray(): array
    {
        return [
            'id'         => $this->id,
            'label'      => $this->label,
            'name'       => $this->name,
            'phone'      => $this->phone,
            'street'     => $this->street,
            'district'   => $this->district,
            'city'       => $this->city,
            'postalCode' => $this->postal_code,
            'isDefault'  => (bool) $this->is_default,
        ];
    }
}
