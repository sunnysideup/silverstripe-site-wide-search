<?php

namespace Sunnysideup\SiteWideSearch\QuickSearches;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\SiteWideSearch\Interfaces\QuickSearchInterface;

class QuickSearchPage implements QuickSearchInterface
{
    use Configurable;

    private static $is_enabled = true;

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
