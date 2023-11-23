Use one of these two links to see if it works for you:

- `dev/tasks/search-and-replace`
- `admin/find/`

If it does then you can build your own presentation layer using the API:

```php

//use statements need to be added !

$myLinks = Injector::inst()->get(SearchApi::class)
    ->setSearchTemplateName(MyQuickSearch::class)
    ->setBaseClass(DataObject::class)
    ->setExcludedClasses([MyMemberDetails::class])
    ->setExcludedFields(['SecretStuff'])
    // ->setIncludedClasses([MyOnlyThingWeCareAbout::class])
    // ->setIncludedFields(['Title'])
    ->setIsQuickSearch(false)
    ->setWords(['MyNiceWord', 'OtherWord'])
    ->getLinks();

```

OR

```php

$myLinks = Injector::inst()->get(SearchApi::class)
    ->setWords(['MyNiceWord', 'OtherWord'])
    ->getLinks();
```

## creating your own searches:

Also consider:

```yml
Sunnysideup\SiteWideSearch\Admin\SearchAdmin:
  default_quick_search_type: Website\App\QuickSearches\MyQuickSearch
```

And then creat your own quick search class:

```php

namespace Website\App\QuickSearches\MyQuickSearch;


use Website\App\MyDataObject;
use SilverStripe\Core\ClassInfo;
use Sunnysideup\SiteWideSearch\QuickSearches\QuickSearchBaseClass;

class QuickSearchPage extends QuickSearchBaseClass
{
    public function getTitle(): string
    {
        return 'Pages';
    }
    public function getClassesToSearch(): array
    {
        return ClassInfo::subclassesFor(MyDataObject::class, false);
    }
    public function getFieldsToSearch(): array
    {
        return [
            'Title',
            'URLSegment',
            'MenuTitle',
        ];
    }

}

```
