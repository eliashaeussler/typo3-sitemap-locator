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

namespace EliasHaeussler\Typo3SitemapLocator\Cache;

use EliasHaeussler\Typo3SitemapLocator\Domain;
use TYPO3\CMS\Core;

/**
 * SitemapsCache
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class SitemapsCache
{
    public function __construct(
        private readonly Core\Cache\Frontend\PhpFrontend $cache,
    ) {}

    /**
     * @return list<Domain\Model\Sitemap>
     */
    public function get(
        Core\Site\Entity\Site $site,
        ?Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): array {
        $cacheIdentifier = $this->calculateCacheIdentifier($site, $siteLanguage);
        $cacheData = $this->readCache($cacheIdentifier);

        // Early return if cache is empty or invalid
        if ($cacheData === []) {
            return [];
        }

        return array_values(
            array_map(
                static fn(string $sitemapUrl) => new Domain\Model\Sitemap(
                    new Core\Http\Uri($sitemapUrl),
                    $site,
                    $siteLanguage ?? $site->getDefaultLanguage(),
                    true,
                ),
                array_filter($cacheData, is_string(...)),
            ),
        );
    }

    /**
     * @param list<Domain\Model\Sitemap> $sitemaps
     */
    public function set(array $sitemaps): void
    {
        /** @var array<string, array<int, array{cacheIdentifier: string, sitemaps: list<Domain\Model\Sitemap>}>> $sitemapsBySite */
        $sitemapsBySite = [];

        // Re-index sitemaps by site and site language
        foreach ($sitemaps as $sitemap) {
            $siteIdentifier = $sitemap->getSite()->getIdentifier();
            $languageIdentifier = $sitemap->getSiteLanguage()->getLanguageId();

            $sitemapsBySite[$siteIdentifier] ??= [];
            $sitemapsBySite[$siteIdentifier][$languageIdentifier] ??= [
                'cacheIdentifier' => $this->calculateCacheIdentifier($sitemap->getSite(), $sitemap->getSiteLanguage()),
                'sitemaps' => [],
            ];
            $sitemapsBySite[$siteIdentifier][$languageIdentifier]['sitemaps'][] = $sitemap;
        }

        // Write sitemaps to cache
        foreach ($sitemapsBySite as $siteLanguagesOfCurrentSite) {
            foreach ($siteLanguagesOfCurrentSite as [
                'cacheIdentifier' => $cacheIdentifier,
                'sitemaps' => $sitemapsOfCurrentSite,
            ]) {
                $this->writeCache(
                    $cacheIdentifier,
                    array_map(
                        static fn(Domain\Model\Sitemap $sitemap) => (string)$sitemap->getUri(),
                        $sitemapsOfCurrentSite,
                    ),
                );
            }
        }
    }

    /**
     * @internal
     */
    public function remove(
        Core\Site\Entity\Site $site,
        ?Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): void {
        $cacheIdentifier = $this->calculateCacheIdentifier($site, $siteLanguage);

        $this->cache->remove($cacheIdentifier);
    }

    /**
     * @return list<string>
     */
    private function readCache(string $siteIdentifier): array
    {
        /** @var list<string>|false $cacheData */
        $cacheData = $this->cache->require($siteIdentifier);

        // Enforce array for cached data
        if (!\is_array($cacheData)) {
            $cacheData = [];
        }

        return $cacheData;
    }

    /**
     * @param list<string> $cacheData
     */
    private function writeCache(string $cacheIdentifier, array $cacheData): void
    {
        $this->cache->set(
            $cacheIdentifier,
            sprintf('return %s;', var_export($cacheData, true)),
        );
    }

    private function calculateCacheIdentifier(
        Core\Site\Entity\Site $site,
        ?Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): string {
        if ($siteLanguage === null) {
            $siteLanguage = $site->getDefaultLanguage();
        }

        return sprintf(
            '%s_%d_%s',
            $site->getIdentifier(),
            $siteLanguage->getLanguageId(),
            sha1((string)$siteLanguage->getBase()),
        );
    }
}
