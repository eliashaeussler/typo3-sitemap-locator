<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "sitemap_locator".
 *
 * Copyright (C) 2023 Elias Häußler <elias@haeussler.dev>
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
use Symfony\Component\Console;
use TYPO3\CMS\Core;

/**
 * TextFormatter
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @internal
 */
final class TextFormatter implements Formatter
{
    public function __construct(
        private readonly Console\Output\OutputInterface&Console\Style\StyleInterface $output,
    ) {}

    public function formatSitemaps(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage,
        array $sitemaps,
    ): void {
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
        $this->output->listing(
            array_map(
                static fn(Domain\Model\Sitemap $sitemap) => (string)$sitemap->getUri(),
                $sitemaps,
            )
        );
    }

    public function formatAllSitemaps(
        Core\Site\Entity\Site $site,
        array $sitemaps,
    ): void {
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
            $this->output->listing(
                array_map(
                    static fn(Domain\Model\Sitemap $sitemap) => (string)$sitemap->getUri(),
                    $sitemapsOfLanguage,
                ),
            );
        }
    }
}
