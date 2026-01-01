<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "sitemap_locator".
 *
 * Copyright (C) 2023-2026 Elias Häußler <elias@haeussler.dev>
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

namespace EliasHaeussler\Typo3SitemapLocator\Command\Formatter;

use EliasHaeussler\Typo3SitemapLocator\Domain;
use EliasHaeussler\Typo3SitemapLocator\Sitemap;
use Symfony\Component\Console;
use TYPO3\CMS\Core;

/**
 * TextFormatter
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @internal
 */
final readonly class TextFormatter implements Formatter
{
    public function __construct(
        private Console\Output\OutputInterface&Console\Style\StyleInterface $output,
        private Sitemap\SitemapLocator $sitemapLocator,
    ) {}

    public function formatSitemaps(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage,
        array $sitemaps,
        bool $validate = false,
    ): bool {
        if ($sitemaps === []) {
            $this->output->warning(
                sprintf(
                    'No XML sitemaps found for site "%s" (%d) and language "%s" (%d).',
                    $site->getIdentifier(),
                    $site->getRootPageId(),
                    $siteLanguage->getTitle(),
                    $siteLanguage->getLanguageId(),
                ),
            );

            return false;
        }

        $this->output->title(
            sprintf(
                'XML sitemap%s for site "%s" (%d) and language "%s" (%d):',
                count($sitemaps) === 1 ? '' : 's',
                $site->getIdentifier(),
                $site->getRootPageId(),
                $siteLanguage->getTitle(),
                $siteLanguage->getLanguageId(),
            ),
        );

        return $this->listSitemapUrls($sitemaps, $validate);
    }

    public function formatAllSitemaps(
        Core\Site\Entity\Site $site,
        array $sitemaps,
        bool $validate = false,
    ): bool {
        $isValid = true;

        if ($sitemaps === []) {
            $this->output->warning(
                sprintf(
                    'No XML sitemaps found for site "%s" (%d).',
                    $site->getIdentifier(),
                    $site->getRootPageId(),
                ),
            );

            return false;
        }

        $this->output->title(
            sprintf(
                'XML sitemap%s for site "%s" (%d)',
                count($sitemaps) === 1 ? '' : 's',
                $site->getIdentifier(),
                $site->getRootPageId(),
            ),
        );

        foreach ($sitemaps as $languageId => $sitemapsOfLanguage) {
            $siteLanguage = $site->getLanguageById($languageId);

            $this->output->section(
                sprintf('Language "%s" (%d)', $siteLanguage->getTitle(), $siteLanguage->getLanguageId()),
            );

            if (!$this->listSitemapUrls($sitemapsOfLanguage, $validate)) {
                $isValid = false;
            }
        }

        return $isValid;
    }

    /**
     * @param list<Domain\Model\Sitemap> $sitemaps
     */
    private function listSitemapUrls(array $sitemaps, bool $validate = false): bool
    {
        $isValid = true;
        $sitemapUrls = [];

        foreach ($sitemaps as $sitemap) {
            $sitemapUrl = (string)$sitemap->getUri();

            if ($validate && !$this->sitemapLocator->isValidSitemap($sitemap)) {
                $isValid = false;
                $sitemapUrl .= ' (<error>invalid</error>)';
            }

            $sitemapUrls[] = $sitemapUrl;
        }

        $this->output->listing($sitemapUrls);

        return $isValid;
    }
}
