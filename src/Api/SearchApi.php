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
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBString;
use SilverStripe\Security\LoginAttempt;
use SilverStripe\Security\MemberPassword;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\View\ArrayData;
use Sunnysideup\SiteWideSearch\Helpers\Cache;
use Sunnysideup\SiteWideSearch\Helpers\FindEditableObjects;

class SearchApi
{
    use Extensible;
    use Configurable;
    use Injectable;

    private const CACHE_NAME = 'SearchApi';

    protected $debug = false;

    protected $isQuickSearch = false;

    protected $baseClass = DataObject::class;

    protected $excludedClasses = [];

    protected $excludedFields = [];

    protected $words = [];

    /**
     * format is as follows:
     * ```php
     *      [
     *          'AllDataObjects' => [
     *              'BaseClassUsed' => [
     *                  0 => ClassNameA,
     *                  1 => ClassNameB,
     *              ],
     *          ],
     *          'AllValidFields' => [
     *              'ClassNameA' => [
     *                  'FieldA' => 'FieldA'
     *              ],
     *          ],
     *          'IndexedFields' => [
     *              'ClassNameA' => [
     *                  0 => ClassNameA,
     *                  1 => ClassNameB,
     *              ],
     *          ],
     *          'ListOfTextClasses' => [
     *              0 => ClassNameA,
     *              1 => ClassNameB,
     *          ],
     *          'ValidFieldTypes' => [
     *              'Varchar(30)' => true,
     *              'Boolean' => false,
     *          ],
     *     ],
     * ```
     * we use true rather than false to be able to use empty to work out if it has been tested before.
     *
     * @var array
     */
    protected $cache = [
    ];

    private static $limit_of_count_per_data_object = 999;

    private static $hours_back_for_recent = 48;

    private static $limit_per_class_for_recent = 5;

    private static $default_exclude_classes = [
        MemberPassword::class,
        LoginAttempt::class,
        ChangeSet::class,
        ChangeSetItem::class,
        RememberLoginHash::class,
    ];

    private static $default_exclude_fields = [
        'ClassName',
        'LastEdited',
        'Created',
        'ID',
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

    public function setWordsAsString(string $s): SearchApi
    {
        $this->words = explode(' ', $s);

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

    // public function __construct()
    // {
    //     Environment::increaseTimeLimitTo(300);
    //     Environment::setMemoryLimitMax(-1);
    //     Environment::increaseMemoryLimitTo(-1);
    // }

    public function getLinks(string $word = ''): ArrayList
    {
        $this->initCache();
        // if ($this->debug) {$start = microtime(true);}
        //always do first ...
        $matches = $this->getMatches($word);
        // if ($this->debug) {$elaps = microtime(true) - $start;DB::alteration_message('seconds taken find results: ' . $elaps);}

        $list = $this->turnMatchesIntoList($matches);
        $this->saveCache();

        return $list;
    }

    protected function getMatches(string $word = ''): array
    {
        $this->workOutExclusions();
        $this->workOutWords($word);

        // if ($this->debug) {DB::alteration_message('Words searched for ' . implode(', ', $this->words));}
        $array = [];

        if (count($this->words)) {
            foreach ($this->getAllDataObjects() as $className) {
                // if ($this->debug) {DB::alteration_message(' ... Searching in ' . $className);}
                if (! in_array($className, $this->excludedClasses, true)) {
                    $array[$className] = [];
                    $fields = $this->getAllValidFields($className);
                    $filterAny = [];
                    foreach ($fields as $field) {
                        if (! in_array($field, $this->excludedFields, true)) {
                            // if ($this->debug) {DB::alteration_message(' ... ... Searching in ' . $className . '.' . $field);}
                            $filterAny[$field . ':PartialMatch'] = $this->words;
                        }
                    }
                    if (count($filterAny)) {
                        // if ($this->debug) {$start = microtime(true); DB::alteration_message(' ... Filter: ' . implode(', ', array_keys($filterAny)));}
                        $array[$className] = $className::get()
                            ->filterAny($filterAny)
                            ->limit($this->Config()->get('limit_of_count_per_data_object'))
                            ->column('ID')
                        ;
                        // if ($this->debug) {$elaps = microtime(true) - $start;DB::alteration_message('search for ' . $className . ' taken : ' . $elaps);}
                    }
                    // if ($this->debug) {DB::alteration_message(' ... No fields in ' . $className);}
                }
                // if ($this->debug) {DB::alteration_message(' ... Skipping ' . $className);}
            }
        } else {
            $array = $this->getDefaultList();
        }

        return $array;
    }

    protected function getDefaultList(): array
    {
        $back = $this->config()->get('hours_back_for_recent') ?? 24;
        $limit = $this->Config()->get('limit_per_class_for_recent') ?? 5;
        $threshold = strtotime('-' . $back . ' hours', DBDatetime::now()->getTimestamp());
        if (! $threshold) {
            $threshold = time() - 86400;
        }
        $array = [];
        $classNames = $this->getAllDataObjects();
        foreach ($classNames as $className) {
            if (! in_array($className, $this->excludedClasses, true)) {
                $array[$className] = $className::get()
                    ->filter('LastEdited:GreaterThan', date('Y-m-d H:i:s', $threshold))
                    ->sort('LastEdited', 'DESC')
                    ->limit($limit)
                    ->column('ID')
                ;
            }
        }

        return $array;
    }

    protected function turnMatchesIntoList(array $matches): ArrayList
    {
        // helper
        $finder = Injector::inst()->get(FindEditableObjects::class);
        $finder->initCache();

        //return values
        $list = ArrayList::create();
        // if ($this->debug) {DB::alteration_message('number of classes: ' . count($matches));}
        foreach ($matches as $className => $ids) {
            if (count($ids)) {
                // if ($this->debug) {$start = microtime(true);DB::alteration_message(' ... number of matches for : ' . $className . ': ' . count($ids));}
                $className = (string) $className;
                $items = $className::get()
                    ->filter(['ID' => $ids, 'ClassName' => $className])
                    ->limit($this->Config()->get('limit_of_count_per_data_object'))
                ;
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
                // if ($this->debug) {$elaps = microtime(true) - $start;DB::alteration_message('seconds taken to find objects in: ' . $className . ': ' . $elaps);}
            }
        }
        $finder->saveCache();

        return $list->sort('SiteWideSearchSortValue', 'ASC');
    }

    protected function getSortValue($item)
    {
        $className = $item->ClassName;
        $fields = $this->getAllValidFields($className);
        $fullWords = implode(' ', $this->words);

        $done = false;
        $score = 0;
        if ($fullWords) {
            $fieldValues = [];
            $fieldValuesAll = '';
            foreach ($fields as $field) {
                $fieldValues[$field] = strtolower(strip_tags($item->{$field}));
            }
            $fieldValuesAll = implode(' ', $fieldValues);
            $testWords = array_merge(
                [$fullWords],
                $this->words
            );
            $testWords = array_unique($testWords);
            foreach ($testWords as $wordKey => $word) {
                //match a exact field to full words / one word
                $fullWords = $wordKey ? false : true;
                if (false === $done) {
                    $count = 0;
                    foreach ($fieldValues as $fieldValue) {
                        ++$count;
                        if ($fieldValue === $word) {
                            $score += intval($wordKey) + $count;
                            $done = true;

                            break;
                        }
                    }
                }

                // the full string / any of the words are present?
                if (false === $done) {
                    $pos = strpos($fieldValuesAll, $word);
                    if (false !== $pos) {
                        $score += (($pos + 1) / strlen($word)) * 1000;
                        $done = true;
                    }
                }

                // all individual words are present
                if (false === $done) {
                    if ($fullWords) {
                        $score += 1000;
                        $allMatch = true;
                        foreach ($this->words as $tmpWord) {
                            $pos = strpos($fieldValuesAll, $tmpWord);
                            if (false === $pos) {
                                $allMatch = false;

                                break;
                            }
                        }
                        if ($allMatch) {
                            $done = true;
                        }
                    }
                }
            }
        }

        //the older the item, the higher the scoare
        //1104622247 = 1 jan 2005
        return $score + (1 / (strtotime($item->LastEdited) - 1104537600));
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

    protected function workOutWords(string $word = ''): array
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
        // if ($this->debug) {DB::alteration_message('Base Class: ' . $this->baseClass);}
        if (! isset($this->cache['AllDataObjects'][$this->baseClass])) {
            $this->cache['AllDataObjects'][$this->baseClass] = array_values(
                ClassInfo::subclassesFor($this->baseClass, false)
            );
            $this->cache['AllDataObjects'][$this->baseClass] = array_unique($this->cache['AllDataObjects'][$this->baseClass]);
        }

        return $this->cache['AllDataObjects'][$this->baseClass];
    }

    protected function getAllValidFields(string $className): array
    {
        if (! isset($this->cache['AllValidFields'][$className])) {
            $array = [];
            $fullList = Config::inst()->get($className, 'db');
            if (is_array($fullList)) {
                if ($this->isQuickSearch) {
                    $fullList = $this->getIndexedFields(
                        $className,
                        $fullList
                    );
                }
                foreach ($fullList as $name => $type) {
                    if ($this->isValidFieldType($className, $name, $type)) {
                        $array[] = $name;
                    }
                }
            }
            $this->cache['AllValidFields'][$className] = $array;
        }

        return $this->cache['AllValidFields'][$className];
    }

    protected function getIndexedFields(string $className, array $dbFields): array
    {
        if (! isset($this->cache['IndexedFields'][$className])) {
            $this->cache['IndexedFields'][$className] = [];
            $indexes = Config::inst()->get($className, 'indexes');
            if (is_array($indexes)) {
                foreach ($indexes as $key => $field) {
                    if (isset($dbFields[$key])) {
                        $this->cache['IndexedFields'][$className][$key] = $dbFields[$key];
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
                                if (isset($dbFields[$testInner])) {
                                    $this->cache['IndexedFields'][$className][$testInner] = $dbFields[$key];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->cache['IndexedFields'][$className];
    }

    protected function isValidFieldType(string $className, string $fieldName, string $type): bool
    {
        if (! isset($this->cache['ValidFieldTypes'][$type])) {
            $this->cache['ValidFieldTypes'][$type] = false;
            $singleton = Injector::inst()->get($className);
            $field = $singleton->dbObject($fieldName);
            if ($field instanceof DBString) {
                $this->cache['ValidFieldTypes'][$type] = true;
            }
        }

        return $this->cache['ValidFieldTypes'][$type];
    }
}
