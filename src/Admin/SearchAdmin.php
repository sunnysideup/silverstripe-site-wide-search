<?php

namespace Sunnysideup\SiteWideSearch\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;

use SilverStripe\Security\PermissionProvider;

use Sunnysideup\SiteWideSearch\Api\SearchApi;

class SearchAdmin extends LeftAndMain implements PermissionProvider
{
    protected $listHTML = '';

    protected $keywords = '';

    protected $isQuickSearch = false;

    protected $rawData = null;

    private static $url_segment = 'find';

    private static $menu_title = 'Search';

    private static $menu_icon_class = 'font-icon-p-search';

    private static $menu_priority = 99999;

    private static $required_permission_codes = [
        'CMS_ACCESS_SITE_WIDE_SEARCH',
    ];

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        // if ($form instanceof HTTPResponse) {
        //     return $form;
        // }
        // $form->Fields()->removeByName('LastVisited');
        $form->Fields()->push(
            (new TextField('Keywords', 'Keyword(s)', $this->keywords ?? ''))
                ->setAttribute('placeholder', 'e.g. insurance OR rental agreement')
        );
        $form->Fields()->push(
            (new CheckboxField('QuickSearch', 'Search Main Fields Only', $this->isQuickSearch))
                ->setDescription('This is faster but only searches a limited number of fields')
        );
        if (! $this->getRequest()->requestVar('Keywords')) {
            $resultsTitle = 'Recently Edited';
            $this->listHTML = $this->renderWith(self::class . '_Results');
        } else {
            $resultsTitle = 'Search Results';
        }
        $form->Fields()->push(
            (new HTMLReadonlyField('List', $resultsTitle, DBField::create_field('HTMLText', $this->listHTML)))
        );
        $form->Fields()->push(
            (new LiteralField('Styling', $this->renderWith(self::class . '_Styling')))
        );
        $form->Actions()->push(
            FormAction::create('save', 'Find')
                ->addExtraClass('btn-primary')
                ->setUseButtonTag(true)
        );
        $form->addExtraClass('root-form cms-edit-form center fill-height');
        // $form->disableSecurityToken();
        // $form->setFormMethod('get');

        return $form;
    }

    public function save($data, $form)
    {
        if (empty($data['Keywords'])) {
            $form->sessionMessage('Please enter one or more keywords', 'bad');
            return $this->redirectBack();
        }
        $request = $this->getRequest();

        $this->rawData = $data;
        $this->listHTML = $this->renderWith(self::class . '_Results');
        // Existing or new record?

        return $this->getResponseNegotiator()->respond($request);
    }

    /**
     * Only show first element, as the profile form is limited to editing
     * the current member it doesn't make much sense to show the member name
     * in the breadcrumbs.
     *
     * @param bool $unlinked
     * @return ArrayList
     */
    public function Breadcrumbs($unlinked = false)
    {
        $items = parent::Breadcrumbs($unlinked);
        return new ArrayList([$items[0]]);
    }

    public function SearchResults(): ?ArrayList
    {
        $this->isQuickSearch = empty($this->rawData['QuickSearch']) ? false : true;
        $this->keywords = trim($this->rawData['Keywords'] ?? '');
        return Injector::inst()->get(SearchApi ::class)
            ->setBaseClass(DataObject::class)
            ->setIsQuickSearch($this->isQuickSearch)
            ->setWordsAsString($this->keywords)
            ->getLinks();
    }

    public function providePermissions()
    {
        return [
            "CMS_ACCESS_SITE_WIDE_SEARCH" => array(
                'name' => 'Access to Search Website in the CMS',
                'category' => _t('SilverStripe\\Security\\Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => 'Allow users to search for documents (all documents will also be checked to see if they are allowed to be viewed)',
            )
        ];
    }

}
