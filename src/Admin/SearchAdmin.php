<?php

namespace Sunnysideup\SiteWideSearch\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Assets\File;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;

use SilverStripe\Core\Environment;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\HTMLReadonlyField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\OptionsetField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\ToggleCompositeField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Security\PermissionProvider;
use Sunnysideup\SiteWideSearch\Api\SearchApi;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class SearchAdmin extends LeftAndMain implements PermissionProvider
{
    protected $listHTML = '';

    protected $keywords = '';

    protected $replace = '';

    protected $applyReplace = false;

    protected $quickSearchType = '';

    protected $searchWholePhrase = true;

    protected $rawData;

    private static $default_quick_search_type = 'limited';

    private static $url_segment = 'find';

    private static $menu_title = 'Search';

    private static $menu_icon_class = 'font-icon-p-search';

    private static $menu_priority = 9999999999;

    private static $required_permission_codes = [
        'CMS_ACCESS_SITE_WIDE_SEARCH',
    ];

    protected function init()
    {
        if($this->request->param('Action')) {
            if(empty($this->request->postVars())) {
                $this->redirect('/admin/find');
            }
        }
        parent::init();
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        $fields = $form->Fields();

        // if ($form instanceof HTTPResponse) {
        //     return $form;
        // }
        // $fields->removeByName('LastVisited');
        $fields->push(
            (new TextField('Keywords', 'Keyword(s)', $this->keywords ?? ''))
                ->setAttribute('placeholder', 'e.g. agreement')
        );
        $fields->push(
            (new HiddenField('IsSubmitHiddenField', 'IsSubmitHiddenField', 1))
        );

        $options = QuickSearchBaseClass::get_list_of_quick_searches();
        $fields->push(
            OptionsetField::create(
                'QuickSearchType',
                'Quick Search',
                $options
            )->setValue($this->bestSearchType())
        );

        $fields->push(
            (new CheckboxField('SearchWholePhrase', 'Search exact phrase', $this->searchWholePhrase))
                ->setDescription('If ticked, any item will be included that includes the whole phrase (e.g. New Zealand, rather than New OR Zealand)')
        );
        $fields->push(
            ToggleCompositeField::create(
                'ReplaceToggle',
                _t(__CLASS__ . '.ReplaceToggle', 'Replace with ... (optional - make a backup first!)'),
                [
                    (new CheckboxField('ApplyReplace', 'Run replace (please make sure to make a backup first!)', $this->applyReplace))
                      ->setDescription('Check this to replace the searched value set above with its replacement value. Note that searches ignore uppercase / lowercase, but replace actions will only search and replace values with the same upper / lowercase.'),
                    (new TextField('ReplaceWith', 'Replace (optional - careful!)', $this->replace ?? ''))
                        ->setAttribute('placeholder', 'e.g. contract - make sure to also tick checkbox below'),
                ]
            )->setHeadingLevel(4)
        );


        if (!$this->getRequest()->requestVar('Keywords')) {
            $lastResults = $this->lastSearchResults();
            if($lastResults) {
                $resultsTitle = 'Last Results';
            } else {
                $resultsTitle = 'Last Edited';
            }
            $this->listHTML = $this->renderWith(self::class . '_Results');
        } else {
            $resultsTitle = 'Search Results';
        }

        $form->setFormMethod('get', false);

        $fields->push(
            (new HTMLReadonlyField('List', $resultsTitle, DBField::create_field('HTMLText', $this->listHTML)))
        );
        $form->Actions()->push(
            FormAction::create('search', 'Find')
                ->addExtraClass('btn-primary')
                ->setUseButtonTag(true)
        );
        $form->addExtraClass('root-form cms-edit-form center fill-height');
        // $form->disableSecurityToken();
        // $form->setFormMethod('get');

        return $form;
    }

    public function search(array $data, Form $form): HTTPResponse
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
     *
     * @return ArrayList
     */
    public function Breadcrumbs($unlinked = false)
    {
        $items = parent::Breadcrumbs($unlinked);

        return new ArrayList([$items[0]]);
    }

    public function IsQuickSearch(): bool
    {
        return $this->quickSearchType === 'limited';
    }

    public function SearchResults(): ?ArrayList
    {
        Environment::increaseTimeLimitTo(300);
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);
        if(empty($this->rawData)) {
            $lastResults = $this->lastSearchResults();
            if($lastResults) {
                return $lastResults;
            }
        }
        $this->keywords = $this->workOutString('Keywords', $this->rawData);
        $this->quickSearchType = $this->workOutString('QuickSearchType', $this->rawData, $this->bestSearchType());
        $this->searchWholePhrase = $this->workOutBoolean('SearchWholePhrase', $this->rawData, false);
        $this->applyReplace = isset($this->rawData['ReplaceWith']) && $this->workOutBoolean('ApplyReplace', $this->rawData, false);
        $this->replace = $this->workOutString('ReplaceWith', $this->rawData);
        if ($this->applyReplace) {
            Injector::inst()->get(SearchApi::class)
                ->setQuickSearchType($this->quickSearchType)
                ->setSearchWholePhrase(true) // always true
                ->setWordsAsString($this->keywords)
                ->doReplacement($this->keywords, $this->replace)
            ;
            $this->applyReplace = false;
        }

        $results = Injector::inst()->get(SearchApi::class)
            ->setQuickSearchType($this->quickSearchType)
            ->setSearchWholePhrase($this->searchWholePhrase)
            ->setWordsAsString($this->keywords)
            ->getLinks()
        ;
        if($results->count() === 1) {
            $result = $results->first();
            if($result->HasCMSEditLink && $result->CMSEditLink) {
                // files do not re-redirect nicely...
                if(!in_array(File::class, ClassInfo::ancestry($result->ClassName), true)) {
                    // this is a variable, not a method!
                    $this->redirect($result->CMSEditLink);
                }
            }
        }
        // Accessing the session
        $session = $this->getRequest()->getSession();
        if($session) {
            $session->set('QuickSearchLastResults', serialize($results->toArray()));
        }
        return $results;
    }

    protected function workOutBoolean(string $fieldName, ?array $data = null, ?bool $default = false): bool
    {
        return (bool) (isset($data['IsSubmitHiddenField']) ? !empty($data[$fieldName]) : $default);
    }

    protected function workOutString(string $fieldName, ?array $data = null, ?string $default = ''): string
    {
        return trim($data[$fieldName] ?? $default);
    }

    public function providePermissions()
    {
        return [
            'CMS_ACCESS_SITE_WIDE_SEARCH' => [
                'name' => 'Access to Search Website in the CMS',
                'category' => _t('SilverStripe\\Security\\Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => 'Allow users to search for documents (all documents will also be checked to see if they are allowed to be viewed)',
            ],
        ];
    }

    protected function bestSearchType(): string
    {
        // Accessing the session
        $session = $this->getRequest()->getSession();
        if($this->quickSearchType) {
            $session->set('QuickSearchType', $this->quickSearchType);
        } elseif($session) {
            $this->quickSearchType = $session->get('QuickSearchType');
            if(isset($_GET['flush'])) {
                $this->quickSearchType = '';
                $session->set('QuickSearchType', '');
            }
        }
        if(!$this->quickSearchType) {
            $this->quickSearchType = $this->Config()->get('default_quick_search_type');
        }
        return (string) $this->quickSearchType;
    }

    protected function lastSearchResults(): ?ArrayList
    {
        // Accessing the session
        $session = $this->getRequest()->getSession();
        if($session) {
            if(isset($_GET['flush'])) {
                $session->clear('QuickSearchLastResults');
            } else {
                $data = $session->get('QuickSearchLastResults');
                if($data) {
                    $array = unserialize($data);
                    $al = ArrayList::create();
                    foreach($array as $item) {
                        $al->push($item);
                    }
                    return $al;
                }
            }
        }
        return null;
    }
}
