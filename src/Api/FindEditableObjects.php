<?php

namespace Sunnysideup\SiteWideSearch\Api;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Security\Member;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\ORM\DataList;
use Sunnysideup\CmsEditLinkField\Api\CMSEditLinkAPI;


class FindEditableObjects {

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

    private static $max_search_per_class_name = 100;

    private static $valid_methods = [
        'CMSEditLink',
        'getCMSEditLink',
        'getLink',
        'Link',
    ];

    protected $originatingClassName = '';
    protected $originatingClassNameCount = 0;

    protected $excludedClasses = [];


    /**
     * returns an link to an object that can be edited in the CMS or viewed
     * @param  mixed dataObject
     * @return string
     */
    public function getLink($dataObject, array $excludedClasses) : string
    {
        $this->excludedClasses = $excludedClasses;
        $this->originatingClassName = $dataObject->ClassName;
        $this->originatingClassNameCount = 0;

        return $this->checkForValidMethods($dataObject);

    }

    protected function checkForValidMethods($dataObject) : string
    {
        if($this->originatingClassNameCount > $this->Config()->get('max_search_per_class_name')) {
            return '';
        }
        $this->originatingClassNameCount++;
        $validMethods = $this->Config()->get('valid_methods');

        // quick return
        if(isset($this->cache[$dataObject->ClassName]) && $this->cache[$dataObject->ClassName] !== true) {
            $method = $this->cache[$dataObject->ClassName];
            return $dataObject->$method();
        }
        if(! in_array($dataObject->ClassName, $this->excludedClasses)) {
            if(empty($this->cache[$dataObject->ClassName]) || $this->cache[$dataObject->ClassName] !== true) {
                foreach ($validMethods as $validMethod) {
                    if ($dataObject->hasMethod($validMethod)) {
                        $this->cache[$dataObject->ClassName] = $validMethod;

                        return $dataObject->$validMethod();
                    }
                }
            }
            if( class_exists(CMSEditLinkAPI::class)) {
                $link = CMSEditLinkAPI::find_edit_link_for_object($dataObject);
                if($link) {
                    return $link;
                }
            }
        }

        // there is no match for this one, but we can search relations ...
        $this->cache[$dataObject->ClassName] = true;
        foreach ($this->getRelations($dataObject) as $relationName)
        {
            $rels = $dataObject->$relationName();
            if($rels) {
                if($rels instanceof DataObject) {
                    return $this->checkForValidMethods($rels);
                } else if ($rels instanceof DataList) {
                    foreach($rels as $rel) {
                        return $this->checkForValidMethods($rel);
                    }
                } elseif( $rels instanceof UnsavedRelationList) {
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

    protected function getRelations($dataObject) : array
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
