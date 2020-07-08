<?php

namespace Sunnysideup\SiteWideSearch\Tasks;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

use Sunnysideup\SiteWideSearch\Api\SearchApi;

class SiteWideSearch extends BuildTask
{
    /**
     * {@inheritDoc}
     */
    protected $title = 'Search the whole site for a word or phrase';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Search the whole site and get a list of links to the matching records';

    /**
     * {@inheritDoc}
     */
    protected $enabled = true;

    /**
     * {@inheritDoc}
     */
    public function run($request)
    {
        Environment::setTimeLimitMax(600);
        $debug = $request->getVar('debug') ? 'checked="checked"' : '';
        $word = Convert::raw2att($request->getVar('word'));
        $html = '
<form methd="get" action="">
    <h2>Enter Search Word(s):</h2>
    <input name="word" value="' . $word . '" />
    <input type="submit" value="search" />
    <br />
    <br />debug: <input name="debug" type="checkbox" ' . $debug . '  />
</form>
';
        echo $html;
        if ($request->getVar('word')) {
            $api = SearchApi::create();
            if ($debug) {
                $api->setDebug(true);
            }
            $words = explode(',', $request->getVar('word'));
            foreach ($words as $word) {
                $innerWords = explode(' ', $word);
                foreach ($innerWords as $finalWord) {
                    $api->addWord(trim($finalWord));
                }
            }
            $links = $api->getLinks();
            foreach ($links as $link) {
                $item = $link->Object;
                $title = $item->getTitle() . ' (' . $item->i18n_singular_name() . ')';
                if ($debug) {
                    $title .= ' ... ' . $item->ClassName . ', ' . $item->ID;
                }
                if ($link->HasLink) {
                    DB::alteration_message('<a href="' . $link->Link . '">' . $title . '</a>', 'created');
                } else {
                    DB::alteration_message($title, 'obsolete');
                }
                if ($link->HasCMSEditLink) {
                    DB::alteration_message('<a href="' . $link->CMSEditLink . '">edit it</a>', 'created');
                } else {
                    DB::alteration_message('no edit available', 'obsolete');
                }
            }
        }
    }
}
