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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Cache;

use EliasHaeussler\Typo3SitemapLocator as Src;
use PHPUnit\Framework;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * SitemapsCacheTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Cache\SitemapsCache::class)]
final class SitemapsCacheTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    protected bool $initializeDatabase = false;

    private Core\Cache\Frontend\PhpFrontend $cache;
    private Src\Cache\SitemapsCache $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->get('cache.sitemap_locator');
        $this->subject = new Src\Cache\SitemapsCache($this->cache);

        $this->cache->flush();
    }

    #[Framework\Attributes\Test]
    public function getReturnsEmptyArrayIfCacheIsMissing(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        self::assertFalse($this->cache->has($cacheIdentifier));
        self::assertSame([], $this->subject->get($site));
    }

    #[Framework\Attributes\Test]
    public function getReturnsEmptyArrayIfCacheDataIsInvalid(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        $this->cache->set($cacheIdentifier, 'return "foo";');

        self::assertTrue($this->cache->has($cacheIdentifier));
        self::assertSame([], $this->subject->get($site));
    }

    #[Framework\Attributes\Test]
    public function getReturnsEmptyArrayIfCacheOfGivenSiteIsEmpty(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        $this->cache->set($cacheIdentifier, 'return [];');

        self::assertTrue($this->cache->has($cacheIdentifier));
        self::assertSame([], $this->subject->get($site));
    }

    #[Framework\Attributes\Test]
    public function getReturnsCachedSitemapsForDefaultLanguage(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        $this->cache->set(
            $cacheIdentifier,
            sprintf(
                'return %s;',
                var_export(
                    [
                        'https://www.example.com/baz',
                        'https://www.example.com/bar',
                    ],
                    true,
                ),
            ),
        );

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
        ];

        self::assertEquals($expected, $this->subject->get($site));
        self::assertEquals($expected, $this->subject->get($site, $site->getDefaultLanguage()));
    }

    #[Framework\Attributes\Test]
    public function getReturnsCachedSitemapForGivenLanguage(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $siteLanguage = new Core\Site\Entity\SiteLanguage(1, 'de_DE.UTF-8', new Core\Http\Uri('https://example.com'), []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site, $siteLanguage);

        $this->cache->set(
            $cacheIdentifier,
            sprintf(
                'return %s;',
                var_export(
                    [
                        'https://www.example.com/baz',
                        'https://www.example.com/bar',
                    ],
                    true,
                ),
            ),
        );

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $siteLanguage,
                true,
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $siteLanguage,
                true,
            ),
        ];

        self::assertEquals($expected, $this->subject->get($site, $siteLanguage));
    }

    #[Framework\Attributes\Test]
    public function setInitializesCacheIfCacheIsMissing(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        self::assertFalse($this->cache->has($cacheIdentifier));

        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
            ),
        ];

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
        ];

        $this->subject->set($sitemaps);

        self::assertEquals($expected, $this->subject->get($site, $site->getDefaultLanguage()));
        self::assertTrue($this->cache->has($cacheIdentifier));
    }

    #[Framework\Attributes\Test]
    public function setStoresGivenSitemapsInCache(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        self::assertFalse($this->cache->has($cacheIdentifier));

        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
            ),
        ];

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
                true,
            ),
        ];

        $this->subject->set($sitemaps);

        self::assertEquals($expected, $this->subject->get($site, $site->getDefaultLanguage()));
        self::assertTrue($this->cache->has($cacheIdentifier));
    }

    #[Framework\Attributes\Test]
    public function removeRemovesSitemapsOfGivenSiteFromCache(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site);

        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz'),
                $site,
                $site->getDefaultLanguage(),
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
            ),
        ];

        $this->subject->set($sitemaps);

        self::assertTrue($this->cache->has($cacheIdentifier));

        $this->subject->remove($site);

        self::assertFalse($this->cache->has($cacheIdentifier));
    }

    #[Framework\Attributes\Test]
    public function removeRemovesSitemapsOfGivenSiteAndSiteLanguageFromCache(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $siteLanguage = new Core\Site\Entity\SiteLanguage(1, 'de_DE.UTF-8', new Core\Http\Uri('https://example.com'), []);
        $cacheIdentifier = $this->calculateCacheIdentifier($site, $siteLanguage);

        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/'),
                $site,
                $site->getDefaultLanguage(),
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/foo'),
                $site,
                $siteLanguage,
            ),
        ];

        $this->subject->set($sitemaps);

        self::assertTrue($this->cache->has($cacheIdentifier));

        $this->subject->remove($site, $siteLanguage);

        self::assertFalse($this->cache->has($cacheIdentifier));
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
