<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches\Implementations;

use SilverStripe\Assets\File;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class QuickSearchFile extends QuickSearchBaseClass
{
    public function getTitle(): string
    {
        return Injector::inst()->get(File::class)->i18n_plural_name();
    }
    public function getClassesToSearch(): array
    {
        return ClassInfo::subclassesFor(File::class, false);
    }
    public function getFieldsToSearch(): array
    {
        return [
            'Title',
            'Filename',
        ];
    }

}
