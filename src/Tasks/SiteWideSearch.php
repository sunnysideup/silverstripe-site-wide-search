<?php

declare(strict_types=1);

namespace Sunnysideup\SiteWideSearch\Tasks;

use Override;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Sunnysideup\SiteWideSearch\Api\SearchApi;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class SiteWideSearch extends BuildTask
{
    protected string $title = 'Search the whole site for a word or phrase';

    protected static string $description = 'Search the whole site and get a list of links to the matching records';

    protected static string $commandName = 'search-and-replace';

    #[Override]
    public function getOptions(): array
    {
        return [
            new InputOption('word', 'w', InputOption::VALUE_REQUIRED, 'The word or phrase to search for', ''),
            new InputOption('replace', 'r', InputOption::VALUE_OPTIONAL, 'The replacement text (optional)', ''),
            new InputOption('debug', 'd', InputOption::VALUE_NONE, 'Enable debug mode'),
        ];
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        Environment::increaseTimeLimitTo(300);
        Environment::setMemoryLimitMax(-1);
        Environment::increaseMemoryLimitTo(-1);

        $debug = $input->getOption('debug');
        $word = $input->getOption('word');
        if (! is_string($word)) {
            $word = '';
        }

        $replace = trim((string) $input->getOption('replace'));
        if (! is_string($replace)) {
            $replace = '';
        }

        if ($word === '' || $word === '0') {
            $output->writeln('<error>Please provide a search word using --word option</error>');
            return Command::FAILURE;
        }

        $api = SearchApi::create();
        if ($debug) {
            $api->setDebug(true);
        }

        $api->setWordsAsString($word);
        $links = $api->getLinks();

        $output->writeln('<info>Search Results:</info>');
        foreach ($links as $item) {
            $title = $item->Title . ' (' . $item->SingularName . ')';
            if ($debug) {
                $title .= ' Class: ' . $item->ClassName . ', ID: ' . $item->ID . ', Sort Value: ' . $item->SiteWideSearchSortValue;
            }

            $cmsEditLink = $item->HasCMSEditLink ? '[CMS] ' : '[No CMS] ';
            if ($item->HasLink) {
                $output->writeln('<info>' . $cmsEditLink . $title . ' - ' . $item->Link . '</info>');
            } else {
                $output->writeln('<comment>' . $cmsEditLink . $title . '</comment>');
            }
        }

        if ($replace !== '' && $replace !== '0') {
            $api->setDebug(true);
            $api->doReplacement($word, $replace);
            $output->writeln('<info>Replacement completed</info>');
        }

        return Command::SUCCESS;
    }
}
