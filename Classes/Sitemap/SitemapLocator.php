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

namespace EliasHaeussler\Typo3SitemapLocator\Sitemap;

use EliasHaeussler\Typo3SitemapLocator\Cache;
use EliasHaeussler\Typo3SitemapLocator\Domain;
use EliasHaeussler\Typo3SitemapLocator\Exception;
use EliasHaeussler\Typo3SitemapLocator\Utility;
use TYPO3\CMS\Core;

/**
 * SitemapLocator
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class SitemapLocator
{
    /**
     * @param iterable<Provider\Provider> $providers
     * @throws Exception\ProviderIsInvalid
     * @throws Exception\ProviderIsNotSupported
     */
    public function __construct(
        private readonly Core\Http\RequestFactory $requestFactory,
        private readonly Cache\SitemapsCache $cache,
        private readonly iterable $providers,
    ) {
        $this->validateProviders();
    }

    /**
     * @return list<Domain\Model\Sitemap>
     * @throws Exception\BaseUrlIsNotSupported
     * @throws Exception\SitemapIsMissing
     */
    public function locateBySite(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): array {
        // Get sitemaps from cache
        if (($sitemaps = $this->cache->get($site, $siteLanguage)) !== []) {
            return $sitemaps;
        }

        // Build and validate base URL
        $baseUrl = $siteLanguage !== null ? $siteLanguage->getBase() : $site->getBase();
        if ($baseUrl->getHost() === '') {
            throw new Exception\BaseUrlIsNotSupported($baseUrl);
        }

        // Resolve and validate sitemaps
        $sitemaps = $this->resolveSitemaps($site, $siteLanguage);
        if ($sitemaps === []) {
            throw new Exception\SitemapIsMissing($site);
        }

        // Store resolved sitemaps in cache
        $this->cache->set($sitemaps);

        return $sitemaps;
    }

    /**
     * @return array<int, list<Domain\Model\Sitemap>>
     * @throws Exception\BaseUrlIsNotSupported
     * @throws Exception\SitemapIsMissing
     */
    public function locateAllBySite(Core\Site\Entity\Site $site): array
    {
        $sitemaps = [];

        foreach ($site->getAvailableLanguages(Utility\BackendUtility::getBackendUser()) as $siteLanguage) {
            if ($siteLanguage->isEnabled()) {
                $sitemaps[$siteLanguage->getLanguageId()] = $this->locateBySite($site, $siteLanguage);
            }
        }

        return $sitemaps;
    }

    public function sitemapExists(Domain\Model\Sitemap $sitemap): bool
    {
        try {
            $response = $this->requestFactory->request((string)$sitemap->getUri(), 'HEAD');

            return $response->getStatusCode() < 400;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * @return list<Domain\Model\Sitemap>
     */
    private function resolveSitemaps(
        Core\Site\Entity\Site $site,
        Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): array {
        foreach ($this->providers as $provider) {
            if (($sitemaps = $provider->get($site, $siteLanguage)) !== []) {
                return $sitemaps;
            }
        }

        return [];
    }

    /**
     * @throws Exception\ProviderIsInvalid
     * @throws Exception\ProviderIsNotSupported
     */
    private function validateProviders(): void
    {
        foreach ($this->providers as $provider) {
            if (!\is_object($provider)) {
                throw new Exception\ProviderIsNotSupported($provider);
            }

            if (!is_a($provider, Provider\Provider::class)) {
                throw new Exception\ProviderIsInvalid($provider);
            }
        }
    }
}
