<?php

namespace Silber\PageCache\Middleware;

use Closure;
use Silber\PageCache\Cache;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheResponse
{
    /**
     * The cache instance.
     *
     * @var \Silber\PageCache\Cache
     */
    protected $cache;

    /**
     * Constructor.
     *
     * @var \Silber\PageCache\Cache  $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Handle an incoming request.
     *
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldCache($request, $response)) {
            $this->cache->cache($request, $response);
        }

        return $response;
    }

    /**
     * Determines whether the given request/response pair should be cached.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    protected function shouldCache(Request $request, Response $response)
    {
        $_shouldCache = ($request->isMethod('GET') && $response->getStatusCode() == 200);
        if(!$_shouldCache) {
            return false;
        }

        /**
         * Does not cache when empty settings.
         */
        $allowed_route_names = config('page-cache.allowed_route_names');
        if(!is_array($allowed_route_names)||empty($allowed_route_names)) {
            return false;
        }

        $name = $request->route()->getName();
        $_allowCache = in_array($name,$allowed_route_names);

        return $_allowCache;
    }
}