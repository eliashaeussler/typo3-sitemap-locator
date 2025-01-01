<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "sitemap_locator".
 *
 * Copyright (C) 2023-2025 Elias Häußler <elias@haeussler.dev>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

namespace EliasHaeussler\Typo3SitemapLocator\Command;

use EliasHaeussler\Typo3SitemapLocator\Exception;
use EliasHaeussler\Typo3SitemapLocator\Sitemap;
use EliasHaeussler\Typo3SitemapLocator\Utility;
use Symfony\Component\Console;
use TYPO3\CMS\Core;

/**
 * LocateSitemapsCommand
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class LocateSitemapsCommand extends Console\Command\Command
{
    private Console\Style\SymfonyStyle $io;
    private Formatter\Formatter $formatter;

    public function __construct(
        private readonly Sitemap\SitemapLocator $sitemapLocator,
        private readonly Core\Site\SiteFinder $siteFinder,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Locate XML sitemaps of a given site and optional site language.');

        $this->addArgument(
            'site',
            Console\Input\InputArgument::REQUIRED,
            'Site identifier or root page ID of site for which to locate XML sitemaps.',
        );
        $this->addOption(
            'language',
            'l',
            Console\Input\InputOption::VALUE_REQUIRED,
            'Optional identifier of a site language for which to locate XML sitemaps, defaults to the default language of the given site.',
        );
        $this->addOption(
            'all',
            'a',
            Console\Input\InputOption::VALUE_NONE,
            'Locate XML sitemaps of all available site languages of the given site.',
        );
        $this->addOption(
            'validate',
            null,
            Console\Input\InputOption::VALUE_NONE,
            'Validate if located XML sitemaps actually exist and are properly accessible.',
        );
        $this->addOption(
            'json',
            'j',
            Console\Input\InputOption::VALUE_NONE,
            'Format located XML sitemaps as JSON instead of listing.',
        );
    }

    protected function initialize(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        $this->io = new Console\Style\SymfonyStyle($input, $output);
        $this->formatter = $input->getOption('json')
            ? new Formatter\JsonFormatter($this->io, $this->sitemapLocator)
            : new Formatter\TextFormatter($this->io, $this->sitemapLocator)
        ;
    }

    /**
     * @throws Core\Exception\SiteNotFoundException
     * @throws Exception\NoSitesAreConfigured
     */
    protected function interact(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): void
    {
        /** @var string|null $site */
        $site = $input->getArgument('site');

        // Early return if site is already specified
        if ($site !== null) {
            return;
        }

        // Site
        $siteIdentifier = $this->io->choice('Please select an available site', $this->decorateAvailableSites());
        $input->setArgument('site', $siteIdentifier);

        // Site language
        if (!$input->getOption('all') && !$this->io->confirm('Locate sitemaps of all available languages?', false)) {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $availableSiteLanguages = $this->decorateAvailableSiteLanguages($site);
            $question = new Console\Question\ChoiceQuestion(
                'Please select a site language',
                $availableSiteLanguages,
                $site->getDefaultLanguage()->getLanguageId(),
            );
            $question->setValidator(
                fn(string|int $siteLanguage) => $this->validateSiteLanguage($siteLanguage, $site, $availableSiteLanguages),
            );

            $input->setOption(
                'language',
                $this->io->askQuestion($question),
            );
        } else {
            $input->setOption('all', true);
        }
    }

    protected function execute(Console\Input\InputInterface $input, Console\Output\OutputInterface $output): int
    {
        $siteIdentifier = $input->getArgument('site');
        $language = $input->getOption('language');
        $all = $input->getOption('all');
        $validate = $input->getOption('validate');

        // Fetch site
        try {
            if (is_numeric($siteIdentifier)) {
                $site = $this->siteFinder->getSiteByRootPageId((int)$siteIdentifier);
            } else {
                $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            }
        } catch (Core\Exception\SiteNotFoundException) {
            $this->io->error(
                sprintf('Site with identifier or root page ID "%s" does not exist.', $siteIdentifier),
            );

            return self::FAILURE;
        }

        // Locate sitemaps of all available languages
        if ($all) {
            return $this->locateAllBySite($site, $validate) ? self::SUCCESS : self::FAILURE;
        }

        // Fetch site language
        if (is_numeric($language)) {
            try {
                $siteLanguage = $site->getLanguageById((int)$language);
            } catch (\InvalidArgumentException) {
                $this->io->error(
                    sprintf('Site language "%d" does not exist in site "%s".', (int)$language, $site->getIdentifier()),
                );

                return self::FAILURE;
            }
        } else {
            $siteLanguage = $site->getDefaultLanguage();
        }

        // Locate sitemaps of configured language
        return $this->locateBySite($site, $siteLanguage, $validate) ? self::SUCCESS : self::FAILURE;
    }

    private function locateBySite(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage,
        bool $validate = false,
    ): bool {
        try {
            $sitemaps = $this->sitemapLocator->locateBySite($site, $siteLanguage);
        } catch (Exception\BaseUrlIsNotSupported|Exception\SitemapIsMissing $exception) {
            $this->io->error(
                sprintf('Unable to locate XML sitemaps due to the following exception: %s', $exception->getMessage()),
            );

            return false;
        }

        return $this->formatter->formatSitemaps($site, $siteLanguage, $sitemaps, $validate);
    }

    private function locateAllBySite(
        Core\Site\Entity\Site $site,
        bool $validate = false,
    ): bool {
        try {
            $sitemaps = $this->sitemapLocator->locateAllBySite($site);
        } catch (Exception\BaseUrlIsNotSupported|Exception\SitemapIsMissing $exception) {
            $this->io->error(
                sprintf('Unable to locate XML sitemaps due to the following exception: %s', $exception->getMessage()),
            );

            return false;
        }

        return $this->formatter->formatAllSitemaps($site, $sitemaps, $validate);
    }

    /**
     * @return array<string, string>
     * @throws Exception\NoSitesAreConfigured
     */
    private function decorateAvailableSites(): array
    {
        $sites = $this->siteFinder->getAllSites();
        $decoratedSites = [];

        // Validate sites
        if ($sites === []) {
            throw new Exception\NoSitesAreConfigured();
        }

        // Decorate sites
        foreach ($sites as $site) {
            $pageTitle = Utility\BackendUtility::getPageTitle($site->getRootPageId());
            $decoratedSites[$site->getIdentifier()] = sprintf(
                '%s (%d)',
                $pageTitle ?? $site->getIdentifier(),
                $site->getRootPageId(),
            );
        }

        return $decoratedSites;
    }

    /**
     * @return array<int, string>
     */
    private function decorateAvailableSiteLanguages(Core\Site\Entity\Site $site): array
    {
        $siteLanguages = $site->getAllLanguages();
        $decoratedSiteLanguages = [];

        foreach ($siteLanguages as $siteLanguage) {
            $decoratedSiteLanguages[$siteLanguage->getLanguageId()] = sprintf(
                '%s (%d)',
                $siteLanguage->getTitle(),
                $siteLanguage->getLanguageId(),
            );
        }

        return $decoratedSiteLanguages;
    }

    /**
     * @param array<int, string> $siteLanguageMapping
     * @throws Console\Exception\RuntimeException
     */
    private function validateSiteLanguage(
        string|int $languageId,
        Core\Site\Entity\Site $site,
        array $siteLanguageMapping,
    ): int {
        if (is_numeric($languageId)) {
            $languageId = (int)$languageId;
        }
        if (is_string($languageId)) {
            $languageId = array_search($languageId, $siteLanguageMapping, true);
        }

        if (!is_int($languageId)) {
            throw new Console\Exception\RuntimeException('Invalid language ID given.', 1698771577);
        }

        try {
            return $site->getLanguageById($languageId)->getLanguageId();
        } catch (\InvalidArgumentException $exception) {
            throw new Console\Exception\RuntimeException($exception->getMessage(), $exception->getCode());
        }
    }
}
