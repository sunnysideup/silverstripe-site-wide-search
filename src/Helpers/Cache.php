<?php

namespace Sunnysideup\SiteWideSearch\Helpers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\UnsavedRelationList;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;

class Cache implements Flushable
{

    /**
     * Flush all MemberCacheFlusher services
     */
    public static function flush()
    {
        $obj = Injector::inst()->get(Cache::class);
        $cache = $obj->getCache();
        if($cache) {
            $cache->clear();
        }
    }

    public function getCache()
    {
        return Injector::inst()->get(CacheInterface::class . '.siteWideSearch');
    }


    public function getCacheValues($cacheName) : array
    {
        $cache = $this->getCache();
        if ($cache->has($cacheName)) {
            $array = unserialize($cache->get($cacheName));
        } else {
            $array = [];
        }
        return $array;
    }

    public function setCacheValues($cacheName, array $array) : self
    {
        $cache = $this->getCache();
        $cache->set($cacheName, serialize($array));

        return $this;
    }

}
