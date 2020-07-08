<?php

namespace Sunnysideup\SiteWideSearch\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Core\Environment;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Control\HTTPResponse;

use Sunnysideup\SiteWideSearch\Api\SearchApi;

class SearchAdmin extends LeftAndMain
{
    protected $listHTML = '';

    protected $keywords = '';

    protected $isQuickSearch = false;

    protected $rawData = null;

    private static $url_segment = 'find';

    private static $menu_title = 'Search';

    // private static $menu_icon = 'silverstripe/cms:images/search.svg';

    private static $menu_priority = 99999;

    private static $required_permission_codes = false;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($form instanceof HTTPResponse) {
            return $form;
        }
        // $form->Fields()->removeByName('LastVisited');
        $form->Fields()->push(
            (new TextField('Keywords', 'Keyword(s)', $this->keywords ?? ''))
                ->setAttribute('placeholder', 'e.g. insurance OR rental agreement')
        );
        $form->Fields()->push(
            (new CheckboxField('QuickSearch', 'Search Main Fields Only', $this->isQuickSearch))
                ->setDescription('Only search main fields?')
        );
        $form->Fields()->push(
            (new LiteralField('List', $this->listHTML))
                ->setDescription('Only search main fields?')
        );
        $form->Actions()->push(
            FormAction::create('save', 'Find')
                ->addExtraClass('btn-primary font-icon-save')
                ->setUseButtonTag(true)
        );
        $form->addExtraClass('root-form cms-edit-form center fill-height');

        return $form;
    }

    public function save($data, $form)
    {
        Environment::setTimeLimitMax(120);
        if (empty($data['Keywords'])) {
            $form->sessionMessage('Please enter one or more keywords', 'bad');
            return $this->redirectBack();
        }
        $request = $this->getRequest();

        $this->rawData = $data;
        $this->listHTML = $this->renderWith(self::class . '_Results');
        // Existing or new record?

        $response = $this->getResponseNegotiator()->respond($request);

        $message = _t(__CLASS__ . '.SEARCH_COMPLETED', 'Searched Completed');
        $response->addHeader('X-Status', rawurlencode($message));

        return $response;
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
        if ($this->rawData) {
            $this->isQuickSearch = empty($this->rawData['QuickSearch']) ? false : true;
            $this->keywords = trim($this->rawData['Keywords'] ?? '');
            if ($this->keywords) {
                $words = explode(', ', $this->rawData['Keywords']);
                return Injector::inst()->get(SearchApi ::class)
                    ->setBaseClass(DataObject::class)
                    ->setIsQuickSearch($this->isQuickSearch)
                    ->setWords($words)
                    ->getLinks();
            }
        }
        return null;
    }
}
