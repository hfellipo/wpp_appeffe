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
        $scriptName = $request->server->get('SCRIPT_NAME', '');
        
        \Log::info('Middleware RemovePublicPrefix: Iniciando', [
            'path_info' => $path,
            'request_uri' => $requestUri,
            'script_name' => $scriptName,
            'full_url' => $request->fullUrl(),
        ]);
        
        // Se o path começa com /public/, remover
        if (strpos($path, '/public/') === 0) {
            $newPath = substr($path, 7); // Remove '/public'
            
            // Atualizar PATH_INFO
            $request->server->set('PATH_INFO', $newPath);
            
            // Atualizar REQUEST_URI removendo /public
            $newRequestUri = preg_replace('#^/public#', '', $requestUri);
            $request->server->set('REQUEST_URI', $newRequestUri);
            
            \Log::info('Middleware RemovePublicPrefix: Path corrigido', [
                'path_original' => $path,
                'path_novo' => $newPath,
                'request_uri_original' => $requestUri,
                'request_uri_novo' => $newRequestUri,
            ]);
        } elseif ($path === '/public') {
            // Se é exatamente /public, redirecionar para /
            $request->server->set('PATH_INFO', '/');
            $newRequestUri = preg_replace('#^/public/?$#', '/', $requestUri);
            $request->server->set('REQUEST_URI', $newRequestUri);
            
            \Log::info('Middleware RemovePublicPrefix: /public redirecionado para /', [
                'request_uri_original' => $requestUri,
                'request_uri_novo' => $newRequestUri,
            ]);
        } else {
            \Log::info('Middleware RemovePublicPrefix: Nenhuma correção necessária', [
                'path' => $path,
            ]);
        }
        
        return $next($request);
    }
}
