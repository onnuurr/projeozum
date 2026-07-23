<?php

namespace Tests\Fixtures\Controllers;

class CleanController
{
    public function index()
    {
        return Widget::query()->get();
    }
}
