<?php

namespace Sunnysideup\SiteWideSearch\Api;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
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
     * format is as follows:
     *     [
     *         ClassNameA => true, // tested and does not have any available methods
     *         ClassNameB => MethodName1, // tested found method MethodName1 that can be used.
     *         ClassNameC => MethodName2, // tested found method MethodName2 that can be used.
     *         ClassNameD => true, // tested and does not have any available methods
     *     ]
     * we use true rather than false to be able to use empty to work out if it has been tested before
     *
     * @var array
     */
    protected $cache = [];

    protected $originatingClassName = '';

    protected $originatingClassNameCount = 0;

    protected $excludedClasses = [];

    private static $max_search_per_class_name = 5;

    private static $valid_methods_edit = [
        'CMSEditLink',
        'getCMSEditLink',
    ];

    private static $valid_methods_view = [
        'getLink',
        'Link',
    ];

    /**
     * returns an link to an object that can be edited in the CMS
     * @param  mixed $dataObject
     * @return string
     */
    public function getCMSEditLink($dataObject, array $excludedClasses): string
    {
        $this->excludedClasses = $excludedClasses;
        $this->originatingClassName = $dataObject->ClassName;
        $this->originatingClassNameCount = 0;

        return $this->checkForValidMethods($dataObject, 'valid_methods_edit');
    }

    /**
     * returns an link to an object that can be viewed
     * @param  mixed $dataObject
     * @return string
     */
    public function getLink($dataObject, array $excludedClasses): string
    {
        $this->excludedClasses = $excludedClasses;
        $this->originatingClassName = $dataObject->ClassName;
        $this->originatingClassNameCount = 0;

        return $this->checkForValidMethods($dataObject, 'valid_methods_view');
    }

    protected function checkForValidMethods($dataObject, string $type = 'valid_methods'): string
    {
        $validMethods = $this->Config()->get($type);

        if ($this->originatingClassNameCount > $this->Config()->get('max_search_per_class_name')) {
            return '';
        }
        if (! isset($this->cache[$type])) {
            $this->cache[$type] = [];
        }
        $this->originatingClassNameCount++;

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
        foreach ($this->getRelations($dataObject) as $relationName) {
            $rels = $dataObject->{$relationName}();
            if ($rels) {
                if ($rels instanceof DataObject) {
                    return $this->checkForValidMethods($rels, $type);
                } elseif ($rels instanceof DataList) {
                    foreach ($rels as $rel) {
                        return $this->checkForValidMethods($rel, $type);
                    }
                } elseif ($rels instanceof UnsavedRelationList) {
                    //do nothing;
                } else {
                    print_r($rels);
                    user_error('Unexpected Relationship');
                    die('');
                }
            }
        }

        return '';
    }

    protected function getRelations($dataObject): array
    {
        return array_merge(
            array_keys(Config::inst()->get($dataObject->ClassName, 'belongs_to')),
            array_keys(Config::inst()->get($dataObject->ClassName, 'has_one')),
            array_keys(Config::inst()->get($dataObject->ClassName, 'has_many')),
            array_keys(Config::inst()->get($dataObject->ClassName, 'belongs_many_many')),
            array_keys(Config::inst()->get($dataObject->ClassName, 'many_many')),
        );
    }
}
