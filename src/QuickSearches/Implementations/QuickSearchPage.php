<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches\Implementations;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class QuickSearchPage extends QuickSearchBaseClass
{
    public function getTitle(): string
    {
        return 'Pages';
    }
    public function getClassesToSearch(): array
    {
        return ClassInfo::subclassesFor(SiteTree::class, false);
    }
    public function getFieldsToSearch(): array
    {
        return [
            'Title',
            'URLSegment',
            'MenuTitle',
        ];
    }

}
