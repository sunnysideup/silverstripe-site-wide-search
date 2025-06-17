<?php

namespace Sunnysideup\SiteWideSearch\Tasks;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\SiteWideSearch\Api\SearchApi;

class SiteWideSearch extends BuildTask
{
    protected $title = 'Search the whole site for a word or phrase';

    protected $description = 'Search the whole site and get a list of links to the matching records';

    protected $enabled = false;

    private static $segment = 'search-and-replace';

    public function run($request)
    {
        Environment::increaseTimeLimitTo(300);
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);
        $debug = $request->postVar('debug') ? 'checked="checked"' : '';
        $word = $request->requestVar('word');
        if (! is_string($word)) {
            $word = '';
        }

        $replace = trim($request->requestVar('replace'));
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
        if ($debug !== '' && $debug !== '0') {
            $api->setDebug(true);
        }

        $api->setWordsAsString($word);
        $links = $api->getLinks();
        echo '<h2>results</h2>';
        foreach ($links as $item) {
            $title = $item->Title . ' (' . $item->SingularName . ')';
            if ($debug !== '' && $debug !== '0') {
                $title .= ' Class: ' . $item->ClassName . ', ID: ' . $item->ID . ', Sort Value: ' . $item->SiteWideSearchSortValue;
            }

            $cmsEditLink = $item->HasCMSEditLink ? '<a href="' . $item->CMSEditLink . '">✎</a> ...' : 'x  ...';
            if ($item->HasLink) {
                DB::alteration_message($cmsEditLink . '<a href="' . $item->Link . '">' . $title . '</a> - ', 'created');
            } else {
                DB::alteration_message($cmsEditLink . $title, 'obsolete');
            }
        }

        if ($replace !== '' && $replace !== '0') {
            $api->setDebug(true);
            $api->doReplacement($word, $replace);
        }
    }
}
