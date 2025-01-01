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

namespace EliasHaeussler\Typo3SitemapLocator\Event;

use EliasHaeussler\Typo3SitemapLocator\Domain;
use Psr\Http\Message\ResponseInterface;

/**
 * SitemapValidatedEvent
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class SitemapValidatedEvent
{
    public function __construct(
        private readonly Domain\Model\Sitemap $sitemap,
        private readonly ?ResponseInterface $response,
        private bool $valid,
    ) {}

    public function getSitemap(): Domain\Model\Sitemap
    {
        return $this->sitemap;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function setValid(bool $valid): void
    {
        $this->valid = $valid;
    }
}
