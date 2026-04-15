<?php

namespace Crater\Http\Middleware;

use Closure;

class RedirectIfInstalled
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        // Anulamos la redirección al instalador: siempre dejamos continuar
        return $next($request);
    }
}
