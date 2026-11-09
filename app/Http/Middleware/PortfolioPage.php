<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PortfolioPage
{
    /**
     * Render public pages without Livewire's browser runtime.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        config(['livewire.inject_assets' => false]);

        return $next($request);
    }
}
