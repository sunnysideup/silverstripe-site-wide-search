<?php

namespace Sunnysideup\SiteWideSearch\Helpers;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBString;

class FindClassesAndFields
{
    use Injectable;

    /**
     * @var string
     */
    private const CACHE_NAME = 'SiteWideSearchApi';

    private const BASIC_FIELDS = [
        'ID' => 'Int',
        'Created' => 'DBDatetime',
        'LastEdited' => 'DBDatetime',
        'ClassName' => 'Varchar',
    ];

    protected $baseClass = DataObject::class;

    protected $debug = false;

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
     *          'AllIndexedFields' => [
     *              'ClassNameA' => [
     *                  0 => ClassNameA,
     *                  1 => ClassNameB,
     *              ],
     *          ],
     *          'AllValidFieldTypes' => [
     *              'Varchar(30)' => true,
     *              'Boolean' => false,
     *          ],
     *     ],
     * ```
     * we use true rather than false to be able to use empty to work out if it has been tested before.
     *
     * @var array
     */
    protected $cache = [];

    public function saveCache(): self
    {
        $this->getFileCache()->setCacheValues(self::CACHE_NAME . '_' . $this->baseClass, $this->cache);

        return $this;
    }

    protected function getFileCache()
    {
        return Injector::inst()->get(Cache::class);
    }

    public function initCache(): self
    {
        $this->cache = $this->getFileCache()->getCacheValues(self::CACHE_NAME . '_' . $this->baseClass);

        return $this;
    }

    protected static $singleton;

    public static function inst(string $baseClass)
    {
        if (self::$singleton === null) {
            self::$singleton = Injector::inst()->get(static::class);
        }
        self::$singleton->setBaseClass($baseClass);
        return self::$singleton;
    }

    public function setBaseClass(string $baseClass): self
    {
        $this->baseClass = $baseClass;

        return $this;
    }

    public function getAllDataObjects(): array
    {
        if (! isset($this->cache['AllDataObjects'][$this->baseClass])) {
            $this->cache['AllDataObjects'][$this->baseClass] = array_values(
                ClassInfo::subclassesFor($this->baseClass, false)
            );
            $this->cache['AllDataObjects'][$this->baseClass] = array_unique($this->cache['AllDataObjects'][$this->baseClass]);
        }

        return $this->cache['AllDataObjects'][$this->baseClass];
    }

    public function getAllValidFields(string $className, ?bool $isQuickSearch = false, ?array $includedFields = [], ?array $includedClassFieldCombos = []): array
    {
        if (! isset($this->cache['AllValidFields'][$className])) {
            $this->cache['AllValidFields'][$className] = Config::inst()->get($className, 'db') ?? [];
            $this->cache['AllValidFields'][$className] = array_merge(
                $this->cache['AllValidFields'][$className],
                self::BASIC_FIELDS
            );
        }
        $array = [];
        foreach ($this->cache['AllValidFields'][$className] as $name => $type) {
            if ($this->isValidFieldType($type, $className, $name)) {
                $array[] = $name;
            } elseif (in_array($name, $includedFields, true)) {
                $array[] = $name;
            }
        }
        if (isset($includedClassFieldCombos[$className])) {
            foreach ($includedClassFieldCombos[$className] as $name) {
                $array[] = $name;
            }
        }
        // print_r($array);
        if ($isQuickSearch === false) {
            return $array;
        }
        $indexedFields = $this->getAllIndexedFields(
            $className,
            $array
        );
        return array_intersect($array, $indexedFields);
    }

    protected function getAllIndexedFields(string $className, array $dbFields): array
    {
        if (! isset($this->cache['AllIndexedFields'][$className])) {
            $this->cache['AllIndexedFields'][$className] = [];
            $indexes = Config::inst()->get($className, 'indexes');
            if (is_array($indexes)) {
                foreach ($indexes as $key => $field) {
                    if (isset($dbFields[$key])) {
                        $this->cache['AllIndexedFields'][$className][$key] = $dbFields[$key];
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
                                    $this->cache['AllIndexedFields'][$className][$testInner] = $dbFields[$key];
                                }
                            }
                        }
                    }
                }
            }
        }

        return $this->cache['AllIndexedFields'][$className];
    }

    /**
     * for a type, it works out if it is a valid field type.
     */
    protected function isValidFieldType(string $type, string $className, string $fieldName): bool
    {
        if (! isset($this->cache['AllValidFieldTypes'][$type])) {
            $this->cache['AllValidFieldTypes'][$type] = false;
            $singleton = Injector::inst()->get($className);
            $field = $singleton->dbObject($fieldName);
            if ($fieldName !== 'ClassName' && $field instanceof DBString) {
                $this->cache['AllValidFieldTypes'][$type] = true;
            }
        }

        return $this->cache['AllValidFieldTypes'][$type];
    }
}
