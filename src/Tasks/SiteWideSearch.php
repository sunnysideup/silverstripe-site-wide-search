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
        if (is_string($word)) {
            $word = '';
        }
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
        $api = SearchApi::create();
        if ($debug) {
            $api->setDebug(true);
        }
        $api->setWordsAsString((string) $request->getVar('word'));
        $links = $api->getLinks();
        echo '<h2>results</h2>';
        foreach ($links as $link) {
            $item = $link->Object;
            $title = $item->getTitle() . ' (' . $item->i18n_singular_name() . ')';
            if ($debug) {
                $title .= ' Class: ' . $item->ClassName . ', ID: ' . $item->ID . ', Sort Value: ' . $link->SiteWideSearchSortValue;
            }
            if ($link->HasCMSEditLink) {
                $cmsEditLink = '<a href="' . $link->CMSEditLink . '">✎</a> ...';
            } else {
                $cmsEditLink = 'x  ...';
            }
            if ($link->HasLink) {
                DB::alteration_message($cmsEditLink . '<a href="' . $link->Link . '">' . $title . '</a> - ', 'created');
            } else {
                DB::alteration_message($cmsEditLink . $title, 'obsolete');
            }
        }
    }
}
