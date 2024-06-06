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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Command;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use PHPUnit\Framework;
use Symfony\Component\Console;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * LocateSitemapsCommandTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Command\LocateSitemapsCommand::class)]
final class LocateSitemapsCommandTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    use Tests\Functional\SiteTrait;

    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    private Core\Cache\Frontend\PhpFrontend $cache;
    private Core\Site\Entity\Site $site;
    private Tests\Functional\Fixtures\Classes\DummySiteFinder $siteFinder;
    private Console\Tester\CommandTester $commandTester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->get('cache.sitemap_locator');
        $this->site = $this->createSite();
        $this->siteFinder = new Tests\Functional\Fixtures\Classes\DummySiteFinder();
        $this->commandTester = new Console\Tester\CommandTester(
            new Src\Command\LocateSitemapsCommand(
                $this->get(Src\Sitemap\SitemapLocator::class),
                $this->siteFinder,
            ),
        );

        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/pages.csv');

        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(Core\Localization\LanguageServiceFactory::class)->createFromUserPreferences($backendUser);

        $this->cache->flush();
    }

    #[Framework\Attributes\Test]
    public function interactDoesNothingIfSiteArgumentIsProvided(): void
    {
        $this->commandTester->execute(
            [
                'site' => $this->site->getIdentifier(),
            ],
            [
                'interactive' => true,
            ],
        );

        self::assertStringNotContainsString('Please select an available site', $this->commandTester->getDisplay());
    }

    #[Framework\Attributes\Test]
    public function interactThrowsExceptionIfNoSitesAreAvailable(): void
    {
        $this->expectExceptionObject(new Src\Exception\NoSitesAreConfigured());

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);
    }

    #[Framework\Attributes\Test]
    public function interactAsksForAndAppliesSite(): void
    {
        $this->siteFinder->expectedSite = $this->site;
        $this->siteFinder->expectedSites = [
            $this->site,
        ];

        $this->commandTester->setInputs([
            'test-site',
            '',
        ]);

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);

        self::assertStringContainsString(
            'XML sitemap for site "test-site" (1)',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function interactAsksForAndAppliesAllSiteLanguages(): void
    {
        $this->siteFinder->expectedSite = $this->site;
        $this->siteFinder->expectedSites = [
            $this->site,
        ];

        $this->commandTester->setInputs([
            'test-site',
            'yes',
            'yes',
        ]);

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Language "English" (0)', $output);
        self::assertStringContainsString('Language "German" (1)', $output);
        self::assertStringContainsString('Language "French" (2)', $output);
    }

    #[Framework\Attributes\Test]
    public function interactThrowsExceptionIfInvalidLanguageIdIsPassed(): void
    {
        $this->siteFinder->expectedSite = $this->site;
        $this->siteFinder->expectedSites = [
            $this->site,
        ];

        $this->commandTester->setInputs([
            'test-site',
            '',
            'foo',
        ]);

        $this->expectException(Console\Exception\RuntimeException::class);
        $this->expectExceptionCode(1698771577);
        $this->expectExceptionMessage('Invalid language ID given.');

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);
    }

    #[Framework\Attributes\Test]
    public function interactThrowsExceptionIfUnsupportedLanguageIdIsPassed(): void
    {
        $this->siteFinder->expectedSite = $this->site;
        $this->siteFinder->expectedSites = [
            $this->site,
        ];

        $this->commandTester->setInputs([
            'test-site',
            '',
            '99',
        ]);

        $this->expectException(Console\Exception\RuntimeException::class);
        $this->expectExceptionCode(1522960188);
        $this->expectExceptionMessage('Language 99 does not exist on site test-site.');

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('interactAsksForAndAppliesLanguageDataProvider')]
    public function interactAsksForAndAppliesLanguage(string|int $selection): void
    {
        $this->siteFinder->expectedSite = $this->site;
        $this->siteFinder->expectedSites = [
            $this->site,
        ];

        $this->commandTester->setInputs([
            'test-site',
            '',
            $selection,
        ]);

        $this->commandTester->execute([], [
            'interactive' => true,
        ]);

        self::assertStringContainsString(
            'XML sitemap for site "test-site" (1) and language "French" (2)',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    #[Framework\Attributes\DataProvider('executeFailsIfGivenSiteDoesNotExistDataProvider')]
    public function executeFailsIfGivenSiteDoesNotExist(int|string $site): void
    {
        $this->commandTester->execute([
            'site' => $site,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Site with identifier or root page ID "' . $site . '" does not exist.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfAnErrorOccursWhileLocatingAllSitemaps(): void
    {
        $site = $this->createSite('/');

        $this->siteFinder->expectedSite = $site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--all' => true,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Unable to locate XML sitemaps due to the following exception: The given base URL "/" is not supported.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysSitemapsOfAllLanguagesIfAllOptionIsGiven(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--all' => true,
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());

        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('Language "English" (0)', $output);
        self::assertStringContainsString('* https://typo3-testing.local/sitemap.xml', $output);
        self::assertStringContainsString('Language "German" (1)', $output);
        self::assertStringContainsString('* https://typo3-testing.local/de/sitemap.xml', $output);
        self::assertStringContainsString('Language "French" (2)', $output);
        self::assertStringContainsString('* https://typo3-testing.local/fr/sitemap.xml', $output);
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfGivenSiteLanguageDoesNotExistInSite(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 99,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Site language "99" does not exist in site "test-site".',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeUsesDefaultSiteLanguageIfGivenLanguageIsNotNumeric(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 'foo',
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'XML sitemap for site "test-site" (1) and language "English" (0)',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeFailsIfAnErrorOccursWhileLocatingSitemaps(): void
    {
        $this->siteFinder->expectedSite = $this->createSite('/');

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 0,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            'Unable to locate XML sitemaps due to the following exception: The given base URL "/" is not supported.',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeDisplaysLocatedSitemapsOfGivenSiteAndLanguage(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 0,
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertStringContainsString(
            '* https://typo3-testing.local/sitemap.xml',
            $this->commandTester->getDisplay(),
        );
    }

    #[Framework\Attributes\Test]
    public function executeUsesJsonFormatterIfJsonOptionIsGiven(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 0,
            '--json' => true,
        ]);

        self::assertSame(Console\Command\Command::SUCCESS, $this->commandTester->getStatusCode());
        self::assertJson($this->commandTester->getDisplay());
    }

    #[Framework\Attributes\Test]
    public function executeValidatesLocatedSitemapsIfValidateOptionIsGiven(): void
    {
        $this->siteFinder->expectedSite = $this->site;

        $this->commandTester->execute([
            'site' => 'test-site',
            '--language' => 0,
            '--validate' => true,
        ]);

        self::assertSame(Console\Command\Command::FAILURE, $this->commandTester->getStatusCode());
        self::assertStringContainsString('(invalid)', $this->commandTester->getDisplay());
    }

    /**
     * @return \Generator<string, array{string|int}>
     */
    public static function interactAsksForAndAppliesLanguageDataProvider(): \Generator
    {
        yield 'language id as integer' => [2];
        yield 'language id as string' => ['2'];
        yield 'decorated language string' => ['French (2)'];
    }

    /**
     * @return \Generator<string, array{string|int}>
     */
    public static function executeFailsIfGivenSiteDoesNotExistDataProvider(): \Generator
    {
        yield 'site identifier' => ['foo'];
        yield 'root page id' => [99];
    }
}
