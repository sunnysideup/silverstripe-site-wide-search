<?php

namespace Sunnysideup\SiteWideSearch\Api;

use Sunnysideup\SiteWideSearch\Helpers\FindEditableObjects;
use Sunnysideup\SiteWideSearch\Helpers\Cache;

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

    private static $limit_of_count_per_data_object = 999;

    private static $default_exclude_classes = [
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

    /**
     * format is as follows:
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
     *          'validMethod' => [
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
     * we use true rather than false to be able to use empty to work out if it has been tested before
     *
     * @var array
     */
    protected $cache = [];

    public function getFileCache()
    {
        return Injector::inst()->get(Cache::class);
    }

    public function initCache() : self
    {
        $this->cache = $this->getFileCache()->getCacheValues(self::class);

        return $this;
    }

    public function saveCache() : self
    {
        $this->getFileCache()->setCacheValues(self::class, $this->cache);

        return $this;
    }


    public function __construct()
    {
        Environment::increaseTimeLimitTo(300);
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);
    }

    public function getLinks(string $word = ''): ArrayList
    {
        $this->initCache();
        if ($this->debug) {
            $start = microtime(true);
        }
        //always do first ...
        $matches = $this->getMatches($word);
        if ($this->debug) {
            $elaps = microtime(true) - $start;
            DB::alteration_message('seconds taken find results: ' . $elaps);
        }

        $list = $this->turnMatchesIntoList($matches);
        $this->saveCache();

        return $list;
    }

    protected function turnMatchesIntoList(array $matches) : ArrayList
    {
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
                $items = $className::get()
                    ->filter(['ID' => $ids])
                    ->limit($this->Config()
                    ->get('limit_of_count_per_data_object'));
                foreach ($items as $item) {
                    if ($item->canView()) {
                        $link = $finder->getLink($item, $this->excludedClasses);
                        $cmsEditLink = '';
                        if ($item->canEdit()) {
                            $cmsEditLink = $finder->getCMSEditLink($item, $this->excludedClasses);
                        }
                        $list->push(
                            ArrayData::create(
                                [
                                    'HasLink' => $link ? true : false,
                                    'HasCMSEditLink' => $cmsEditLink ? true : false,
                                    'Link' => $link,
                                    'CMSEditLink' => $cmsEditLink,
                                    'Object' => $item,
                                    'SiteWideSearchSortValue' => $this->getSortValue($item),
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

        $list->sort('SiteWideSearchSortValue', 'ASC');

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

        foreach ($this->getAllDataObjects() as $className) {
            if ($this->debug) {
                DB::alteration_message(' .. Searching in ' . $className);
            }
            if (! in_array($className, $this->excludedClasses, true)) {
                $array[$className] = [];
                $fields = $this->getAllValidFields($className);
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

    protected function getSortValue($item)
    {
        $className = $item->ClassName;
        $fields = $this->getAllValidFields();
        $fullWords = implode(' ', $this->words);

        $done = false;
        $score = 0;
        if($fullWords) {
            $fieldValues = [];
            $fieldValuesAll = '';
            $testWords = array_merge(
                [$fullWords],
                $this->words
            );
            foreach($testWords as $wordKey => $word) {
                $fullWords = true;
                if($wordKey) {
                    $fullWords = false;
                }
                if($done === false) {
                    foreach($fields as $field) {
                        $score = $score++;
                        $fieldValues[$field] = strtolower(strip_tags($item->{$field}));
                        if($fieldValues[$field] === $word) {
                            $done = true;
                            break;
                        }
                    }
                }
                if(! $fieldValuesAll) {
                    $fieldValuesAll = implode(' ', $fieldValues);
                }
                if($done === false) {
                    $score += 1000;
                    $test = strpos($fieldValuesAll, $word);
                    if($test !== false) {
                        $score += ($test + 1) / strlen($word);
                        $done = true;
                    }
                }

                //add if we are moving to individual words
                if($fullWords) {
                    $score += 1000;
                }
            }
        } else {
            $done = true;
        }

        //the newer the item, the more likely
        return $score + (1 / strototime($item->LastEdited));
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

    protected function workOutWords(string $word = '') : array
    {
        if ($word) {
            $this->words[] = $word;
        }
        if (! count($this->words)) {
            user_error('No word has been provided');
        }
        $this->words = array_unique($this->words);
        $this->words = array_filter($this->words);
        $this->words = array_map('strtolower', $this->words);

        return $this->words;
    }

    protected function getAllDataObjects(): array
    {
        if ($this->debug) {
            DB::alteration_message('Base Class: ' . $this->baseClass);
        }
        if(! isset($this->cache['AllDataObjects'][$this->baseClass])) {
            if(! isset($this->cache['AllDataObjects'])) {
                $this->cache['AllDataObjects'] = [];
            }
            $this->cache['AllDataObjects'][$this->baseClass] = ClassInfo::subclassesFor($this->baseClass, true);
        }

        return $this->cache['AllDataObjects'][$this->baseClass];
    }

    protected function getAllValidFields(string $className): array
    {
        $listofTextFieldClasses = $this->getListOfTextClasses();
        if(! isset($this->cache['AllValidFields'][$className])) {
            if(! isset($this->cache['AllValidFields'])) {
                $this->cache['AllValidFields'] = [];
            }
            $this->cache['AllValidFields'][$className] = Config::inst()->get($className, 'db');
            if (is_array($this->cache['AllValidFields'][$className])) {
                if ($this->isQuickSearch) {
                    $this->cache['AllValidFields'][$className] = $this->getIndexedFields(
                        $className,
                        $this->cache['AllValidFields'][$className]
                    );
                }
                foreach (array_keys($this->cache['AllValidFields'][$className]) as $name) {
                    if (in_array($name, $listofTextFieldClasses , 1)) {
                        $this->cache['AllValidFields'][$className][$name] = $name;
                    } else {
                        unset($this->cache['AllValidFields'][$className][$name]);
                    }
                }
            }
        }
        return $this->cache['AllValidFields'][$className];
    }

    protected function getIndexedFields(string $className, array $availableFields): array
    {
        if(! isset($this->cache['IndexedFields'][$className])) {
            if(! isset($this->cache['IndexedFields'])) {
                $this->cache['IndexedFields'] = [];
            }
            $this->cache['IndexedFields'][$className] = [];
            $indexes = Config::inst()->get($className, 'indexes');
            if (is_array($indexes)) {
                foreach ($indexes as $key => $field) {
                    if (isset($availableFields[$key])) {
                        $this->cache['IndexedFields'][$className][$key] = $key;
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
                                    $this->cache['IndexedFields'][$className][$testInner] = $testInner;
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->cache['IndexedFields'][$className];
    }

    protected function defaultList() : array
    {
        $threshold = strtotime('-3 days', DBDatetime::now()->getTimestamp());
        $array = [];
        foreach($this->getAllDataObjects() as $className) {
            $array[$className] = $className::get()
                ->filter('LastEdited:GreaterThan', date("Y-m-d H:i:s", $threshold))
                ->sort('LastEdited', 'DESC')
                ->limit()
                ->column('ID');

        }
        return $array;
    }

    protected function getListOfTextClasses() : array
    {
        if(! isset($this->cache['ListOfTextClasses'])) {
            $this->cache['ListOfTextClasses'] = ClassInfo::subclassesFor(DBString::class);
        }
        return $this->cache['ListOfTextClasses'];
    }

}
