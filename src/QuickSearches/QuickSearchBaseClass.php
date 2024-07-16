<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;

abstract class QuickSearchBaseClass
{
    use Configurable;

    private static $is_enabled = true;

    public static function available_quick_searches()
    {
        return ClassInfo::subclassesFor(self::class, false);
    }

    public function IsEnabled(): bool
    {
        return $this->Config()->get('is_enabled');
    }

    abstract public function getTitle(): string;

    abstract public function getClassesToSearch(): array;

    abstract public function getFieldsToSearch(): array;

    public function getSortOverride(): ?array
    {
        return null;
    }

    /**
     * Should return it like this:
     * ```php
     * [
     *     'ClassName' => [
     *          'MyRelation.Title',
     *          'MyOtherRelation.Title',
     *      ]
     *
     * ]
     * ```
     */
    public function getIncludedClassFieldCombos(): array
    {
        return [];
    }

    /**
     * Should return it like this:
     * ```php
     * [
     *     'MyClass' => MyClass::get()->filter(['MyField' => 'MyValue']),
     *
     * ]
     * ```
     */
    public function getDefaultLists(): array
    {
        return [];
    }

    public static function get_list_of_quick_searches(): array
    {
        $array = [
            'limited' => 'Quick search (limited search)',
        ];
        $availableSearchClasses = self::available_quick_searches();
        if (! empty($availableSearchClasses) > 0) {
            foreach ($availableSearchClasses as $availableSearchClass) {
                $singleton = Injector::inst()->get($availableSearchClass);
                if ($singleton->isEnabled()) {
                    $array[$availableSearchClass] = $singleton->getTitle();
                }
            }
        }
        $array['all'] = 'Full Search (please use with care - this will put a lot of strain on the server)';
        return $array;
    }
}
