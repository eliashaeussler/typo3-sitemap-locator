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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Cache;

use EliasHaeussler\Typo3SitemapLocator as Src;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * SitemapsCacheTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @covers \EliasHaeussler\Typo3SitemapLocator\Cache\SitemapsCache
 */
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

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfCacheIsMissing(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);

        self::assertFalse($this->cache->has('baz'));
        self::assertSame([], $this->subject->get($site));
    }

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfCacheDataIsInvalid(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);

        $this->cache->set('baz', 'return "foo";');

        self::assertSame([], $this->subject->get($site));
    }

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfCacheOfGivenSiteIsEmpty(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);

        $this->cache->set('baz', 'return [];');

        self::assertSame([], $this->subject->get($site));
    }

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfCacheOfGivenSiteLanguageIsEmpty(): void
    {
        $site = new Core\Site\Entity\Site('baz', 1, []);

        $this->cache->set(
            'baz',
            sprintf(
                'return %s;',
                var_export(
                    [
                        1 => [
                            'https://www.example.com/baz',
                            'https://www.example.com/bar',
                        ],
                    ],
                    true,
                ),
            ),
        );

        self::assertSame([], $this->subject->get($site));
    }

    /**
     * @test
     */
    public function getReturnsCachedSitemapsForDefaultLanguage(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);

        $this->cache->set(
            'foo',
            sprintf(
                'return %s;',
                var_export(
                    [
                        0 => [
                            'https://www.example.com/baz',
                            'https://www.example.com/bar',
                        ],
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
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $site->getDefaultLanguage(),
            ),
        ];

        self::assertEquals($expected, $this->subject->get($site));
        self::assertEquals($expected, $this->subject->get($site, $site->getDefaultLanguage()));
    }

    /**
     * @test
     */
    public function getReturnsCachedSitemapForGivenLanguage(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $siteLanguage = new Core\Site\Entity\SiteLanguage(1, 'de_DE.UTF-8', new Core\Http\Uri('https://example.com'), []);

        $this->cache->set(
            'foo',
            sprintf(
                'return %s;',
                var_export(
                    [
                        1 => [
                            'https://www.example.com/baz',
                            'https://www.example.com/bar',
                        ],
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
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar'),
                $site,
                $siteLanguage,
            ),
        ];

        self::assertEquals($expected, $this->subject->get($site, $siteLanguage));
    }

    /**
     * @test
     */
    public function setInitializesCacheIfCacheIsMissing(): void
    {
        self::assertFalse($this->cache->has('foo'));

        $site = new Core\Site\Entity\Site('foo', 1, []);
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

        self::assertEquals($sitemaps, $this->subject->get($site, $site->getDefaultLanguage()));
        self::assertTrue($this->cache->has('foo'));
    }

    /**
     * @test
     */
    public function setStoresGivenSitemapInCache(): void
    {
        self::assertFalse($this->cache->has('foo'));

        $site = new Core\Site\Entity\Site('foo', 1, []);
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

        self::assertEquals($sitemaps, $this->subject->get($site, $site->getDefaultLanguage()));
        self::assertTrue($this->cache->has('foo'));
    }

    /**
     * @test
     */
    public function removeInitializesCacheIfCacheIsMissing(): void
    {
        self::assertFalse($this->cache->has('foo'));

        $site = new Core\Site\Entity\Site('foo', 1, []);

        $this->subject->remove($site);

        self::assertFalse($this->cache->has('foo'));
    }

    /**
     * @test
     */
    public function removeRemovesSitemapsOfGivenSiteFromCache(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
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

        self::assertTrue($this->cache->has('foo'));

        $this->subject->remove($site);

        self::assertFalse($this->cache->has('foo'));
    }

    /**
     * @test
     */
    public function removeRemovesSitemapsOfGivenSiteAndSiteLanguageFromCache(): void
    {
        $site = new Core\Site\Entity\Site('foo', 1, []);
        $siteLanguage = new Core\Site\Entity\SiteLanguage(1, 'de_DE.UTF-8', new Core\Http\Uri('https://example.com'), []);

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

        self::assertTrue($this->cache->has('foo'));

        $this->subject->remove($site, $siteLanguage);

        self::assertCount(1, $this->cache->require('foo'));
    }
}
