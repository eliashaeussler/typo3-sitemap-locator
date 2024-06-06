<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "sitemap_locator".
 *
 * Copyright (C) 2023-2024 Elias Häußler <elias@haeussler.dev>
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
 * TextFormatterTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\Formatter\TextFormatter::class)]
final class TextFormatterTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    use Tests\Functional\SiteTrait;

    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    protected bool $initializeDatabase = false;

    private Console\Output\BufferedOutput $output;
    private Core\Site\Entity\Site $site;
    private Tests\Unit\Fixtures\DummyRequestFactory $requestFactory;
    private Src\Command\Formatter\TextFormatter $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->output = new Console\Output\BufferedOutput();
        $this->site = $this->createSite();
        $this->requestFactory = new Tests\Unit\Fixtures\DummyRequestFactory();
        $this->subject = new Src\Command\Formatter\TextFormatter(
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
    public function formatSitemapsDisplaysWarningIfNoSitemapsWereFound(): void
    {
        $actual = $this->subject->formatSitemaps(
            $this->site,
            $this->site->getDefaultLanguage(),
            [],
        );

        self::assertFalse($actual);
        self::assertStringContainsString(
            'No XML sitemaps found for site "test-site" (1) and language "English" (0).',
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function formatSitemapsWritesSitemapsAsListing(): void
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

        $actual = $this->subject->formatSitemaps($this->site, $siteLanguage, $sitemaps);
        $output = $this->output->fetch();

        self::assertTrue($actual);
        self::assertStringContainsString(
            'XML sitemaps for site "test-site" (1) and language "English" (0)',
            $output,
        );
        self::assertStringContainsString(
            '* https://typo3-testing.local/sitemap-1.xml',
            $output,
        );
        self::assertStringContainsString(
            '* https://typo3-testing.local/sitemap-2.xml',
            $output,
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

        foreach ($expectedStates as $index => $expectedState) {
            $sitemapUrl = 'https://typo3-testing.local/sitemap-' . $index . '.xml';

            $sitemaps[] = new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($sitemapUrl),
                $this->site,
                $siteLanguage,
            );
        }

        $actual = $this->subject->formatSitemaps($this->site, $siteLanguage, $sitemaps, true);
        $output = $this->output->fetch();

        self::assertSame($expectedResult, $actual);

        foreach ($expectedStates as $index => $expectedState) {
            $expectedOutput = '* https://typo3-testing.local/sitemap-' . $index . '.xml (invalid)';

            if ($expectedState) {
                self::assertStringNotContainsString($expectedOutput, $output);
            } else {
                self::assertStringContainsString($expectedOutput, $output);
            }
        }
    }

    #[Framework\Attributes\Test]
    public function formatAllSitemapsDisplaysWarningIfNoSitemapsWereFound(): void
    {
        $actual = $this->subject->formatAllSitemaps(
            $this->site,
            [],
        );

        self::assertFalse($actual);
        self::assertStringContainsString(
            'No XML sitemaps found for site "test-site" (1).',
            $this->output->fetch(),
        );
    }

    #[Framework\Attributes\Test]
    public function formatAllSitemapsWritesSitemapsAsListingGroupedByLanguage(): void
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

        $actual = $this->subject->formatAllSitemaps($this->site, $sitemaps);
        $output = $this->output->fetch();

        self::assertTrue($actual);
        self::assertStringContainsString('XML sitemaps for site "test-site" (1)', $output);

        self::assertStringContainsString('Language "English" (0)', $output);
        self::assertStringContainsString(
            '* https://typo3-testing.local/sitemap-1.xml',
            $output,
        );
        self::assertStringContainsString(
            '* https://typo3-testing.local/sitemap-2.xml',
            $output,
        );

        self::assertStringContainsString('Language "German" (1)', $output);
        self::assertStringContainsString(
            '* https://typo3-testing.local/de/sitemap.xml',
            $output,
        );

        self::assertStringContainsString('Language "French" (2)', $output);
        self::assertStringContainsString(
            '* https://typo3-testing.local/fr/sitemap-1.xml',
            $output,
        );
        self::assertStringContainsString(
            '* https://typo3-testing.local/fr/sitemap-2.xml',
            $output,
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
        $siteLanguage = $this->site->getDefaultLanguage();

        $this->requestFactory->handler->append(...$responses);

        foreach ($expectedStates as $languageId => $expectedState) {
            $sitemapUrl = 'https://typo3-testing.local/sitemap-' . $languageId . '.xml';

            if (!isset($sitemaps[$languageId])) {
                $sitemaps[$languageId] = [];
            }

            $sitemaps[$languageId][] = new Src\Domain\Model\Sitemap(
                new Core\Http\Uri($sitemapUrl),
                $this->site,
                $siteLanguage,
            );
        }

        $actual = $this->subject->formatAllSitemaps($this->site, $sitemaps, true);
        $output = $this->output->fetch();

        self::assertSame($expectedResult, $actual);

        foreach ($expectedStates as $index => $expectedState) {
            $expectedOutput = '* https://typo3-testing.local/sitemap-' . $index . '.xml (invalid)';

            if ($expectedState) {
                self::assertStringNotContainsString($expectedOutput, $output);
            } else {
                self::assertStringContainsString($expectedOutput, $output);
            }
        }
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
