<?php

return [
    /**
     * Cache File Save Path
     */
    'save_path' => storage_path('app/page-cache'),
    /*
     * route name list of response cache
     * ex ['top.index','articles.index','articles.show']
     */
    'allow_cache_route' => [],
    /**
     * Forget Cache From Url Access get parameter
     */
    'forget_cache_query_key'=> ['pcfgcc'],
];