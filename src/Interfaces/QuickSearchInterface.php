<?php

namespace Sunnysideup\SiteWideSearch\Interfaces;

use SilverStripe\Control\Controller;
use SilverStripe\Forms\Form;

interface QuickSearchInterface
{
    public function getClassesToSearch(): array;
    public function getFieldsToSearch(): array;


}
