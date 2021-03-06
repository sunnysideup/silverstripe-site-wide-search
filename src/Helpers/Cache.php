<?php

namespace Sunnysideup\SiteWideSearch\Helpers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;

class Cache implements Flushable
{
    /**
     * Flush all MemberCacheFlusher services
     */
    public static function flush()
    {
        $obj = Injector::inst()->get(Cache::class);
        $cache = $obj->getCache();
        if ($cache) {
            $cache->clear();
        }
    }

    public function getCache()
    {
        return Injector::inst()->get(CacheInterface::class . '.siteWideSearch');
    }

    public function getCacheValues($cacheName): array
    {
        $cache = $this->getCache();
        if ($cache->has($cacheName)) {
            $array = unserialize($cache->get($cacheName));
        } else {
            $array = [];
        }
        return $array;
    }

    public function setCacheValues($cacheName, array $array): self
    {
        $cache = $this->getCache();
        $cache->set($cacheName, serialize($array));

        return $this;
    }
}
