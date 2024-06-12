<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches\Implementations;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class QuickSearchPage extends QuickSearchBaseClass
{
    public function getTitle(): string
    {
        return Injector::inst()->get(SiteTree::class)->i18n_plural_name();
    }

    public function getClassesToSearch(): array
    {
        return [
            SiteTree::class,
        ];
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
