<?php

namespace Sunnysideup\SiteWideSearch\Helpers;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\UnsavedRelationList;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;

class FindEditableObjects
{
    use Extensible;
    use Configurable;
    use Injectable;

    /**
     * @var string
     */
    private const CACHE_NAME = 'FindEditableObjectsCache';

    protected $relationTypesCovered = [];

    protected $excludedClasses = [];
    protected $includedClasses = [];

    /**
     * format is as follows:
     * ```php
     *      [
     *          'valid_methods_edit' => [
     *              ClassNameA => false, // tested and does not have any available methods
     *              ClassNameB => MethodName1, // tested found method MethodName1 that can be used.
     *              ClassNameC => MethodName2, // tested found method MethodName2 that can be used.
     *              ClassNameD => false, // tested and does not have any available methods
     *          ],
     *          'valid_methods_view' => [
     *              ClassNameA => false, // tested and does not have any available methods
     *              ClassNameB => MethodName1, // tested found method MethodName1 that can be used.
     *              ClassNameC => MethodName2, // tested found method MethodName2 that can be used.
     *              ClassNameD => false, // tested and does not have any available methods
     *          ],
     *          'valid_methods_view_links' => [
     *              [ClassNameX_IDY] => 'MyLinkView',
     *              [ClassNameX_IDZ] => 'MyLinkView',
     *          ],
     *          'valid_methods_edit_links' => [
     *              [ClassNameX_IDY] => 'MyLinkEdit',
     *              [ClassNameX_IDZ] => 'MyLinkEdit',
     *          ],
     *          'rels' =>
     *              'ClassNameY' => [
     *                  'MethodA' => RelationClassNameB,
     *                  'MethodC' => RelationClassNameD,
     *              ],
     *          ],
     *          'validMethods' => [
     *              'valid_methods_edit' => [
     *                  'A',
     *                  'B',
     *              ]
     *              'valid_methods_view' => [
     *                  'A',
     *                  'B',
     *              ]
     *          ]
     *     ]
     * ```
     * we use false to be able to use empty to work out if it has been tested before.
     *
     * @var array
     */
    protected $cache = [
        'valid_methods_edit',
        'valid_methods_view',
        'valid_methods_image',
        'valid_methods_view_links',
        'valid_methods_edit_links',
        'valid_methods_image_links',
        'rels',
        'validMethods' => [
            'valid_methods_edit' => [],
            'valid_methods_view' => [],
            'valid_methods_image' => [],
        ],
    ];

    private static $max_relation_depth = 3;

    private static $valid_methods_edit = [
        'CMSEditLink',
        'getCMSEditLink',
    ];

    private static $valid_methods_view = [
        'getLink',
        'Link',
    ];

    private static $valid_methods_image = [
        'StripThumbnail',
        'CMSThumbnail',
        'getCMSThumbnail',
    ];

    public function getFileCache()
    {
        return Injector::inst()->get(Cache::class);
    }

    public function initCache(string $additionalCacheName): self
    {
        $this->cache = $this->getFileCache()->getCacheValues(self::CACHE_NAME . '_' . $additionalCacheName);

        return $this;
    }

    public function setExcludedClasses(array $excludedClasses): self
    {
        $this->excludedClasses = $excludedClasses;

        return $this;
    }

    public function setIncludedClasses(array $includedClasses): self
    {
        $this->includedClasses = $includedClasses;

        return $this;
    }

    public function saveCache(): self
    {
        $this->getFileCache()->setCacheValues(self::CACHE_NAME, $this->cache);

        return $this;
    }

    /**
     * returns an link to an object that can be edited in the CMS.
     *
     * @param mixed $dataObject
     */
    public function getCMSEditLink($dataObject): string
    {
        return $this->getLinkInner($dataObject, 'valid_methods_edit');
    }

    /**
     * returns a link to an object that can be viewed.
     *
     * @param mixed $dataObject
     */
    public function getLink($dataObject): string
    {
        return $this->getLinkInner($dataObject, 'valid_methods_view');
    }

    /**
     * returns link to a thumbnail.
     *
     * @param mixed $dataObject
     */
    public function getCMSThumbnail($dataObject): string
    {
        return $this->getLinkInner($dataObject, 'valid_methods_image');
    }

    /**
     * returns an link to an object that can be viewed.
     *
     * @param mixed $dataObject
     */
    protected function getLinkInner($dataObject, string $type): string
    {
        $typeKey = $type . '_links';
        $key = $dataObject->ClassName . $dataObject->ID;
        $result = $this->cache[$typeKey][$key] ?? false;
        if (false === $result) {
            $this->relationTypesCovered = [];
            $result = $this->checkForValidMethods($dataObject, $type);
            $this->cache[$typeKey][$key] = $result;
        }

        return $result;
    }

    protected function checkForValidMethods($dataObject, string $type, int $relationDepth = 0): string
    {
        //too many iterations!
        if ($relationDepth > $this->Config()->get('max_relation_depth')) {
            return '';
        }

        $validMethods = $this->getValidMethods($type);

        $this->relationTypesCovered[$dataObject->ClassName] = false;

        // quick return
        if (isset($this->cache[$type][$dataObject->ClassName]) && false !== $this->cache[$type][$dataObject->ClassName]) {
            $validMethod = $this->cache[$type][$dataObject->ClassName];
            if ($dataObject->hasMethod($validMethod)) {
                return (string) $dataObject->{$validMethod}();
            }

            return (string) $dataObject->{$validMethod};
        }

        if ($this->classCanBeIncluded($dataObject->ClassName)) {
            if (empty($this->cache[$type][$dataObject->ClassName]) || false !== $this->cache[$type][$dataObject->ClassName]) {
                foreach ($validMethods as $validMethod) {
                    $outcome = null;
                    if ($dataObject->hasMethod($validMethod)) {
                        $outcome = $dataObject->{$validMethod}();
                    } elseif (!empty($dataObject->{$validMethod})) {
                        $outcome = $dataObject->{$validMethod};
                    }

                    if ($outcome) {
                        $this->cache[$type][$dataObject->ClassName] = $validMethod;

                        return (string) $outcome;
                    }
                }
            }

            if ('valid_methods_edit' === $type) {
                if (class_exists(CMSEditLinkAPI::class)) {
                    $link = CMSEditLinkAPI::find_edit_link_for_object($dataObject);
                    if ($link) {
                        return (string) $link;
                    }
                }
            }
        }

        // there is no match for this one, but we can search relations ...
        $this->cache[$type][$dataObject->ClassName] = true;
        ++$relationDepth;
        foreach ($this->getRelations($dataObject) as $relationName => $relType) {
            $outcome = null;
            //no support for link through relations yet!
            if (is_array($relType)) {
                continue;
            }

            if (!isset($this->relationTypesCovered[$relType])) {
                $rels = $dataObject->{$relationName}();
                if ($rels) {
                    if ($rels instanceof DataList) {
                        $rels = $rels->first();
                    } elseif ($rels && $rels instanceof DataObject) {
                        $outcome = $this->checkForValidMethods($rels, $type, $relationDepth);
                    } elseif ($rels instanceof UnsavedRelationList) {
                        //do nothing;
                    } else {
                        print_r($rels);
                        user_error('Unexpected Relationship');
                        die('');
                    }
                }
            }

            if ($outcome) {
                return $outcome;
            }
        }

        return '';
    }

    protected function getRelations($dataObject): array
    {
        if (!isset($this->cache['rels'][$dataObject->ClassName])) {
            $this->cache['rels'][$dataObject->ClassName] = array_merge(
                Config::inst()->get($dataObject->ClassName, 'belongs_to'),
                Config::inst()->get($dataObject->ClassName, 'has_one'),
                Config::inst()->get($dataObject->ClassName, 'has_many'),
                Config::inst()->get($dataObject->ClassName, 'belongs_many_many'),
                Config::inst()->get($dataObject->ClassName, 'many_many')
            );
            foreach ($this->cache['rels'][$dataObject->ClassName] as $key => $value) {
                if (!(is_string($value) && class_exists($value) && $this->classCanBeIncluded($value))) {
                    unset($this->cache['rels'][$dataObject->ClassName][$key]);
                }
            }
        }

        return $this->cache['rels'][$dataObject->ClassName];
    }

    protected function getValidMethods(string $type): array
    {
        if (!isset($this->cache['validMethods'][$type])) {
            $this->cache['validMethods'][$type] = $this->Config()->get($type);
        }

        return $this->cache['validMethods'][$type];
    }

    protected function classCanBeIncluded(string $dataObjectClassName): bool
    {
        if(count($this->excludedClasses) || count($this->includedClasses)) {
            if(!class_exists($dataObjectClassName)) {
                return false;
            }
            if (count($this->includedClasses)) {
                return in_array($dataObjectClassName, $this->includedClasses, true);
            }

            return !in_array($dataObjectClassName, $this->excludedClasses, true);
        }
        user_error('Please set either excludedClasses or includedClasses', E_USER_NOTICE);
        return false;
    }
}
