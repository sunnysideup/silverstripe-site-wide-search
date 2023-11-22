<?php

namespace Sunnysideup\SiteWideSearch\Control;

use SilverStripe\Control\Controller;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\SiteWideSearch\Interfaces\QuickSearchInterface;

class QuickSearchController extends Controller
{
    private static $url_segment = 'admin/quicksearch';

    private static $allowed_actions = [
        'index' => 'ADMIN',
        'getform' => 'ADMIN',
        'doform' => 'ADMIN',
    ];

    public function Link($action = '')
    {
        return '/' . $this->Config()->get('url_segment') . '/' . $action;
    }


    public function FormProvider(): Form
    {
        $form = new Form(
            $this,
            'FormProvider',
            FieldList::create(
                TextField::create('Keywords', 'Keyword(s)', $this->getKeywords() ?? '')
                    ->setAttribute('placeholder', 'e.g. agreement')
            ),
            FieldList::create(
                FormAction::create('doSearch', 'Search')
            )
        );
        $form->setFormMethod('GET');
        $form->setFormAction($this->Link());
        $form->disableSecurityToken();
        return $form;
    }

    public function FormProcessor(Form $form, array $data): array
    {
        return [];
    }
    public function getKeywords()
    {
        return '';
    }
}
