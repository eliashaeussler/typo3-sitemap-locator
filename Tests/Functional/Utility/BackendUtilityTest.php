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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Utility;

use EliasHaeussler\Typo3SitemapLocator as Src;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\TestingFramework;

/**
 * BackendUtilityTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @covers \EliasHaeussler\Typo3SitemapLocator\Utility\BackendUtility
 */
final class BackendUtilityTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/be_users.csv');
        $this->importCSVDataSet(\dirname(__DIR__) . '/Fixtures/Database/pages.csv');

        $this->setUpBackendUser(1);

        Bootstrap::initializeLanguageObject();
    }

    /**
     * @test
     */
    public function getBackendUserReturnsCurrentBackendUser(): void
    {
        $actual = Src\Utility\BackendUtility::getBackendUser();

        self::assertIsArray($actual->user);
        self::assertSame(1, $actual->user[$actual->userid_column]);
    }

    /**
     * @test
     */
    public function getPageTitleReturnsNullIfPageDoesNotExist(): void
    {
        self::assertNull(Src\Utility\BackendUtility::getPageTitle(99));
    }

    /**
     * @test
     */
    public function getPageTitleReturnsNullIfNoPageTitleIsSet(): void
    {
        self::assertNull(Src\Utility\BackendUtility::getPageTitle(2));
    }

    /**
     * @test
     */
    public function getPageTitleReturnsPageTitle(): void
    {
        self::assertSame('Root', Src\Utility\BackendUtility::getPageTitle(1));
    }
}
