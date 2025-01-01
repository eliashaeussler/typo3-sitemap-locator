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

namespace EliasHaeussler\Typo3SitemapLocator\Sitemap\Provider;

use EliasHaeussler\Typo3SitemapLocator\Domain;
use EliasHaeussler\Typo3SitemapLocator\Utility;
use TYPO3\CMS\Core;

/**
 * SiteProvider
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class SiteProvider implements Provider
{
    public function get(
        Core\Site\Entity\Site $site,
        ?Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): array {
        if ($siteLanguage !== null && $siteLanguage !== $site->getDefaultLanguage()) {
            $sitemapPath = $siteLanguage->toArray()['xml_sitemap_path'] ?? null;
        } else {
            $sitemapPath = $site->getConfiguration()['xml_sitemap_path'] ?? null;
        }

        // Early return if no sitemap path is configured
        if (!\is_string($sitemapPath) || trim($sitemapPath) === '') {
            return [];
        }

        $sitemap = new Domain\Model\Sitemap(
            Utility\HttpUtility::getSiteUrlWithPath($site, $sitemapPath, $siteLanguage),
            $site,
            $siteLanguage ?? $site->getDefaultLanguage(),
        );

        return [$sitemap];
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getPriority(): int
    {
        return 200;
    }
}
