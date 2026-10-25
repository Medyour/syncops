<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ArchitectureController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(): View
    {
        return view('architecture');
    }
}
