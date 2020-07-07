<?php

namespace Sunnysideup\SiteWideSearch\Admin;

use SilverStripe\Admin\LeftAndMain;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\CheckboxField;
use SilverStripe\Core\Environment;

class SearchAdmin extends LeftAndMain
{
    private static $url_segment = 'find';

    private static $menu_title = 'Find Content';

    private static $menu_priority = 99999;

    private static $required_permission_codes = false;

    private static $tree_class = DataObject::class;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        if ($form instanceof HTTPResponse) {
            return $form;
        }
        //
        // $form->Fields()->removeByName('LastVisited');
        $form->Fields()->push(
            (new TextField('Keywords'))
                ->setAttribute('placeholder', 'e.g. insurance OR rental agreement')
        );
        $form->Fields()->push(
            (new CheckboxField('QuickSearch'))
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
        $words = explode(', ', $data['Keywords']);
        $myLinks = Injector::inst()->get(SearchApi::class)
            ->setBaseClass(DataObject::class)
            ->setExcludedClasses([MyMemberDetails::class])
            ->setExcludedFields(['SecretStuff'])
            ->setIsQuickSearch($data['QuickSearch'])
            ->setWords($words)
            ->getLinks();

        // Existing or new record?

        $message = _t(__CLASS__ . '.SAVEDUP', 'Searched.');
        if ($this->getSchemaRequested()) {
            $form->setMessage($message, 'good');
            $response = $this->getSchemaResponse($schemaId, $form);
        } else {
            $response = $this->getResponseNegotiator()->respond($request);
        }

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
        return new ArrayList(array($items[0]));
    }
}
