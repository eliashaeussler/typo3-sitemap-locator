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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Unit\Sitemap\Provider;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use Exception;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * RobotsTxtProviderTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @covers \EliasHaeussler\Typo3SitemapLocator\Sitemap\Provider\RobotsTxtProvider
 */
final class RobotsTxtProviderTest extends TestingFramework\Core\Unit\UnitTestCase
{
    private Tests\Unit\Fixtures\DummyRequestFactory $requestFactory;
    private Core\Site\Entity\Site $site;
    private Src\Sitemap\Provider\RobotsTxtProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestFactory = new Tests\Unit\Fixtures\DummyRequestFactory();
        $this->site = new Core\Site\Entity\Site('foo', 1, ['base' => 'https://www.example.com/']);
        $this->subject = new Src\Sitemap\Provider\RobotsTxtProvider($this->requestFactory);
    }

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfNoRobotsTxtExists(): void
    {
        $this->requestFactory->handler->append(
            new Exception(),
        );

        self::assertSame([], $this->subject->get($this->site));
    }

    /**
     * @test
     */
    public function getReturnsEmptyArrayIfNoRobotsTxtDoesNotContainSitemapConfiguration(): void
    {
        $response = new Core\Http\Response();
        $body = $response->getBody();
        $body->write('foo');
        $body->rewind();

        $this->requestFactory->handler->append($response);

        self::assertSame([], $this->subject->get($this->site));
    }

    /**
     * @test
     */
    public function getReturnsSitemapIfRobotsTxtContainsSitemapConfiguration(): void
    {
        $response = new Core\Http\Response();
        $body = $response->getBody();
        $body->write(
            <<<TXT
Sitemap: https://www.example.com/baz.xml
Sitemap: https://www.example.com/bar.xml
TXT
        );
        $body->rewind();

        $this->requestFactory->handler->append($response);

        $expected = [
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/baz.xml'),
                $this->site,
                $this->site->getDefaultLanguage(),
            ),
            new Src\Domain\Model\Sitemap(
                new Core\Http\Uri('https://www.example.com/bar.xml'),
                $this->site,
                $this->site->getDefaultLanguage(),
            ),
        ];

        self::assertEquals($expected, $this->subject->get($this->site));
    }
}
