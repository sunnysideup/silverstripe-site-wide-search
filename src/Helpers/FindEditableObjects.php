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

    private const CACHE_NAME = 'FindEditableObjectsCache';

    protected $relationTypesCovered = [];

    protected $excludedClasses = [];

    /**
     * format is as follows:
     * ```php
     *      [
     *          'valid_methods_edit' => [
     *              ClassNameA => true, // tested and does not have any available methods
     *              ClassNameB => MethodName1, // tested found method MethodName1 that can be used.
     *              ClassNameC => MethodName2, // tested found method MethodName2 that can be used.
     *              ClassNameD => true, // tested and does not have any available methods
     *          ],
     *          'valid_methods_view' => [
     *              ClassNameA => true, // tested and does not have any available methods
     *              ClassNameB => MethodName1, // tested found method MethodName1 that can be used.
     *              ClassNameC => MethodName2, // tested found method MethodName2 that can be used.
     *              ClassNameD => true, // tested and does not have any available methods
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
     * we use true rather than false to be able to use empty to work out if it has been tested before
     *
     * @var array
     */
    protected $cache = [
        'valid_methods_edit',
        'valid_methods_view',
        'valid_methods_view_links',
        'valid_methods_edit_links',
        'rels',
        'validMethods' => [
            'valid_methods_edit' => [],
            'valid_methods_view' => [],
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

    public function getFileCache()
    {
        return Injector::inst()->get(Cache::class);
    }

    public function initCache(): self
    {
        $this->cache = $this->getFileCache()->getCacheValues(self::CACHE_NAME);

        return $this;
    }

    public function saveCache(): self
    {
        $this->getFileCache()->setCacheValues(self::CACHE_NAME, $this->cache);

        return $this;
    }

    /**
     * returns an link to an object that can be edited in the CMS
     * @param  mixed $dataObject
     * @return string
     */
    public function getCMSEditLink($dataObject, array $excludedClasses): string
    {
        return $this->getLinkInner($dataObject, $excludedClasses, 'valid_methods_edit');
    }

    /**
     * returns an link to an object that can be viewed
     * @param  mixed $dataObject
     * @return string
     */
    public function getLink($dataObject, array $excludedClasses): string
    {
        return $this->getLinkInner($dataObject, $excludedClasses, 'valid_methods_view');
    }

    /**
     * returns an link to an object that can be viewed
     * @param  mixed $dataObject
     * @return string
     */
    protected function getLinkInner($dataObject, array $excludedClasses, string $type): string
    {
        $typeKey = $type . '_links';
        $objectKey = $dataObject->ClassName . '_' . $dataObject->ID;
        $result = $this->cache[$typeKey][$objectKey] ?? false;
        if ($result === false) {
            $this->excludedClasses = $excludedClasses;
            $this->relationTypesCovered = [];
            $result = $this->checkForValidMethods($dataObject, $type);
            $this->cache[$typeKey][$objectKey] = $result;
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

        $this->relationTypesCovered[$dataObject->ClassName] = true;

        // quick return
        if (isset($this->cache[$type][$dataObject->ClassName]) && $this->cache[$type][$dataObject->ClassName] !== true) {
            $validMethod = $this->cache[$type][$dataObject->ClassName];
            if ($dataObject->hasMethod($validMethod)) {
                return (string) $dataObject->{$validMethod}();
            }
            return (string) $dataObject->{$validMethod};
        }
        if (! in_array($dataObject->ClassName, $this->excludedClasses, true)) {
            if (empty($this->cache[$type][$dataObject->ClassName]) || $this->cache[$type][$dataObject->ClassName] !== true) {
                foreach ($validMethods as $validMethod) {
                    $outcome = null;
                    if ($dataObject->hasMethod($validMethod)) {
                        $outcome = $dataObject->{$validMethod}();
                    } elseif (! empty($dataObject->{$validMethod})) {
                        $outcome = $dataObject->{$validMethod};
                    }
                    if ($outcome) {
                        $this->cache[$type][$dataObject->ClassName] = $validMethod;
                        return (string) $outcome;
                    }
                }
            }
            if ($type === 'valid_methods_edit') {
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
        $relationDepth++;
        foreach ($this->getRelations($dataObject) as $relationName => $relType) {
            $outcome = null;
            //no support for link through relations yet!
            if (is_array($relType)) {
                continue;
            }
            if (! isset($this->relationTypesCovered[$relType])) {
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
        if (! isset($this->cache['rels'][$dataObject->ClassName])) {
            $this->cache['rels'][$dataObject->ClassName] = array_merge(
                Config::inst()->get($dataObject->ClassName, 'belongs_to'),
                Config::inst()->get($dataObject->ClassName, 'has_one'),
                Config::inst()->get($dataObject->ClassName, 'has_many'),
                Config::inst()->get($dataObject->ClassName, 'belongs_many_many'),
                Config::inst()->get($dataObject->ClassName, 'many_many')
            );
            foreach($this->cache['rels'][$dataObject->ClassName] as $key => $value) {
                if (! in_array($value, $this->excludedClasses, true)) {
                    unset($this->cache['rels'][$dataObject->ClassName][$key]);
                }
            }
        }

        return $this->cache['rels'][$dataObject->ClassName];
    }

    protected function getValidMethods(string $type): array
    {
        if (! isset($this->cache['validMethods'][$type])) {
            $this->cache['validMethods'][$type] = $this->Config()->get($type);
        }

        return $this->cache['validMethods'][$type];
    }
}
