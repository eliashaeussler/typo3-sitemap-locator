<?php

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

$GLOBALS['SiteConfiguration']['site_language']['columns']['xml_sitemap_path'] = [
    'label' => 'LLL:EXT:sitemap_locator/Resources/Private/Language/locallang_db.xlf:site_language.xml_sitemap_path.label',
    'description' => 'LLL:EXT:sitemap_locator/Resources/Private/Language/locallang_db.xlf:site_language.xml_sitemap_path.description',
    'displayCond' => 'FIELD:languageId:>:0',
    'config' => [
        'type' => 'input',
        'valuePicker' => [
            'items' => [
                [
                    \EliasHaeussler\Typo3SitemapLocator\Sitemap\Provider\DefaultProvider::DEFAULT_PATH,
                    \EliasHaeussler\Typo3SitemapLocator\Sitemap\Provider\DefaultProvider::DEFAULT_PATH,
                ],
            ],
        ],
        'eval' => 'trim',
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['columns']['xml_sitemap_status'] = [
    'label' => 'LLL:EXT:sitemap_locator/Resources/Private/Language/locallang_db.xlf:site_language.xml_sitemap_status.label',
    'description' => 'LLL:EXT:sitemap_locator/Resources/Private/Language/locallang_db.xlf:site_language.xml_sitemap_status.description',
    'displayCond' => 'FIELD:languageId:>:0',
    'config' => [
        'type' => 'user',
        'renderType' => 'xmlSitemapLocation',
    ],
];

$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'] = str_replace(
    '--palette--;;default,',
    '--palette--;;default, --palette--;;xml_sitemap,',
    (string)$GLOBALS['SiteConfiguration']['site_language']['types']['1']['showitem'],
);

$GLOBALS['SiteConfiguration']['site_language']['palettes']['xml_sitemap'] = [
    'label' => 'LLL:EXT:sitemap_locator/Resources/Private/Language/locallang_db.xlf:palettes.xml_sitemap',
    'showitem' => 'xml_sitemap_path, xml_sitemap_status',
];
