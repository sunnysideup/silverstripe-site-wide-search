


Use the Task to see if it works for you.  

`dev/tasks/Sunnysideup-SiteWideSearch-Tasks-SiteWideSearch`

If it does then you can build your own presentation layer using the API:

```php

//use statements need to be added !

$myLinks = Injector::inst()->get(SearchApi::class)
    ->setBaseClass(DataObject::class)
    ->setExcludedClasses([MyMemberDetails::class])
    ->setExcludedFields(['SecretStuff'])
    ->setIsQuickSearch(false)
    ->setWords(['MyNiceWord', 'OtherWord'])
    ->getLinks();


```
