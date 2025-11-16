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
use EliasHaeussler\Typo3SitemapLocator\Http;
use EliasHaeussler\Typo3SitemapLocator\Utility;
use Psr\Http\Message;
use TYPO3\CMS\Core;

/**
 * RobotsTxtProvider
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final readonly class RobotsTxtProvider implements Provider
{
    private const SITEMAP_PATTERN = '#^Sitemap:\s*(?P<url>https?://[^\r\n]+)#im';

    public function __construct(
        private Http\Client\ClientFactory $clientFactory,
    ) {}

    public function get(
        Core\Site\Entity\Site $site,
        ?Core\Site\Entity\SiteLanguage $siteLanguage = null,
    ): array {
        $robotsTxtUri = Utility\HttpUtility::getSiteUrlWithPath($site, 'robots.txt', $siteLanguage);
        $robotsTxt = $this->fetchRobotsTxt($robotsTxtUri);

        // Early return if no robots.txt exists
        if ($robotsTxt === null || trim($robotsTxt) === '') {
            return [];
        }

        // Early return if no sitemap is specified in robots.txt
        if ((int)preg_match_all(self::SITEMAP_PATTERN, $robotsTxt, $matches) === 0) {
            return [];
        }

        return array_map(
            static fn(string $url) => new Domain\Model\Sitemap(
                new Core\Http\Uri($url),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
            ),
            $matches['url'],
        );
    }

    private function fetchRobotsTxt(Message\UriInterface $uri): ?string
    {
        try {
            $response = $this->clientFactory->get()->request('GET', $uri);

            return $response->getBody()->getContents();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * @codeCoverageIgnore
     */
    public static function getPriority(): int
    {
        return 100;
    }
}
