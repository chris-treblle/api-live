<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use \Closure;
use Illuminate\Support\Facades\Route;

class AllowMiddleware
{
    public function handle($request, Closure $next)
    {
        $currentRoute = Route::getCurrentRoute();
        $routes = Route::getRoutes();

        $methods = [];

        foreach ($routes as $route) {
            if ($route->uri() === $currentRoute->uri()) {
                $methods = array_merge($methods, $route->methods());
            }
        }

        $response = $next($request);
        $response->header('Allow', implode(', ', $methods));

        return $response;
    }
}
