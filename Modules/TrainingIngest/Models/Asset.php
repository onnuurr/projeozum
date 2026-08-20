<?php

namespace Modules\TrainingIngest\Models;

class Asset extends TrainingModel
{
    public const ROLE_PRODUCT_INPUT = 'product_input';
    public const ROLE_MANNEQUIN_INPUT = 'mannequin_input';
    public const ROLE_GENERATED_OUTPUT = 'generated_output';

    protected $fillable = [
        'storage_bucket', 'storage_key', 'content_sha256', 'mime_type',
        'byte_size', 'width', 'height', 'role',
    ];

    protected $casts = [
        'byte_size'  => 'integer',
        'width'      => 'integer',
        'height'     => 'integer',
        'created_at' => 'immutable_datetime',
    ];
}
