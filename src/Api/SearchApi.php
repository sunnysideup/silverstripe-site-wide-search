<?php

namespace Sunnysideup\SiteWideSearch\Api;

use SilverStripe\Core\Extensible;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberPassword;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\ORM\ArrayList;
use SilverStripe\View\ArrayData;

class SearchApi {

    use Extensible;
    use Configurable;
    use Injectable;

    private static $limit_of_count_per_data_object = 100;

    protected $debug = false;

    public function setDebug(bool $b) : SearchApi
    {
        $this->debug = $b;

        return $this;
    }

    private static $default_exclude_classes = [
        Member::class,
        Group::class,
        MemberPassword::class,
    ];
    private static $default_exclude_fields = [
        'ClassName',
    ];

    protected $baseClass = DataObject::class;

    public function setBaseClase(string $class) : SearchApi
    {
        $this->baseClass = $class;

        return $this;
    }

    protected $excludedClasses = [];

    public function setExcludedClasses(array $a) : SearchApi
    {
        $this->excludedClasses = $a;

        return $this;
    }
    protected $excludedFields = [];

    public function setExcludedFields(array $a) : SearchApi
    {
        $this->excludedFields = $a;

        return $this;
    }
    protected $words = [];

    public function setWords(array $a) : SearchApi
    {
        $this->words = array_combine($a, $a);

        return $this;
    }

    public function addWord(string $s) : SearchApi
    {
        $this->words[$s] = $s;

        return $this;
    }

    public function getLinks(string $word = '') : ArrayList
    {
        //always do first ...
        $matches = $this->getMatches($word);

        // helper
        $finder = Injector::inst()->get(FindEditableObjects::class);

        //return values
        $list = ArrayList::create();

        foreach($matches as $className => $ids) {
            if (count($ids)) {
                $items = $className::get()->filter(['ID' => $ids])->limit($this->Config()->get('limit_of_count_per_data_object'));
                foreach ($items as $item) {
                    $link = $finder->getLink($item,$this->excludedClasses);
                    if ($item->canView()) {
                        $list->push(
                            ArrayData::create(
                                [
                                    'HasLink' => $link ? true : false,
                                    'Link' => $link,
                                    'Object' => $item,
                                ]
                            )
                        );
                    }
                }
            }
        }

        return $list;
    }

    public function getMatches(string $word = '') : array
    {
        $this->workOutExclusions();
        $this->workOutWords($word);

        if ($this->debug) { DB::alteration_message('Words searched for ' . implode(', ', $this->words)); }
        $array = [];

        $this->words = array_unique($this->words);
        foreach($this->getAllDataObjects() as $className)
        {
            if ($this->debug) { DB::alteration_message(' .. Searching in ' . $className); }
            if(!  in_array($className, $this->excludedClasses, true))  {
                $array[$className] = [];
                $singleton = Injector::inst()->get($className);
                $fields = $this->getAllValidFields($singleton);
                $filterAny = [];
                foreach($fields as $field) {
                    if ($this->debug) { DB::alteration_message( ' .. .. Searching in ' . $className . '.' . $field); }
                    if(!  in_array($field, $this->excludedFields, true))  {
                        $filterAny[$field.':PartialMatch'] = $this->words;
                    }
                }
                if ( count($filterAny)) {
                    if ($this->debug) { DB::alteration_message(' .. Filter: ' . implode(', ', array_keys($filterAny))); }
                    $array[$className] = $className::get()->filterAny($filterAny)->column('ID');
                }
            }
        }

        return $array;
    }


    protected function workOutExclusions()
    {
        $this->excludedClasses = array_unique(
            array_merge(
                $this->Config()->get('default_exclude_classes'),
                $this->excludedClasses
            )
        );
        $this->excludedFields = array_unique(
                array_merge(
                $this->Config()->get('default_exclude_fields'),
                $this->excludedFields
            )
        );
    }
    protected function workOutWords(string $word = '')
    {
        if($word) {
            $this->words[] = $word;
        }
        if(! count($this->words)) {
            user_error('No word has been provided');
        }
    }

    protected function getAllDataObjects() : array
    {
        if ($this->debug) { DB::alteration_message('Base Class: ' . $this->baseClass); }
        return ClassInfo::subclassesFor( $this->baseClass, true);
    }

    protected function getAllValidFields($singleton) : array
    {
        $fields = Config::inst()->get(get_class($singleton), 'db');
        $array= [];
        foreach($fields as $name => $type)
        {
            $dbField = $singleton->dbObject($name);
            if($dbField instanceof DBString) {
                $array[$name] = $name;
            }
        }
        return $array;
    }

}
