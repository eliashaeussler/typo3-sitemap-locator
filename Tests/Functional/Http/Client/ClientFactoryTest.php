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

namespace EliasHaeussler\Typo3SitemapLocator\Tests\Functional\Http\Client;

use EliasHaeussler\Typo3SitemapLocator as Src;
use EliasHaeussler\Typo3SitemapLocator\Tests;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework;
use Symfony\Component\EventDispatcher;
use TYPO3\CMS\Core;
use TYPO3\TestingFramework;

/**
 * ClientFactoryTest
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(Src\Http\Client\ClientFactory::class)]
final class ClientFactoryTest extends TestingFramework\Core\Functional\FunctionalTestCase
{
    use Tests\Functional\ClientMockTrait;

    protected array $testExtensionsToLoad = [
        'sitemap_locator',
    ];

    protected array $configurationToUseInTestInstance = [
        'HTTP' => [
            RequestOptions::VERIFY => false,
        ],
    ];

    protected bool $initializeDatabase = false;

    private EventDispatcher\EventDispatcher $eventDispatcher;
    private Src\Http\Client\ClientFactory $subject;

    public function setUp(): void
    {
        parent::setUp();

        $this->registerMockHandler();

        $this->subject = new Src\Http\Client\ClientFactory(
            $this->get(Core\Http\Client\GuzzleClientFactory::class),
            $this->eventDispatcher,
        );
    }

    #[Framework\Attributes\Test]
    public function getReturnsClientWithModifiedClientConfig(): void
    {
        $actual = $this->subject->get();

        $clientConfig = $this->getClientConfigViaReflection($actual);

        self::assertSame($this->createMockHandler(), $clientConfig['handler'] ?? null);
        self::assertFalse($clientConfig[RequestOptions::VERIFY] ?? null);
    }

    #[Framework\Attributes\Test]
    public function getClientConfigReturnsTYPO3AndExtensionSpecificClientOptions(): void
    {
        $actual = $this->subject->getClientConfig();

        self::assertSame($this->createMockHandler(), $actual['handler'] ?? null);
        self::assertFalse($actual[RequestOptions::VERIFY] ?? null);
    }

    /**
     * @return array<string, mixed>
     */
    private function getClientConfigViaReflection(ClientInterface $client): array
    {
        self::assertInstanceOf(Client::class, $client);

        $reflection = new \ReflectionObject($client);
        $config = $reflection->getProperty('config')->getValue($client);

        self::assertIsArray($config);

        return $config;
    }
}
