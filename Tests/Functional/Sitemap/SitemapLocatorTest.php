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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Sitemap;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use PHPUnit\Framework;
use Symfony\Component\EventDispatcher;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * SitemapLocatorTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Sitemap\SitemapLocator::class)]
final class SitemapLocatorTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    use Tests\Functional\ClientMockTrait;

    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    private Src\Cache\SitemapsCache $cache;
    private EventDispatcher\EventDispatcher $eventDispatcher;
    private Src\Http\Client\ClientFactory $clientFactory;
    private Src\Sitemap\SitemapLocator $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registerMockHandler();

        $this->cache = $this->get(Src\Cache\SitemapsCache::class);
        $this->clientFactory = $this->get(Src\Http\Client\ClientFactory::class);
        $this->subject = new Src\Sitemap\SitemapLocator(
            new Src\Http\Client\ClientFactory(
                new Core\Http\Client\GuzzleClientFactory(),
                $this->eventDispatcher,
            ),
            $this->cache,
            $this->eventDispatcher,
            [new Src\Sitemap\Provider\DefaultProvider()],
        );

        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/be_users.csv');

        /** @var Core\Cache\Frontend\PhpFrontend $cacheFrontend */
        $cacheFrontend = $this->get('cache.sitemap_locator');
        $cacheFrontend->flush();
    }

    #[Framework\Attributes\Test]
    public function constructorThrowsExceptionIfGivenProviderIsNotAnObject(): void
    {
        $providers = [
            'foo',
        ];

        $this->expectExceptionObject(
            new Src\Exception\ProviderIsNotSupported('foo'),
        );

        new Src\Sitemap\SitemapLocator($this->clientFactory, $this->cache, $this->eventDispatcher, $providers);
    }

    #[Framework\Attributes\Test]
    public function constructorThrowsExceptionIfGivenProviderIsNoValidObject(): void
    {
        $providers = [
            new \stdClass(),
        ];

        $this->expectExceptionObject(
            new Src\Exception\ProviderIsInvalid(new \stdClass()),
        );

        new Src\Sitemap\SitemapLocator($this->clientFactory, $this->cache, $this->eventDispatcher, $providers);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('locateBySiteReturnsCachedSitemapDataProvider')]
    public function locateBySiteReturnsCachedSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage, string $expectedUrl): void
    {
        $site = self::getSite([]);
        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($expectedUrl),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
                true,
            ),
        ];

        $this->cache->set($sitemaps);

        self::assertEquals($sitemaps, $this->subject->locateBySite($site, $siteLanguage));
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfiguredDataProvider')]
    public function locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfigured(?Core\Site\Entity\SiteLanguage $siteLanguage): void
    {
        $site = self::getSite([]);

        $this->expectExceptionObject(
            new Src\Exception\BaseUrlIsNotSupported(''),
        );

        $this->subject->locateBySite($site, $siteLanguage);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('locateBySiteThrowsExceptionIfProvidersCannotResolveSitemapDataProvider')]
    public function locateBySiteThrowsExceptionIfProvidersCannotResolveSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage): void
    {
        $site = self::getSite();
        $subject = new Src\Sitemap\SitemapLocator(
            $this->clientFactory,
            $this->cache,
            $this->eventDispatcher,
            [],
        );

        $this->expectExceptionObject(
            new Src\Exception\SitemapIsMissing($site),
        );

        $subject->locateBySite($site, $siteLanguage);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('locateBySiteReturnsLocatedSitemapDataProvider')]
    public function locateBySiteReturnsLocatedSitemap(?Core\Site\Entity\SiteLanguage $siteLanguage, string $expectedUrl): void
    {
        $site = self::getSite();

        $uncachedSitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($expectedUrl),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
            ),
        ];

        $cachedSitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($expectedUrl),
                $site,
                $siteLanguage ?? $site->getDefaultLanguage(),
                true,
            ),
        ];

        self::assertSame([], $this->cache->get($site, $siteLanguage));
        self::assertEquals($uncachedSitemaps, $this->subject->locateBySite($site, $siteLanguage));
        self::assertEquals($cachedSitemaps, $this->cache->get($site, $siteLanguage));
    }

    #[Framework\Attributes\Test]
    public function locateBySiteDispatchesEventWithLocatedSitemap(): void
    {
        $site = self::getSite();

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/sitemap.xml'),
                $site,
                $site->getDefaultLanguage(),
            ),
        ];

        $this->eventDispatcher->addListener(
            Src\Event\SitemapsLocatedEvent::class,
            static function (Src\Event\SitemapsLocatedEvent $event) use ($site, $expected): void {
                self::assertSame($site, $event->getSite());
                self::assertNull($event->getSiteLanguage());
                self::assertEquals($expected, $event->getSitemaps());

                // Reset sitemaps to test event behavior
                $event->setSitemaps([]);
            },
        );

        self::assertSame([], $this->subject->locateBySite($site));
    }

    #[Framework\Attributes\Test]
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

    #[Framework\Attributes\Test]
    public function locateAllBySiteExcludesInaccessibleLanguages(): void
    {
        $GLOBALS['BE_USER'] = $this->setUpBackendUser(2);

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
                true,
            ),
        ];

        $this->cache->set($sitemaps);

        self::assertEquals([1 => $sitemaps], $this->subject->locateAllBySite($site));
    }

    #[Framework\Attributes\Test]
    public function isValidSitemapDispatchesEventWithValidityResult(): void
    {
        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/sitemap.xml'),
            $site,
            $site->getDefaultLanguage(),
        );

        $this->createMockHandler()->append(
            new Core\Http\Response(),
        );

        $this->eventDispatcher->addListener(
            Src\Event\SitemapValidatedEvent::class,
            static function (Src\Event\SitemapValidatedEvent $event) use ($sitemap): void {
                self::assertSame($sitemap, $event->getSitemap());
                self::assertTrue($event->isValid());

                // Change validity result to test event behavior
                $event->setValid(false);
            },
        );

        self::assertFalse($this->subject->isValidSitemap($sitemap));
    }

    #[Framework\Attributes\Test]
    public function isValidSitemapReturnsFalseOnInaccessibleSitemap(): void
    {
        $this->createMockHandler()->append(
            new \Exception(),
        );

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertFalse($this->subject->isValidSitemap($sitemap));
    }

    #[Framework\Attributes\Test]
    public function isValidSitemapReturnsFalseOnFailedRequest(): void
    {
        $this->createMockHandler()->append(
            new Core\Http\Response(null, 404),
        );

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertFalse($this->subject->isValidSitemap($sitemap));
    }

    #[Framework\Attributes\Test]
    public function isValidSitemapReturnsTrueOnSuccessfulRequest(): void
    {
        $this->createMockHandler()->append(
            new Core\Http\Response(),
        );

        $site = self::getSite();
        $sitemap = new Src\Domain\Model\Sitemap(
            new Core\Http\Uri('https://www.example.com/'),
            $site,
            $site->getDefaultLanguage(),
        );

        self::assertTrue($this->subject->isValidSitemap($sitemap));
    }

    /**
     * @return \Generator<string, array{Core\Site\Entity\SiteLanguage|null, string}>
     */
    public static function locateBySiteReturnsCachedSitemapDataProvider(): \Generator
    {
        yield 'no site language' => [null, 'https://www.example.com/sitemap.xml'];
        yield 'site language' => [self::getSiteLanguage(), 'https://www.example.com/sitemap.xml'];
    }

    /**
     * @return \Generator<string, array{Core\Site\Entity\SiteLanguage|null}>
     */
    public static function locateBySiteThrowsExceptionIfSiteBaseHasNoHostnameConfiguredDataProvider(): \Generator
    {
        yield 'no site language' => [null];
        yield 'site language' => [self::getSiteLanguage('')];
    }

    /**
     * @return \Generator<string, array{Core\Site\Entity\SiteLanguage|null}>
     */
    public static function locateBySiteThrowsExceptionIfProvidersCannotResolveSitemapDataProvider(): \Generator
    {
        yield 'no site language' => [null];
        yield 'site language' => [self::getSiteLanguage()];
    }

    /**
     * @return \Generator<string, array{Core\Site\Entity\SiteLanguage|null, string}>
     */
    public static function locateBySiteReturnsLocatedSitemapDataProvider(): \Generator
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
