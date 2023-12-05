<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches;

use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\Form;

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
     *          'MyRelation.Title' => 'Varchar',
     *      ]
     *
     * ]
     * ```
     * @return array
     */
    public function getIncludedClassFieldCombos(): array
    {
        return [];
    }
    public static function get_list_of_quick_searches(): array
    {
        $array = [
            'all' => 'All',
            'limited' => 'Limited search',
        ];
        $availableSearchClasses = self::available_quick_searches();
        if(!empty($availableSearchClasses) > 0) {
            foreach($availableSearchClasses as $availableSearchClass) {
                $array[$availableSearchClass] =  Injector::inst()->get($availableSearchClass)->getTitle();
            }
        }
        return $array;

    }

}
