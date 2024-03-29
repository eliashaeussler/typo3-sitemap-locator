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

use EliasHaeussler\RectorConfig\Config\Config;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Privatization\Rector\Class_\FinalizeClassesWithoutChildrenRector;
use Rector\Symfony\Symfony53\Rector\Class_\CommandDescriptionToPropertyRector;
use Rector\ValueObject\PhpVersion;

return static function(RectorConfig $rectorConfig): void {
    Config::create($rectorConfig, PhpVersion::PHP_81)
        ->in(
            __DIR__ . '/Classes',
            __DIR__ . '/Configuration',
            __DIR__ . '/Tests',
        )
        ->not(
            __DIR__ . '/.Build/*',
            __DIR__ . '/.github/*',
            __DIR__ . '/var/*',
        )
        ->withPHPUnit()
        ->withSymfony()
        ->withTYPO3()
        ->skip(AnnotationToAttributeRector::class, [
            __DIR__ . '/Classes/Extension.php',
            __DIR__ . '/Classes/Sitemap/Provider/DefaultProvider.php',
            __DIR__ . '/Classes/Sitemap/Provider/PageTypeProvider.php',
            __DIR__ . '/Classes/Sitemap/Provider/RobotsTxtProvider.php',
            __DIR__ . '/Classes/Sitemap/Provider/SiteProvider.php',
        ])
        ->skip(CommandDescriptionToPropertyRector::class)
        ->skip(FinalizeClassesWithoutChildrenRector::class, [
            // We keep domain models open for extensions
            __DIR__ . '/Classes/Domain/Model/*',
        ])
        ->apply()
    ;

    $rectorConfig->importNames(false, false);
};
