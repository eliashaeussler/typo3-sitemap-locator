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

namespace EliasHaeussler\Typo3SitemapLocator\Http\Client;

use EliasHaeussler\Typo3SitemapLocator\Event\BeforeClientConfiguredEvent;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Psr\EventDispatcher;
use TYPO3\CMS\Core;

/**
 * ClientFactory
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 * @internal
 */
final class ClientFactory
{
    private ?ClientInterface $client = null;

    public function __construct(
        private readonly Core\Http\Client\GuzzleClientFactory $guzzleClientFactory,
        private readonly EventDispatcher\EventDispatcherInterface $eventDispatcher,
    ) {}

    public function get(): ClientInterface
    {
        $clientConfig = $this->getClientConfig();

        return new Client($clientConfig);
    }

    /**
     * @return array<string, mixed>
     */
    public function getClientConfig(): array
    {
        $this->client ??= $this->guzzleClientFactory->getClient();

        $clientConfig = $this->getClientConfigFromReflection($this->client);
        $event = new BeforeClientConfiguredEvent($clientConfig);

        $this->eventDispatcher->dispatch($event);

        return $event->getOptions();
    }

    /**
     * @return array<string, mixed>
     */
    private function getClientConfigFromReflection(ClientInterface $client): array
    {
        if (!($client instanceof Client)) {
            return [];
        }

        return (new \ReflectionObject($client))->getProperty('config')->getValue($client);
    }
}
