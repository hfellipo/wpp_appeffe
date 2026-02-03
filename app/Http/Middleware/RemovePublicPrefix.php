<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RemovePublicPrefix
{
    /**
     * Handle an incoming request.
     * Remove /public prefix from path if present (for servers that redirect to /public).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $requestUri = $request->server->get('REQUEST_URI', '');

        // Se o path começa com /public/, remover
        if (strpos($path, '/public/') === 0) {
            $newPath = substr($path, 7); // Remove '/public'

            $request->server->set('PATH_INFO', $newPath);
            $newRequestUri = preg_replace('#^/public#', '', $requestUri);
            $request->server->set('REQUEST_URI', $newRequestUri);

            \Log::info('Middleware RemovePublicPrefix: Path corrigido', [
                'path_original' => $path,
                'path_novo' => $newPath,
            ]);
        } elseif ($path === '/public') {
            $request->server->set('PATH_INFO', '/');
            $newRequestUri = preg_replace('#^/public/?$#', '/', $requestUri);
            $request->server->set('REQUEST_URI', $newRequestUri);

            \Log::info('Middleware RemovePublicPrefix: /public redirecionado para /');
        }
        
        return $next($request);
    }
}
