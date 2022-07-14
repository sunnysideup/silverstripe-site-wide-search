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
    private static $segment = 'search-and-replace';

    /**
     * {@inheritDoc}
     */
    public function run($request)
    {
        Environment::increaseTimeLimitTo(300);
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);
        $debug = $request->postVar('debug') ? 'checked="checked"' : '';
        $word = Convert::raw2sql($request->requestVar('word'));
        if (! is_string($word)) {
            $word = '';
        }
        $replace = Convert::raw2sql($request->requestVar('replace'));
        if (! is_string($replace)) {
            $replace = '';
        }

        $html = '
<form methd="post" action="">
    <h2>Enter Search Word(s):</h2>
    <h3>Find</h3>
    <input name="word" value="' . Convert::raw2att($word) . '" style="width: 500px; padding: 5px;"  />
    <h3>Replace (optional)</h3>
    <input name="replace" value="' . Convert::raw2att($replace) . '" style="width: 500px; padding: 5px;" />
    <h3>Do it now ... (careful)</h3>
    <input type="submit" value="search OR search and replace" style="width: 250px; padding: 5px;" />
    <br />
    <br />debug: <input name="debug" type="checkbox" ' . $debug . '  />
</form>
';
        echo $html;
        $api = SearchApi::create();
        if ($debug) {
            $api->setDebug(true);
        }

        $api->setWordsAsString($word);
        $links = $api->getLinks();
        echo '<h2>results</h2>';
        foreach ($links as $link) {
            $item = $link->Object;
            $title = $item->getTitle() . ' (' . $item->i18n_singular_name() . ')';
            if ($debug) {
                $title .= ' Class: ' . $item->ClassName . ', ID: ' . $item->ID . ', Sort Value: ' . $link->SiteWideSearchSortValue;
            }

            $cmsEditLink = $link->HasCMSEditLink ? '<a href="' . $link->CMSEditLink . '">✎</a> ...' : 'x  ...';
            if ($link->HasLink) {
                DB::alteration_message($cmsEditLink . '<a href="' . $link->Link . '">' . $title . '</a> - ', 'created');
            } else {
                DB::alteration_message($cmsEditLink . $title, 'obsolete');
            }
        }
        if($replace) {
            $api->doReplacement($word, $replace);
            $api->setDebug(true);
        }
    }
}
