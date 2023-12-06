<?php

namespace Sunnysideup\SiteWideSearch\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\LoginAttempt;
use SilverStripe\Security\MemberPassword;
use SilverStripe\Security\RememberLoginHash;
use SilverStripe\Versioned\ChangeSet;
use SilverStripe\Versioned\Versioned;
use SilverStripe\Versioned\ChangeSetItem;
use SilverStripe\View\ArrayData;

use SilverStripe\SessionManager\Models\LoginSession;
use Sunnysideup\SiteWideSearch\Helpers\FindClassesAndFields;
use Sunnysideup\SiteWideSearch\Helpers\FindEditableObjects;

class SearchApi
{
    use Extensible;
    use Configurable;
    use Injectable;



    protected $debug = false;

    protected $isQuickSearch = false;

    protected $searchWholePhrase = false;

    protected $baseClass = DataObject::class;

    protected $quickSearchType = 'limited';

    protected $excludedClasses = [];

    protected $excludedClassesWithSubClassess = [];

    protected $includedClasses = [];

    protected $includedClassesWithSubClassess = [];

    protected $excludedFields = [];

    protected $includedFields = [];

    protected $includedClassFieldCombos = [];
    protected $defaultLists = [];

    protected $sortOverride = null;

    protected $words = [];

    protected $replace = '';


    private $objects = [];

    private static $limit_of_count_per_data_object = 999;

    private static $hours_back_for_recent = 48;

    private static $limit_per_class_for_recent = 5;

    private static $default_exclude_classes = [
        MemberPassword::class,
        LoginAttempt::class,
        ChangeSet::class,
        ChangeSetItem::class,
        RememberLoginHash::class,
        LoginSession::class,
    ];

    private static $default_exclude_fields = [
        'ClassName',
        'LastEdited',
        'Created',
        'ID',
        'CanViewType',
        'CanEditType',
    ];

    private static $default_include_classes = [];

    private static $default_include_fields = [];

    private static $default_include_class_field_combos = [];
    private static $default_lists = [];

    public function setDebug(bool $b): SearchApi
    {
        $this->debug = $b;

        return $this;
    }

    protected function getCache()
    {
        return FindClassesAndFields::inst($this->baseClass);
    }

    public function setQuickSearchType(string $nameOrType): SearchApi
    {
        if($nameOrType === 'all') {
            $this->isQuickSearch = false;
            $this->quickSearchType = '';
        } elseif($nameOrType === 'limited') {
            $this->isQuickSearch = true;
            $this->quickSearchType = '';
        } elseif(class_exists($nameOrType)) {
            $this->quickSearchType = $nameOrType;
            $object = Injector::inst()->get($nameOrType);
            $this->setIncludedClasses($object->getClassesToSearch());
            $this->setIncludedFields($object->getFieldsToSearch());
            $this->setIncludedClassFieldCombos($object->getIncludedClassFieldCombos());
            $this->setDefaultLists($object->getDefaultLists());
            $this->setSortOverride($object->getSortOverride());
        } else {
            user_error('QuickSearchType must be either "all" or "limited" or a defined quick search class. Provided was: ' . $nameOrType);
        }

        return $this;
    }

    public function setIsQuickSearch(bool $b): SearchApi
    {
        $this->isQuickSearch = $b;

        return $this;
    }

    public function setSearchWholePhrase(bool $b): SearchApi
    {
        $this->searchWholePhrase = $b;

        return $this;
    }

    public function setBaseClass(string $class): SearchApi
    {
        if(class_exists($class)) {
            $this->baseClass = $class;
        }

        return $this;
    }

    public function setExcludedClasses(array $a): SearchApi
    {
        $this->excludedClasses = $a;

        return $this;
    }

    public function setIncludedClasses(array $a): SearchApi
    {
        $this->includedClasses = $a;
        return $this;
    }

    public function setExcludedFields(array $a): SearchApi
    {
        $this->excludedFields = $a;

        return $this;
    }

    public function setIncludedFields(array $a): SearchApi
    {
        $this->includedFields = $a;

        return $this;
    }

    public function setIncludedClassFieldCombos(array $a): SearchApi
    {
        $this->includedClassFieldCombos = $a;

        return $this;
    }

    public function setDefaultLists(array $a): SearchApi
    {
        $this->defaultLists = $a;

        return $this;
    }

    public function setSortOverride(?array $a = null): SearchApi
    {
        $this->sortOverride = $a;

        return $this;
    }

    public function setWordsAsString(string $s): SearchApi
    {
        $s = $this->securityCheckInput($s);
        $this->words = explode(' ', $s);

        return $this;
    }


    // public function __construct()
    // {
    //     Environment::increaseTimeLimitTo(300);
    //     Environment::setMemoryLimitMax(-1);
    //     Environment::increaseMemoryLimitTo(-1);
    // }

    public function buildCache(?string $word = ''): SearchApi
    {
        $this->getLinksInner($word);

        return $this;

    }

    public function getLinks(?string $word = ''): ArrayList
    {
        return $this->getLinksInner($word);
    }

    protected function getLinksInner(?string $word = ''): ArrayList
    {
        $this->initCache();

        //always do first ...
        $matches = $this->getMatches($word);

        $list = $this->turnMatchesIntoList($matches);

        $this->saveCache();

        return $list;

    }

    public function doReplacement(string $word, string $replace): int
    {
        $this->initCache();
        $count = 0;
        if($word) {
            $this->buildCache($word);
            $replace = $this->securityCheckInput($replace);
            foreach ($this->objects as $item) {
                $className = $item->ClassName;
                if ($item->canEdit()) {
                    $fields = $this->getAllValidFields($className);
                    foreach ($fields as $field) {
                        if(!$this->includeFieldTest($className, $field)) {
                            continue;
                        }
                        $new = str_replace($word, $replace, $item->{$field});
                        if ($new !== $item->{$field}) {
                            ++$count;
                            $item->{$field} = $new;
                            $this->writeAndPublishIfAppropriate($item);

                            if ($this->debug) {
                                DB::alteration_message('<h2>Match:  ' . $item->ClassName . $item->ID . '</h2>' . $new . '<hr />');
                            }
                        }
                    }
                }
            }
        }

        return $count;
    }

    protected function saveCache(): self
    {
        $this->getCache()->saveCache();

        return $this;
    }


    protected function initCache(): self
    {
        $this->getCache()->initCache();

        return $this;
    }


    protected function writeAndPublishIfAppropriate($item)
    {
        if ($item->hasExtension(Versioned::class)) {
            $myStage = Versioned::get_stage();
            Versioned::set_stage(Versioned::DRAFT);
            // is it on live and is live the same as draft
            $canBePublished = $item->isPublished() && !$item->isModifiedOnDraft();
            $item->writeToStage(Versioned::DRAFT);
            if ($canBePublished) {
                $item->publishSingle();
            }
            Versioned::set_stage($myStage);
        } else {
            $item->write();
        }

    }

    protected function getMatches(?string $word = ''): array
    {
        $startInner = 0;
        $startOuter = 0;
        if ($this->debug) {
            $startOuter = microtime(true);
        }
        $this->workOutInclusionsAndExclusions();

        // important to do this first
        if($word) {
            $this->setWordsAsString($word);
        }
        $this->workOutWordsForSearching();
        if ($this->debug) {
            DB::alteration_message('Words searched for ' . print_r($this->words, 1));
        }

        $array = [];

        if (count($this->words)) {
            foreach ($this->getAllDataObjects() as $className) {

                if(!$this->includeClassTest($className)) {
                    continue;
                }

                $array[$className] = [];
                $fields = $this->getAllValidFields($className);
                $filterAny = [];
                foreach ($fields as $field) {
                    if(!$this->includeFieldTest($className, $field)) {
                        continue;
                    }
                    $filterAny[$field . ':PartialMatch'] = $this->words;
                    if ($this->debug) {
                        DB::alteration_message(' ... ... Searching in ' . $className . '.' . $field);
                    }
                }
                if ([] !== $filterAny) {
                    if ($this->debug) {
                        $startInner = microtime(true);
                        DB::alteration_message(' ... Filter: ' . implode(', ', array_keys($filterAny)));
                    }
                    $defaultList = $this->getDefaultList($className);
                    if(empty($defaultList)) {
                        $array[$className] = $className::get();
                    }
                    $array[$className] = $array[$className]->filter(['ClassName' => $className]);
                    $array[$className] = $array[$className]
                        ->filterAny($filterAny)
                        ->limit($this->Config()->get('limit_of_count_per_data_object'))
                        ->columnUnique('ID')
                    ;
                    if ($this->debug) {
                        $elaps = microtime(true) - $startInner;
                        DB::alteration_message('search for ' . $className . ' taken : ' . $elaps);
                    }
                }

                if ($this->debug) {
                    DB::alteration_message(' ... No fields in ' . $className);
                }
            }
        } else {
            $array = $this->getDefaultResults();
        }

        if ($this->debug) {
            $elaps = microtime(true) - $startOuter;
            DB::alteration_message('seconds taken find results: ' . $elaps);
        }

        return $array;
    }

    protected function getDefaultResults(): array
    {
        $back = $this->config()->get('hours_back_for_recent') ?: 24;
        $limit = $this->Config()->get('limit_per_class_for_recent') ?: 5;
        $threshold = strtotime('-' . $back . ' hours', DBDatetime::now()->getTimestamp());
        if (!$threshold) {
            $threshold = time() - 86400;
        }

        $array = [];
        $classNames = $this->getAllDataObjects();
        foreach ($classNames as $className) {
            if($this->includeClassTest($className)) {
                $array[$className] = $className::get()
                    ->filter('LastEdited:GreaterThan', date('Y-m-d H:i:s', $threshold))
                    ->sort(['LastEdited' => 'DESC'])
                    ->limit($limit)
                    ->column('ID')
                ;
            }
        }

        return $array;
    }

    /**
     * weeds out doubles
     *
     * @param array $matches
     * @param integer|null $limit
     * @return array
     */
    protected function turnArrayIntoObjects(array $matches, ?int $limit = 0): array
    {
        $start = 0;
        $fullListCheck = [];

        if (empty($this->objects)) {
            if (empty($limit)) {
                $limit = (int) $this->Config()->get('limit_of_count_per_data_object');
            }

            $this->objects = [];
            if ($this->debug) {
                DB::alteration_message('number of classes: ' . count($matches));
            }

            foreach ($matches as $className => $ids) {
                if ($this->debug) {
                    $start = microtime(true);
                    DB::alteration_message(' ... number of matches for : ' . $className . ': ' . count($ids));
                }

                if (count($ids)) {
                    $className = (string) $className;
                    $items = $className::get()
                        ->filter(['ID' => $ids, 'ClassName' => $className])
                        ->limit($limit)
                    ;
                    foreach ($items as $item) {
                        if(isset($fullListCheck[$item->ClassName][$item->ID])) {
                            continue;
                        }
                        if ($item->canView()) {
                            $fullListCheck[$item->ClassName][$item->ID] = true;
                            $this->objects[] = $item;
                        } else {
                            $fullListCheck[$item->ClassName][$item->ID] = false;
                        }
                    }
                }

                if ($this->debug) {
                    $elaps = microtime(true) - $start;
                    DB::alteration_message('seconds taken to find objects in: ' . $className . ': ' . $elaps);
                }
            }
        }

        return $this->objects;
    }

    protected function turnMatchesIntoList(array $matches): ArrayList
    {
        // helper
        //return values
        $list = ArrayList::create();
        $finder = Injector::inst()->get(FindEditableObjects::class);
        $finder->initCache(md5(serialize($this->excludedClassesWithSubClassess)))
            ->setExcludedClasses($this->excludedClassesWithSubClassess);

        $items = $this->turnArrayIntoObjects($matches);
        foreach ($items as $item) {
            $link = $finder->getLink($item, $this->excludedClasses);
            if($item->canView()) {
                $cmsEditLink = $item->canEdit() ? $finder->getCMSEditLink($item) : '';
                $list->push(
                    ArrayData::create(
                        [
                            'HasLink' => (bool) $link,
                            'HasCMSEditLink' => (bool) $cmsEditLink,
                            'Link' => $link,
                            'CMSEditLink' => $cmsEditLink,
                            'ID' => $item->ID,
                            'LastEdited' => $item->LastEdited,
                            'Title' => $item->getTitle(),
                            'SingularName' => $item->i18n_singular_name(),
                            'SiteWideSearchSortValue' => $this->getSortValue($item),
                            'CMSThumbnail' => DBField::create_field('HTMLText', $finder->getCMSThumbnail($item)),
                        ]
                    )
                );
            }
        }
        $finder->saveCache();

        if(!empty($this->sortOverride)) {
            return $list->sort($this->sortOverride);
        } else {
            return $list->sort(['SiteWideSearchSortValue' => 'ASC']);
        }
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
                $fieldValues[$field] = strtolower(strip_tags((string) $item->{$field}));
            }

            $fieldValuesAll = implode(' ', $fieldValues);
            $testWords = array_merge(
                [$fullWords],
                $this->words
            );
            $testWords = array_unique($testWords);
            foreach ($testWords as $wordKey => $word) {
                //match a exact field to full words / one word
                $fullWords = !(bool) $wordKey;
                if (false === $done) {
                    $count = 0;
                    foreach ($fieldValues as $fieldValue) {
                        ++$count;
                        if ($fieldValue === $word) {
                            $score += (int) $wordKey + $count;
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

    protected function workOutInclusionsAndExclusions()
    {
        $this->excludedClasses = array_unique(
            array_merge(
                $this->Config()->get('default_exclude_classes'),
                $this->excludedClasses
            )
        );
        $this->excludedClassesWithSubClassess = $this->includeSubClasses($this->excludedClasses);
        $this->includedClasses = array_unique(
            array_merge(
                $this->Config()->get('default_include_classes'),
                $this->includedClasses
            )
        );
        $this->includedClassesWithSubClassess = $this->includeSubClasses($this->includedClasses);
        $this->excludedFields = array_unique(
            array_merge(
                $this->Config()->get('default_exclude_fields'),
                $this->excludedFields
            )
        );

        $this->includedFields = array_unique(
            array_merge(
                $this->Config()->get('default_include_fields'),
                $this->includedFields
            )
        );
        $this->includedClassFieldCombos = array_unique(
            array_merge(
                $this->Config()->get('default_include_class_field_combos'),
                $this->includedClassFieldCombos
            )
        );
        $this->defaultLists = array_unique(
            array_merge(
                $this->Config()->get('default_lists'),
                $this->defaultLists
            )
        );
    }

    protected function workOutWordsForSearching()
    {
        if ($this->searchWholePhrase) {
            $this->words = [implode(' ', $this->words)];
        }

        if (!count($this->words)) {
            user_error('No word has been provided');
        }

        $this->words = array_map('trim', $this->words);
        $this->words = array_map('strtolower', $this->words);
        $this->words = array_unique($this->words);
        $this->words = array_filter($this->words);

    }

    protected function getAllDataObjects(): array
    {
        return $this->getCache()->getAllDataObjects();
    }

    protected function getAllValidFields(string $className): array
    {
        return $this->getCache()->getAllValidFields($className, $this->isQuickSearch, $this->includedFields, $this->includedClassFieldCombos);
    }


    protected function includeClassTest(string $className): bool
    {
        if(count($this->includedClassesWithSubClassess) && !in_array($className, $this->includedClassesWithSubClassess, true)) {
            if ($this->debug) {
                DB::alteration_message(' ... Skipping as not included ' . $className);
            }
            return false;
        }
        if(count($this->excludedClassesWithSubClassess) && in_array($className, $this->excludedClassesWithSubClassess, true)) {
            if ($this->debug) {
                DB::alteration_message(' ... Skipping as excluded ' . $className);
            }
            return false;
        }
        if ($this->debug) {
            DB::alteration_message(' ... including ' . $className);
        }

        return true;
    }

    protected function includeFieldTest(string $className, string $field): bool
    {
        if(isset($this->includedClassFieldCombos[$className][$field])) {
            return true;
        } elseif(count($this->includedFields)) {
            return in_array($field, $this->includedFields, true);
        } elseif (count($this->excludedFields)) {
            return in_array($field, $this->includedFields, true) ? false : true;
        } else {
            return false;
        }
    }

    protected function includeSubClasses(array $classes): array
    {
        $toAdd = [];
        foreach($classes as $class) {
            $toAdd = array_merge($toAdd, ClassInfo::subclassesFor($class, false));
        }
        return array_unique(array_merge($classes, $toAdd));
    }

    protected function securityCheckInput(string $word): string
    {
        $word = trim($word);
        return Convert::raw2sql($word);
    }

    protected function getDefaultList(string $className): array
    {
        return $this->defaultLists[$className] ?? [];
    }
}
