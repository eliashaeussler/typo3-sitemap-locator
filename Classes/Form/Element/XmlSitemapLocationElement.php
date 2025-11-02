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

namespace EliasHaeussler\Typo3SitemapLocator\Form\Element;

use EliasHaeussler\Typo3SitemapLocator\Domain;
use EliasHaeussler\Typo3SitemapLocator\Exception;
use EliasHaeussler\Typo3SitemapLocator\Sitemap;
use TYPO3\CMS\Backend;
use TYPO3\CMS\Core;

/**
 * XmlSitemapLocationElement
 *
 * @author Elias Häußler <elias@haeussler.dev>
 * @license GPL-2.0-or-later
 */
final class XmlSitemapLocationElement extends Backend\Form\Element\AbstractFormElement
{
    /**
     * @var array<string, mixed>
     */
    protected $defaultFieldInformation = [
        'tcaDescription' => [
            'renderType' => 'tcaDescription',
        ],
    ];

    public function __construct(
        private readonly Core\Imaging\IconFactory $iconFactory,
        private readonly Core\Site\SiteFinder $siteFinder,
        private readonly Sitemap\SitemapLocator $sitemapLocator,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function render(): array
    {
        $fieldInformationResult = $this->renderFieldInformation();
        $resultArray = $this->mergeChildReturnIntoExistingResult($this->initializeResultArray(), $fieldInformationResult);

        $html = [];
        $html[] = '<div class="formengine-field-item t3js-formengine-field-item">';
        $html[] =     '<div class="form-wizards-wrap">';
        $html[] =         '<div class="form-wizards-element">';
        $html[] =             $this->renderSitemapStatus();
        $html[] =         '</div>';
        $html[] =     '</div>';
        $html[] = '</div>';

        $resultArray['html'] .= PHP_EOL . implode(PHP_EOL, $html);

        return $resultArray;
    }

    private function renderSitemapStatus(): string
    {
        $command = $this->data['command'];

        // Early return on new data
        if ($command !== 'edit') {
            return $this->renderCallout(
                'info',
                'actions-info',
                $this->translate('xml_sitemap_location.alert.new_site'),
                false,
            );
        }

        try {
            [$site, $siteLanguage] = $this->resolveCurrentSiteAndLanguage();
            $sitemaps = $this->sitemapLocator->locateBySite($site, $siteLanguage);
        } catch (\Exception $exception) {
            return $this->renderCallout(
                'danger',
                'actions-exclamation',
                $exception->getMessage(),
                false,
            );
        }

        // Early return if no sitemaps were found
        if ($sitemaps === []) {
            return $this->renderCallout(
                'info',
                'actions-info',
                $this->translate('xml_sitemap_location.alert.no_sitemaps'),
                false,
            );
        }

        $html = [];

        foreach ($sitemaps as $sitemap) {
            $html[] = $this->renderSitemap($sitemap);
        }

        return implode(PHP_EOL, $html);
    }

    /**
     * @return array{Core\Site\Entity\Site, Core\Site\Entity\SiteLanguage}
     * @throws Core\Exception\SiteNotFoundException
     * @throws Exception\TableIsNotSupported
     */
    private function resolveCurrentSiteAndLanguage(): array
    {
        $row = $this->data['databaseRow'];
        $tableName = $this->data['tableName'];

        $site = $this->siteFinder->getSiteByRootPageId(
            match ($tableName) {
                'site' => (int)$row['rootPageId'][0],
                'site_language' => (int)$this->data['inlineParentUid'],
                default => throw new Exception\TableIsNotSupported($tableName),
            },
        );

        $siteLanguage = match ($tableName) {
            'site' => $site->getDefaultLanguage(),
            'site_language' => $site->getLanguageById((int)$row['languageId'][0]),
            default => throw new Exception\TableIsNotSupported($tableName),
        };

        return [$site, $siteLanguage];
    }

    private function renderSitemap(Domain\Model\Sitemap $sitemap): string
    {
        $url = (string)$sitemap->getUri();
        $baseUrl = rtrim((string)$sitemap->getSiteLanguage()->getBase(), '/') . '/';

        // Compare site base URL and sitemap URL
        if (!str_starts_with($url, $baseUrl)) {
            $baseUrl = '';
            $sitemapPath = $url;
        } else {
            $sitemapPath = preg_replace('#^' . preg_quote($baseUrl, '#') . '#', '', $url);
        }

        // Render callout body
        $body = sprintf(
            '<a href="%s" target="_blank">%s<strong>%s</strong></a>',
            $url,
            $baseUrl,
            $sitemapPath,
        );

        if ($this->sitemapLocator->isValidSitemap($sitemap)) {
            return $this->renderCallout('success', 'actions-check', $body);
        }

        return $this->renderCallout('warning', 'actions-exclamation', $body);
    }

    private function renderCallout(string $state, string $icon, string $body, bool $small = true): string
    {
        $classes = [
            'callout',
            'callout-' . $state,
        ];

        if ($small) {
            $classes[] = 'callout-sm';
        }

        $html = [];
        $html[] = '<div class="' . implode(' ', $classes) . '">';
        $html[] =     '<div class="media">';
        $html[] =         '<div class="media-left">';
        $html[] =             '<span class="icon-emphasized">';
        $html[] =                 $this->renderIcon($icon);
        $html[] =             '</span>';
        $html[] =         '</div>';
        $html[] =         '<div class="media-body">';
        $html[] =             '<div class="callout-body">';
        $html[] =                 $body;
        $html[] =             '</div>';
        $html[] =         '</div>';
        $html[] =     '</div>';
        $html[] = '</div>';

        return implode(PHP_EOL, $html);
    }

    private function renderIcon(string $identifier): string
    {
        return $this->iconFactory->getIcon($identifier, Core\Imaging\IconSize::SMALL)->render();
    }

    private function translate(string $key): string
    {
        return $this->getLanguageService()->sL('LLL:EXT:sitemap_locator/Resources/Private/Language/locallang.xlf:' . $key);
    }
}
