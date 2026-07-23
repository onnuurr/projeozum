<?php

namespace Tests\Fixtures\TenantIsolation;

class UnprotectedController
{
    public function index()
    {
        return Product::all();
    }

    public function exportAll()
    {
        return Category::query()->get();
    }
}
