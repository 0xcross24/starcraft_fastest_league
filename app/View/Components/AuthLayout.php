<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AuthLayout extends Component
{
    /**
     * Page-level <head> values; see layouts/partials/head.blade.php.
     */
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public bool $noindex = false,
    ) {}

    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        return view('layouts.auth');
    }
}
