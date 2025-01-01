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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Command\Formatter;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use PHPUnit\Framework;
use Psr\EventDispatcher;
use Psr\Http\Message;
use Symfony\Component\Console;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * JsonFormatterTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\Formatter\JsonFormatter::class)]
final class JsonFormatterTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    use Tests\Functional\SiteTrait;

    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    protected bool $initializeDatabase = false;

    private Console\Output\BufferedOutput $output;
    private Core\Site\Entity\Site $site;
    private Tests\Unit\Fixtures\DummyRequestFactory $requestFactory;
    private Src\Command\Formatter\JsonFormatter $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new Console\Output\BufferedOutput();
        $this->site = $this->createSite();
        $this->requestFactory = new Tests\Unit\Fixtures\DummyRequestFactory();
        $this->subject = new Src\Command\Formatter\JsonFormatter(
            new Console\Style\SymfonyStyle(
                new Console\Input\StringInput(''),
                $this->output,
            ),
            new Src\Sitemap\SitemapLocator(
                $this->requestFactory,
                $this->get(Src\Cache\SitemapsCache::class),
                $this->get(EventDispatcher\EventDispatcherInterface::class),
                [
                    new Src\Sitemap\Provider\DefaultProvider(),
                ],
            ),
        );
    }

    #[Framework\Attributes\Test]
    public function formatSitemapsReturnsFalseIfNoSitemapsWereFound(): void
    {
        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'siteLanguage' => [
                'languageId' => 0,
                'title' => 'English',
            ],
            'sitemaps' => [],
        ];

        $actual = $this->subject->formatSitemaps(
            $this->site,
            $this->site->getDefaultLanguage(),
            [],
        );

        self::assertFalse($actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function formatSitemapsWritesSitemapsAsJsonArray(): void
    {
        $siteLanguage = $this->site->getDefaultLanguage();
        $sitemaps = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://typo3-testing.local/sitemap-1.xml'),
                $this->site,
                $siteLanguage,
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://typo3-testing.local/sitemap-2.xml'),
                $this->site,
                $siteLanguage,
            ),
        ];

        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'siteLanguage' => [
                'languageId' => 0,
                'title' => 'English',
            ],
            'sitemaps' => [
                'https://typo3-testing.local/sitemap-1.xml',
                'https://typo3-testing.local/sitemap-2.xml',
            ],
        ];

        $actual = $this->subject->formatSitemaps($this->site, $siteLanguage, $sitemaps);

        self::assertTrue($actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    /**
     * @param list<Message\ResponseInterface> $responses
     * @param list<bool> $expectedStates
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('formatSitemapsIncludesValidityStateOfLocatedSitemapsDataProvider')]
    public function formatSitemapsIncludesValidityStateOfLocatedSitemaps(
        array $responses,
        array $expectedStates,
        bool $expectedResult,
    ): void {
        $sitemaps = [];
        $siteLanguage = $this->site->getDefaultLanguage();

        $this->requestFactory->handler->append(...$responses);

        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'siteLanguage' => [
                'languageId' => 0,
                'title' => 'English',
            ],
            'sitemaps' => [],
        ];

        foreach ($expectedStates as $index => $expectedState) {
            $sitemapUrl = 'https://typo3-testing.local/sitemap-' . $index . '.xml';

            $sitemaps[] = new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($sitemapUrl),
                $this->site,
                $siteLanguage,
            );
            $expected['sitemaps'][] = [
                'url' => $sitemapUrl,
                'valid' => $expectedState,
            ];
        }

        $actual = $this->subject->formatSitemaps($this->site, $siteLanguage, $sitemaps, true);

        self::assertSame($expectedResult, $actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function formatAllSitemapsReturnsFalseIfNoSitemapsWereFound(): void
    {
        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'sitemaps' => [],
        ];

        $actual = $this->subject->formatAllSitemaps(
            $this->site,
            [],
        );

        self::assertFalse($actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function formatAllSitemapsWritesSitemapsAsJsonArrayGroupedByLanguage(): void
    {
        $sitemaps = [
            0 => [
                new Src\Domain\Model\Sitemap(
                    new Core\Http\Uri('https://typo3-testing.local/sitemap-1.xml'),
                    $this->site,
                    $this->site->getDefaultLanguage(),
                ),
                new Src\Domain\Model\Sitemap(
                    new Core\Http\Uri('https://typo3-testing.local/sitemap-2.xml'),
                    $this->site,
                    $this->site->getDefaultLanguage(),
                ),
            ],
            1 => [
                new Src\Domain\Model\Sitemap(
                    new Core\Http\Uri('https://typo3-testing.local/de/sitemap.xml'),
                    $this->site,
                    $this->site->getLanguageById(1),
                ),
            ],
            2 => [
                new Src\Domain\Model\Sitemap(
                    new Core\Http\Uri('https://typo3-testing.local/fr/sitemap-1.xml'),
                    $this->site,
                    $this->site->getLanguageById(2),
                ),
                new Src\Domain\Model\Sitemap(
                    new Core\Http\Uri('https://typo3-testing.local/fr/sitemap-2.xml'),
                    $this->site,
                    $this->site->getLanguageById(2),
                ),
            ],
        ];

        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'sitemaps' => [
                0 => [
                    'siteLanguage' => [
                        'languageId' => 0,
                        'title' => 'English',
                    ],
                    'sitemaps' => [
                        'https://typo3-testing.local/sitemap-1.xml',
                        'https://typo3-testing.local/sitemap-2.xml',
                    ],
                ],
                1 => [
                    'siteLanguage' => [
                        'languageId' => 1,
                        'title' => 'German',
                    ],
                    'sitemaps' => [
                        'https://typo3-testing.local/de/sitemap.xml',
                    ],
                ],
                2 => [
                    'siteLanguage' => [
                        'languageId' => 2,
                        'title' => 'French',
                    ],
                    'sitemaps' => [
                        'https://typo3-testing.local/fr/sitemap-1.xml',
                        'https://typo3-testing.local/fr/sitemap-2.xml',
                    ],
                ],
            ],
        ];

        $actual = $this->subject->formatAllSitemaps($this->site, $sitemaps);

        self::assertTrue($actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    /**
     * @param list<Message\ResponseInterface> $responses
     * @param list<bool> $expectedStates
     */
    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('formatAllSitemapsIncludesValidityStateOfLocatedSitemapsDataProvider')]
    public function formatAllSitemapsIncludesValidityStateOfLocatedSitemaps(
        array $responses,
        array $expectedStates,
        bool $expectedResult,
    ): void {
        $sitemaps = [];

        $this->requestFactory->handler->append(...$responses);

        $expected = [
            'site' => [
                'identifier' => 'test-site',
                'rootPageId' => 1,
            ],
            'sitemaps' => [],
        ];

        foreach ($expectedStates as $languageId => $expectedState) {
            $siteLanguage = $this->site->getLanguageById($languageId);
            $sitemapUrl = 'https://typo3-testing.local/sitemap-' . $languageId . '.xml';

            if (!isset($sitemaps[$languageId])) {
                $sitemaps[$languageId] = [];
            }

            $sitemaps[$languageId][] = new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($sitemapUrl),
                $this->site,
                $siteLanguage,
            );

            if (!isset($expected['sitemaps'][$languageId])) {
                $expected['sitemaps'][$languageId] = [
                    'siteLanguage' => [
                        'languageId' => $languageId,
                        'title' => $siteLanguage->getTitle(),
                    ],
                    'sitemaps' => [],
                ];
            }

            $expected['sitemaps'][$languageId]['sitemaps'][] = [
                'url' => $sitemapUrl,
                'valid' => $expectedState,
            ];
        }

        $actual = $this->subject->formatAllSitemaps($this->site, $sitemaps, true);

        self::assertSame($expectedResult, $actual);
        self::assertJsonStringEqualsJsonString(
            json_encode($expected, JSON_THROW_ON_ERROR),
            $this->output->fetch(),
        );
    }

    /**
     * @return \Generator<string, array{list<Message\ResponseInterface>, list<bool>, bool}>
     */
    public static function formatSitemapsIncludesValidityStateOfLocatedSitemapsDataProvider(): \Generator
    {
        $validResponse = new Core\Http\Response();
        $invalidResponse = new Core\Http\Response(statusCode: 404);

        yield 'no sitemaps' => [[], [], false];
        yield 'one valid sitemap' => [[$validResponse], [true], true];
        yield 'one invalid sitemap' => [[$invalidResponse], [false], false];
        yield 'multiple valid sitemaps' => [[$validResponse, $validResponse, $validResponse], [true, true, true], true];
        yield 'mixed validity states' => [[$validResponse, $invalidResponse, $validResponse], [true, false, true], false];
    }

    /**
     * @return \Generator<string, array{list<Message\ResponseInterface>, list<bool>, bool}>
     */
    public static function formatAllSitemapsIncludesValidityStateOfLocatedSitemapsDataProvider(): \Generator
    {
        $validResponse = new Core\Http\Response();
        $invalidResponse = new Core\Http\Response(statusCode: 404);

        yield 'no sitemaps' => [[], [], false];
        yield 'one valid sitemap' => [[$validResponse], [true], true];
        yield 'one invalid sitemap' => [[$invalidResponse], [false], false];
        yield 'multiple valid sitemaps' => [[$validResponse, $validResponse, $validResponse], [true, true, true], true];
        yield 'mixed validity states' => [[$validResponse, $invalidResponse, $validResponse], [true, false, true], false];
    }
}
