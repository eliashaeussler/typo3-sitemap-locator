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
use EliasHaeussler\Typo3SitemapLocator\Sitemap;
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
        private readonly Sitemap\SitemapLocator $sitemapLocator,
    ) {}

    public function formatSitemaps(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage,
        array $sitemaps,
        bool $validate = false,
    ): bool {
        $isValid = true;

        $this->render([
            'site' => [
                'identifier' => $site->getIdentifier(),
                'rootPageId' => $site->getRootPageId(),
            ],
            'siteLanguage' => [
                'languageId' => $siteLanguage->getLanguageId(),
                'title' => $siteLanguage->getTitle(),
            ],
            'sitemaps' => $this->listSitemapUrls($sitemaps, $isValid, $validate),
        ]);

        // Fail if no sitemaps were located
        if ($sitemaps === []) {
            $isValid = false;
        }

        return $isValid;
    }

    public function formatAllSitemaps(
        Core\Site\Entity\Site $site,
        array $sitemaps,
        bool $validate = false,
    ): bool {
        $isValid = true;
        $sitemapResult = [];

        foreach ($sitemaps as $languageId => $sitemapsOfLanguage) {
            $siteLanguage = $site->getLanguageById($languageId);
            $isCurrentSitemapValid = true;

            $sitemapResult[$languageId] = [
                'siteLanguage' => [
                    'languageId' => $siteLanguage->getLanguageId(),
                    'title' => $siteLanguage->getTitle(),
                ],
                'sitemaps' => $this->listSitemapUrls($sitemapsOfLanguage, $isCurrentSitemapValid, $validate),
            ];

            if (!$isCurrentSitemapValid) {
                $isValid = false;
            }
        }

        $this->render([
            'site' => [
                'identifier' => $site->getIdentifier(),
                'rootPageId' => $site->getRootPageId(),
            ],
            'sitemaps' => $sitemapResult,
        ]);

        // Fail if no sitemaps were located
        if ($sitemaps === []) {
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * @param list<Domain\Model\Sitemap> $sitemaps
     * @phpstan-return ($validate is true ? list<array{url: string, valid: bool}> : list<string>)
     */
    private function listSitemapUrls(array $sitemaps, bool &$isValid = true, bool $validate = false): array
    {
        $sitemapUrls = [];

        foreach ($sitemaps as $sitemap) {
            $sitemapUrl = (string)$sitemap->getUri();

            // On disabled validation, display only the sitemap URL
            if (!$validate) {
                $sitemapUrls[] = $sitemapUrl;
                continue;
            }

            // On enabled validation, display URL and validation result
            $isCurrentSitemapValid = $this->sitemapLocator->isValidSitemap($sitemap);
            $sitemapUrls[] = [
                'url' => $sitemapUrl,
                'valid' => $isCurrentSitemapValid,
            ];

            // Fail if located sitemap does not exist
            if (!$isCurrentSitemapValid) {
                $isValid = false;
            }
        }

        return $sitemapUrls;
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
