<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->header('Accept-Language');
        
        if (!in_array($locale, ['az', 'en', 'ru'])) {
            $locale = 'az'; 
        }
        
        app()->setLocale($locale);
        
        return $next($request);
    }
} 