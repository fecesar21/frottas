<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SomenteAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (auth()->user()?->perfil !== 'admin') {
            return response()->json(['error' => 'Acesso restrito a administradores.'], 403);
        }
        return $next($request);
    }
}
