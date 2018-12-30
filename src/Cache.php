<?php

namespace Silber\PageCache;

use Exception;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Cache
{
    /**
     * The filesystem instance.
     *
     * @var \Illuminate\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The container instance.
     *
     * @var \Illuminate\Contracts\Container\Container|null
     */
    protected $container = null;

    /**
     * The directory in which to store the cached pages.
     *
     * @var string|null
     */
    protected $cachePath = null;

    /**
     * Constructor.
     *
     * @var \Illuminate\Filesystem\Filesystem  $files
     */
    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    /**
     * Sets the container instance.
     *
     * @param  \Illuminate\Contracts\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Sets the directory in which to store the cached pages.
     *
     * @param  string  $path
     * @return void
     */
    public function setCachePath($path)
    {
        $this->cachePath = rtrim($path, '\/');
    }

    /**
     * Gets the path to the cache directory.
     *
     * @param  string  ...$paths
     * @return string
     *
     * @throws \Exception
     */
    public function getCachePath()
    {
        $base = $this->cachePath ? $this->cachePath : $this->getDefaultCachePath();

        if (is_null($base)) {
            throw new Exception('Cache path not set.');
        }

        return $this->join(array_merge([$base], func_get_args()));
    }

    /**
     * Join the given paths together by the system's separator.
     *
     * @param  string[] $paths
     * @return string
     */
    protected function join(array $paths)
    {
        $trimmed = array_map(function ($path) {
            return trim($path, '/');
        }, $paths);

        return $this->matchRelativity(
            $paths[0], implode('/', array_filter($trimmed))
        );
    }

    /**
     * Makes the target path absolute if the source path is also absolute.
     *
     * @param  string  $source
     * @param  string  $target
     * @return string
     */
    protected function matchRelativity($source, $target)
    {
        return $source[0] == '/' ? '/'.$target : $target;
    }

    /**
     * Caches the given response if we determine that it should be cache.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return $this
     */
    public function cacheIfNeeded(Request $request, Response $response)
    {
        if ($this->shouldCache($request, $response)) {
            $this->cache($request, $response);
        }

        return $this;
    }

    /**
     * Determines whether the given request/response pair should be cached.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return bool
     */
    public function shouldCache(Request $request, Response $response)
    {
        return $request->isMethod('GET') && $response->getStatusCode() == 200;
    }

    /**
     * Cache the response to a file.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function cache(Request $request, Response $response)
    {
        list($path, $file) = $this->getDirectoryAndFileNames($request);

        $this->files->makeDirectory($path, 0775, true, true);

        $this->files->put(
            $this->join([$path, $file]),
            $response->getContent(),
            true
        );
    }

    /**
     * Check the response cache file exist.
     *
     * @param  \Symfony\Component\HttpFoundation\Request  $request
     * @return bool
     */
    public function hasCache(Request $request) {
        $full_path = $this->getCacheFilePath($request);
        if(file_exists($full_path)){
            return true;
        }
        return false;
    }

    /**
     * @param Request $request
     * @return bool|string
     */
    public function getCacheContent(Request $request) {
        $content = '';
        $file = $this->getCacheFilePath($request);
        if(file_exists($file)) {
            $content = file_get_contents($file);
        }
        return $content;
    }

    /**
     * Get file full path
     * @param Request $request
     * @return string
     */
    protected function getCacheFilePath(Request $request) {
        list($path, $file) = $this->getDirectoryAndFileNames($request);
        $full_path = $path."/".$file;
        return $full_path;
    }

    /**
     * Remove the cached file for the given slug.
     *
     * @param  string  $slug
     * @return bool
     */
    public function forget($slug)
    {
        return $this->files->delete($this->getCachePath($slug.'.html'));
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function forgetByRequest(Request $request) {
        $slug = $this->getSlug($request);
        $this->forget($slug);
    }

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function getSlug(Request $request) {
        list($path, $file) = $this->getDirectoryAndFileNames($request);
        $base = $this->getCachePath();

        $_file = str_replace('.html','',$file);
        $_path = str_replace($base,'',$path);

        $slug = str_replace('//','/',"{$_path}/{$_file}");
        return $slug;
    }

    /**
     * Fully clear the cache directory.
     *
     * @return bool
     */
    public function clear()
    {
        return $this->files->deleteDirectory($this->getCachePath(), true);
    }

    /**
     * Get the names of the directory and file.
     *
     * @param  Request $request
     * @return array
     */
    protected function getDirectoryAndFileNames($request)
    {
        $segments = explode('/', ltrim($request->getPathInfo(), '/'));
        $last_path = array_pop($segments);

        $queryString = $request->getQueryString();
        parse_str($queryString,$query);
        ksort($query,SORT_REGULAR|SORT_DESC);

        $forget_key = config('page-cache.forget_cache_query_key');

        if(!empty($query)&&array_key_exists($forget_key,$query)) {
            unset($query[$forget_key]);
        }

        $queryResortString = http_build_query($query);
        if(strlen($queryResortString)>0) {
            $fileHash = md5($queryResortString);
            $last_path = $last_path."--".$fileHash;
        }
        $file = $this->aliasFilename($last_path).'.html';

        return [$this->getCachePath(implode('/', $segments)), $file];
    }

    /**
     * Alias the filename if necessary.
     *
     * @param  string  $filename
     * @return string
     */
    protected function aliasFilename($filename)
    {
        return $filename ?: 'pc__index__pc';
    }

    /**
     * Get the default path to the cache directory.
     *
     * @return string|null
     */
    protected function getDefaultCachePath()
    {
        return config('page-cache.save_path');
    }
}
