<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "sitemap_locator".
 *
 * Copyright (C) 2023-2026 Elias Häußler <elias@haeussler.dev>
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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\EventListener;

use EliasHaeussler\PHPUnitAttributes;
use EliasHaeussler\Typo3SitemapLocator as Src;
use PHPUnit\Framework;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * SiteConfigurationListenerTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\EventListener\SiteConfigurationListener::class)]
#[PHPUnitAttributes\Attribute\RequiresPackage('typo3/cms-core', '>= 12')] // @todo Remove once support for TYPO3 v11 is dropped
final class SiteConfigurationListenerTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    protected bool $initializeDatabase = false;

    private Core\Cache\Frontend\PhpFrontend $cache;
    private Core\Site\SiteFinder&Framework\MockObject\MockObject $siteFinder;
    private Src\EventListener\SiteConfigurationListener $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->get('cache.sitemap_locator');
        $this->siteFinder = $this->createMock(Core\Site\SiteFinder::class);
        $this->subject = new Src\EventListener\SiteConfigurationListener(
            $this->get(Src\Cache\SitemapsCache::class),
            $this->siteFinder,
        );

        $this->cache->set(
            'foo_0_42099b4af021e53fd8fd4e056c2568d7c2e3ffa8',
            \sprintf(
                'return %s;',
                \var_export(
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

        $this->cache->set(
            'foo_1_1c526ad1bac1b1cce895c61f4a427529c672099d',
            \sprintf(
                'return %s;',
                \var_export(
                    [
                        0 => [
                            'https://www.example.com/de/baz',
                            'https://www.example.com/de/bar',
                        ],
                    ],
                    true,
                ),
            ),
        );
    }

    #[Framework\Attributes\Test]
    public function invokeDoesNothingIfGivenSiteDoesNotExist(): void
    {
        $this->siteFinder->method('getSiteByIdentifier')->willThrowException(
            new Core\Exception\SiteNotFoundException('No site found for identifier foo', 1521716628)
        );

        $event = new Core\Configuration\Event\SiteConfigurationBeforeWriteEvent('foo', []);

        ($this->subject)($event);

        self::assertTrue($this->cache->has('foo_0_42099b4af021e53fd8fd4e056c2568d7c2e3ffa8'));
        self::assertTrue($this->cache->has('foo_1_1c526ad1bac1b1cce895c61f4a427529c672099d'));
    }

    #[Framework\Attributes\Test]
    public function invokeRemovesSitemapsCache(): void
    {
        $event = new Core\Configuration\Event\SiteConfigurationBeforeWriteEvent('foo', []);
        $site = new Core\Site\Entity\Site('foo', 1, [
            'languages' => [
                0 => [
                    'base' => '/',
                    'languageId' => 0,
                    'title' => 'Default',
                    'navigationTitle' => '',
                    'flag' => 'us',
                    'locale' => 'en_US.UTF-8',
                ],
                1 => [
                    'base' => '/de/',
                    'languageId' => 1,
                    'title' => 'German',
                    'navigationTitle' => '',
                    'flag' => 'de',
                    'locale' => 'de_DE.UTF-8',
                ],
            ],
        ]);

        $this->siteFinder->method('getSiteByIdentifier')->willReturn($site);

        ($this->subject)($event);

        self::assertFalse($this->cache->has('foo_0_42099b4af021e53fd8fd4e056c2568d7c2e3ffa8'));
        self::assertFalse($this->cache->has('foo_0_1c526ad1bac1b1cce895c61f4a427529c672099d'));
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->cache->flush();
    }
}
