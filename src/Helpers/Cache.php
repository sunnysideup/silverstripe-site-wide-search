<?php

namespace Sunnysideup\SiteWideSearch\Helpers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;

class Cache implements Flushable
{
    /**
     * Flush all MemberCacheFlusher services.
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
        $cacheName = $this->cleanCacheName($cacheName);
        $cache = $this->getCache();
        $array = $cache->has($cacheName) ? unserialize($cache->get($cacheName)) : [];

        return $cache->has($cacheName) ? unserialize($cache->get($cacheName)) : [];
    }

    public function setCacheValues($cacheName, array $array): self
    {
        $cacheName = $this->cleanCacheName($cacheName);
        $cache = $this->getCache();
        $cache->set($cacheName, serialize($array));

        return $this;
    }

    public function cleanCacheName(string $string)
    {
        return crc32($string);
    }
}
