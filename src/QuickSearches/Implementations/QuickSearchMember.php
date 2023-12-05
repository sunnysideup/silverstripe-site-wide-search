<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches\Implementations;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Member;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class QuickSearchMember extends QuickSearchBaseClass
{
    public function getTitle(): string
    {
        return Injector::inst()->get(Member::class)->i18n_plural_name();
    }
    public function getClassesToSearch(): array
    {
        return [
            Member::class,
        ];
    }
    public function getFieldsToSearch(): array
    {
        return [
            'FirstName',
            'Surname',
            'Email',
        ];
    }

}
