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
 * JsonFormatter
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @internal
 */
final class JsonFormatter implements Formatter
{
    public function __construct(
        private readonly Console\Output\OutputInterface&Console\Style\StyleInterface $output,
    ) {}

    public function formatSitemaps(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage,
        array $sitemaps,
    ): void {
        $this->render([
            'site' => [
                'identifier' => $site->getIdentifier(),
                'rootPageId' => $site->getRootPageId(),
            ],
            'siteLanguage' => [
                'languageId' => $siteLanguage->getLanguageId(),
                'title' => $siteLanguage->getTitle(),
            ],
            'sitemaps' => array_map(
                static fn(Domain\Model\Sitemap $sitemap) => (string)$sitemap->getUri(),
                $sitemaps,
            ),
        ]);
    }

    public function formatAllSitemaps(
        Core\Site\Entity\Site $site,
        array $sitemaps,
    ): void {
        $sitemapResult = [];

        foreach ($sitemaps as $languageId => $sitemapsOfLanguage) {
            $siteLanguage = $site->getLanguageById($languageId);
            $sitemapResult[$languageId] = [
                'siteLanguage' => [
                    'languageId' => $siteLanguage->getLanguageId(),
                    'title' => $siteLanguage->getTitle(),
                ],
                'sitemaps' => array_map(
                    static fn(Domain\Model\Sitemap $sitemap) => (string)$sitemap->getUri(),
                    $sitemapsOfLanguage,
                ),
            ];
        }

        $this->render([
            'site' => [
                'identifier' => $site->getIdentifier(),
                'rootPageId' => $site->getRootPageId(),
            ],
            'sitemaps' => $sitemapResult,
        ]);
    }

    /**
     * @param array<string, mixed> $json
     */
    private function render(array $json): void
    {
        $this->output->writeln(
            json_encode($json, JSON_UNESCAPED_SLASHES | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR),
        );
    }
}
