<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class TrackLastFilamentModule
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        $route = $request->route();
        $routeName = (string) ($route?->getName() ?? '');
        $path = trim($request->path(), '/');

        if (! $user || ! $request->isMethod('GET')) {
            return $response;
        }

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        if (! str_starts_with($routeName, 'filament.agarcorp.')) {
            return $response;
        }

        if (str_contains($routeName, '.auth.')) {
            return $response;
        }

        if ($path === 'agarcorp' || $path === 'agarcorp/') {
            return $response;
        }

        Cache::forever('agarcorp:last-module:' . $user->getAuthIdentifier(), [
            'title' => $this->resolveTitle($routeName, $path),
            'url' => url('/' . $path),
            'path' => $path,
            'visited_at' => now()->toIso8601String(),
        ]);

        return $response;
    }

    protected function resolveTitle(string $routeName, string $path): string
    {
        $slug = null;

        if (preg_match('/\.resources\.([^.]+)\./', $routeName, $matches) === 1) {
            $slug = $matches[1];
        }

        if (! $slug && preg_match('/\.pages\.([^.]+)$/', $routeName, $matches) === 1) {
            $slug = $matches[1];
        }

        if (! $slug) {
            $segments = array_values(array_filter(explode('/', $path)));
            $slug = end($segments) ?: 'modulo';
        }

        return Str::of($slug)
            ->replace(['-', '_'], ' ')
            ->headline()
            ->toString();
    }
}
