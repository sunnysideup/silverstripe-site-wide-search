<?php

namespace Sunnysideup\SiteWideSearch\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\Security\Group;
use SilverStripe\Security\LoginAttempt;
use SilverStripe\Security\Member;
use SilverStripe\Security\MemberPassword;
use SilverStripe\View\ArrayData;

class SearchApi
{
    use Extensible;
    use Configurable;
    use Injectable;

    protected $debug = false;

    protected $isQuickSearch = false;

    protected $baseClass = DataObject::class;

    protected $excludedClasses = [];

    protected $excludedFields = [];

    protected $words = [];

    private static $limit_of_count_per_data_object = 100;

    private static $default_exclude_classes = [
        Member::class,
        Group::class,
        MemberPassword::class,
        LoginAttempt::class,
    ];

    private static $default_exclude_fields = [
        'ClassName',
    ];

    public function setDebug(bool $b): SearchApi
    {
        $this->debug = $b;

        return $this;
    }

    public function setIsQuickSearch(bool $b): SearchApi
    {
        $this->isQuickSearch = $b;

        return $this;
    }

    public function setBaseClass(string $class): SearchApi
    {
        $this->baseClass = $class;

        return $this;
    }

    public function setExcludedClasses(array $a): SearchApi
    {
        $this->excludedClasses = $a;

        return $this;
    }

    public function setExcludedFields(array $a): SearchApi
    {
        $this->excludedFields = $a;

        return $this;
    }

    public function setWords(array $a): SearchApi
    {
        $this->words = array_combine($a, $a);

        return $this;
    }

    public function addWord(string $s): SearchApi
    {
        $this->words[$s] = $s;

        return $this;
    }

    public function getLinks(string $word = ''): ArrayList
    {
        Environment::increaseTimeLimitTo();
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);
        if ($this->debug) {
            $start = microtime(true);
        }
        //always do first ...
        $matches = $this->getMatches($word);
        if ($this->debug) {
            $elaps = microtime(true) - $start;
            DB::alteration_message('seconds taken find results: ' . $elaps);
        }

        // helper
        $finder = Injector::inst()->get(FindEditableObjects::class);
        $finder->initCache();

        //return values
        $list = ArrayList::create();
        if ($this->debug) {
            DB::alteration_message('number of matched classes: ' . count($matches));
        }
        foreach ($matches as $className => $ids) {
            if (count($ids)) {
                if ($this->debug) {
                    $start = microtime(true);
                    DB::alteration_message('matches for : ' . $className . ': ' . count($ids));
                }
                $className = (string) $className;
                $items = $className::get()->filter(['ID' => $ids])->limit($this->Config()->get('limit_of_count_per_data_object'));
                foreach ($items as $item) {
                    $cmsEditLink = $finder->getCMSEditLink($item, $this->excludedClasses);
                    $link = $finder->getLink($item, $this->excludedClasses);
                    if ($item->canView()) {
                        $list->push(
                            ArrayData::create(
                                [
                                    'HasLink' => $link ? true : false,
                                    'HasCMSEditLink' => $cmsEditLink ? true : false,
                                    'Link' => $link,
                                    'CMSEditLink' => $cmsEditLink,
                                    'Object' => $item,
                                ]
                            )
                        );
                    }
                }
                if ($this->debug) {
                    $elaps = microtime(true) - $start;
                    DB::alteration_message('seconds taken to find objects in: ' . $className . ': ' . $elaps);
                }
            }
        }
        $finder->saveCache();

        return $list;
    }

    public function getMatches(string $word = ''): array
    {
        $this->workOutExclusions();
        $this->workOutWords($word);

        if ($this->debug) {
            DB::alteration_message('Words searched for ' . implode(', ', $this->words));
        }
        $array = [];

        $this->words = array_unique($this->words);
        foreach ($this->getAllDataObjects() as $className) {
            if ($this->debug) {
                DB::alteration_message(' .. Searching in ' . $className);
            }
            if (! in_array($className, $this->excludedClasses, true)) {
                $array[$className] = [];
                $singleton = Injector::inst()->get($className);
                $fields = $this->getAllValidFields($singleton);
                $filterAny = [];
                foreach ($fields as $field) {
                    if ($this->debug) {
                        DB::alteration_message(' .. .. Searching in ' . $className . '.' . $field);
                    }
                    if (! in_array($field, $this->excludedFields, true)) {
                        $filterAny[$field . ':PartialMatch'] = $this->words;
                    }
                }
                if (count($filterAny)) {
                    if ($this->debug) {
                        DB::alteration_message(' .. Filter: ' . implode(', ', array_keys($filterAny)));
                    }
                    if ($this->debug) {
                        $start = microtime(true);
                    }
                    $array[$className] = $className::get()
                        ->filterAny($filterAny)
                        ->limit($this->Config()->get('limit_of_count_per_data_object'))
                        ->column('ID');
                    if ($this->debug) {
                        $elaps = microtime(true) - $start;
                        DB::alteration_message('search for ' . $className . ' taken : ' . $elaps);
                    }
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
        if ($word) {
            $this->words[] = $word;
        }
        if (! count($this->words)) {
            user_error('No word has been provided');
        }
    }

    protected function getAllDataObjects(): array
    {
        if ($this->debug) {
            DB::alteration_message('Base Class: ' . $this->baseClass);
        }
        return ClassInfo::subclassesFor($this->baseClass, true);
    }

    protected function getAllValidFields($singleton): array
    {
        $array = [];
        $fields = Config::inst()->get(get_class($singleton), 'db');
        if (is_array($fields)) {
            if ($this->isQuickSearch) {
                $fields = $this->getIndexedField($singleton, $fields);
            }
            foreach (array_keys($fields) as $name) {
                $dbField = $singleton->dbObject($name);
                if ($dbField instanceof DBString) {
                    $array[$name] = $name;
                }
            }
        }

        return $array;
    }

    protected function getIndexedField($singleton, array $availableFields): array
    {
        $array = [];
        $indexes = Config::inst()->get(get_class($singleton), 'indexes');
        if (is_array($indexes)) {
            foreach ($indexes as $key => $field) {
                if (isset($availableFields[$key])) {
                    $array[$key] = $key;
                } elseif (is_array($field)) {
                    foreach ($field as $test) {
                        if (is_array($test)) {
                            if (isset($test['columns'])) {
                                $test = $test['columns'];
                            } else {
                                continue;
                            }
                        }
                        $testArray = explode(',', $test);
                        foreach ($testArray as $testInner) {
                            $testInner = trim($testInner);
                            if (isset($availableFields[$testInner])) {
                                $array[$testInner] = $testInner;
                            }
                        }
                    }
                }
            }
        }

        return $array;
    }
}
