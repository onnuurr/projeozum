<?php

namespace Tests\Fixtures\Controllers;

use Illuminate\Support\Facades\DB;

class BypassingController
{
    public function index()
    {
        return DB::table('widgets')->get();
    }
}
