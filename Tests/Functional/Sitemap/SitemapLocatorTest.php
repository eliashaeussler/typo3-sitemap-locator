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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Sitemap;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use Exception;
use Generator;
use stdClass;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * SitemapLocatorTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @covers \EliasHaeussler\Typo3SitemapLocator\Sitemap\SitemapLocator
 */
final class SitemapLocatorTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    private Src\Cache\SitemapsCache $cache;
    private Tests\Unit\Fixtures\DummyRequestFactory $requestFactory;
    private Src\Sitemap\SitemapLocator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->get(Src\Cache\SitemapsCache::class);
        $this->requestFactory = new Tests\Unit\Fixtures\DummyRequestFactory();
        $this->subject = new Src\Sitemap\SitemapLocator(
            $this->requestFactory,
            $this->cache,
            [new Src\Sitemap\Provider\DefaultProvider()],
        );

        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/be_users.csv');

        /** @var Core\Cache\Frontend\PhpFrontend $cacheFrontend */
        $cacheFrontend = $this->get('cache.sitemap_locator');
        $cacheFrontend->flush();
    }

    /**
     * @test
     */
    public function constructorThrowsExceptionIfGivenProviderIsNotAnObject(): void
    {
        $providers = [
            'foo',
        ];

        $this->expectExceptionObject(
            new Src\Exception\ProviderIsNotSupported('foo'),
        );

        new Src\Sitemap\SitemapLocator($this->requestFactory, $this->cache, $providers);
    }

    /**
     * @test
     */
    public function constructorThrowsExceptionIfGivenProviderIsNoValidObject(): void
    {
        $providers = [
            new stdClass(),
        ];

        $this->expectExceptionObject(
            new Src\Exception\ProviderIsInvalid(new stdClass()),
        );

        new Src\Sitemap\SitemapLocator($this->requestFactory, $this->cache, $providers);
    }

    /**
     * @test
     * @dataProvider locateBySiteReturnsCachedSitemapDataProvider
     */
    public function locateBySiteReturnsCachedSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage, string $expectedUrl): void
    {
        $site = self::getSite([]);
        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($expectedUrl),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
            ),
        ];

        $this->cache->set($sitemaps);

        self::assertEquals($sitemaps, $this->subject->locateBySite($site, $siteLanguage));
    }

    /**
     * @test
     * @dataProvider locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfiguredDataProvider
     */
    public function locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfigured(?Core\Site\Entity\SiteLanguage $siteLanguage): void
    {
        $site = self::getSite([]);

        $this->expectExceptionObject(
            new Src\Exception\BaseUrlIsNotSupported(''),
        );

        $this->subject->locateBySite($site, $siteLanguage);
    }

    /**
     * @test
     * @dataProvider locateBySiteThrowsExceptionIfProvidersCannotResolveSitemapDataProvider
     */
    public function locateBySiteThrowsExceptionIfProvidersCannotResolveSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage): void
    {
        $site = self::getSite();
        $subject = new Src\Sitemap\SitemapLocator(
            $this->requestFactory,
            $this->cache,
            []
        );

        $this->expectExceptionObject(
            new Src\Exception\SitemapIsMissing($site),
        );

        $subject->locateBySite($site, $siteLanguage);
    }

    /**
     * @test
     * @dataProvider locateBySiteReturnsLocatedSitemapDataProvider
     */
    public function locateBySiteReturnsLocatedSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage, string $expectedUrl): void
    {
        $site = self::getSite();
        $sitemap = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($expectedUrl),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
            ),
        ];

        self::assertSame([], $this->cache->get($site, $siteLanguage));
        self::assertEquals($sitemap, $this->subject->locateBySite($site, $siteLanguage));
        self::assertEquals($sitemap, $this->cache->get($site, $siteLanguage));
    }

    /**
     * @test
     */
    public function locateAllBySiteExcludesDisabledLanguages(): void
    {
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(1);

        $site = self::getSite([
            'base' => 'https://www.example.com/',
            'languages' => [
                0 => [
                    'languageId' => 0,
                    'title' => 'Default',
                    'navigationTitle' => '',
                    'typo3Language' => 'default',
                    'flag' => 'us',
                    'locale' => 'en_US.UTF-8',
                    'iso-639-1' => 'en',
                    'hreflang' => 'en-US',
                    'direction' => '',
                    'enabled' => false,
                ],
                1 => array_merge(
                    self::getSiteLanguage()->toArray(),
                    ['enabled' => false]
                ),
            ],
        ]);

        self::assertSame([], $this->subject->locateAllBySite($site));
    }

    /**
     * @test
     */
    public function locateAllBySiteExcludesInaccessibleLanguages(): void
    {
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(1);

        $site = self::getSite([
            'base' => 'https://www.example.com/',
            'languages' => [
                0 => [
                    'languageId' => 0,
                    'title' => 'Default',
                    'navigationTitle' => '',
                    'typo3Language' => 'default',
                    'flag' => 'us',
                    'locale' => 'en_US.UTF-8',
                    'iso-639-1' => 'en',
                    'hreflang' => 'en-US',
                    'direction' => '',
                ],
                1 => self::getSiteLanguage()->toArray(),
            ],
        ]);
        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/'),
                $site,
                $site->getLanguageById(1),
            ),
        ];

        $this->cache->set($sitemaps);

        self::assertEquals([1 => $sitemaps], $this->subject->locateAllBySite($site));
    }

    /**
     * @test
     */
    public function sitemapExistsReturnsFalseOnInaccessibleSitemap(): void
    {
        $this->requestFactory->exception = new Exception();

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertFalse($this->subject->sitemapExists($sitemap));
    }

    /**
     * @test
     */
    public function sitemapExistsReturnsFalseOnFailedRequest(): void
    {
        $this->requestFactory->response = new Core\Http\Response(null, 404);

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertFalse($this->subject->sitemapExists($sitemap));
    }

    /**
     * @test
     */
    public function sitemapExistsReturnsTrueOnSuccessfulRequest(): void
    {
        $this->requestFactory->response = new Core\Http\Response();

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertTrue($this->subject->sitemapExists($sitemap));
    }

    /**
     * @return Generator<string, array{Core\Site\Entity\SiteLanguage|null, string}>
     */
    public static function locateBySiteReturnsCachedSitemapDataProvider(): Generator
    {
        yield 'no site language' => [null, 'https://www.example.com/sitemap.xml'];
        yield 'site language' => [self::getSiteLanguage(), 'https://www.example.com/sitemap.xml'];
    }

    /**
     * @return Generator<string, array{Core\Site\Entity\SiteLanguage|null}>
     */
    public static function locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfiguredDataProvider(): Generator
    {
        yield 'no site language' => [null];
        yield 'site language' => [self::getSiteLanguage('')];
    }

    /**
     * @return Generator<string, array{Core\Site\Entity\SiteLanguage|null}>
     */
    public static function locateBySiteThrowsExceptionIfProvidersCannotResolveSitemapDataProvider(): Generator
    {
        yield 'no site language' => [null];
        yield 'site language' => [self::getSiteLanguage()];
    }

    /**
     * @return Generator<string, array{Core\Site\Entity\SiteLanguage|null, string}>
     */
    public static function locateBySiteReturnsLocatedSitemapDataProvider(): Generator
    {
        yield 'no site language' => [null, 'https://www.example.com/sitemap.xml'];
        yield 'site language' => [self::getSiteLanguage(), 'https://www.example.com/de/sitemap.xml'];
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private static function getSite(array $configuration = ['base' => 'https://www.example.com/']): Core\Site\Entity\Site
    {
        return new Core\Site\Entity\Site('foo', 1, $configuration);
    }

    private static function getSiteLanguage(string $baseUrl = 'https://www.example.com/de/'): Core\Site\Entity\SiteLanguage
    {
        return new Core\Site\Entity\SiteLanguage(1, 'de_DE.UTF-8', new Core\Http\Uri($baseUrl), []);
    }
}
