<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;

class BarHistory extends Model
{
    use Prunable;
    use SoftDeletes;
}
