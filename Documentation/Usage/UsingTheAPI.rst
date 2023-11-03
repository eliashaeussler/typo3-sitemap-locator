..  include:: /Includes.rst.txt

..  _using-the-api:

=============
Using the API
=============

The main functionality of this extension is to provide a PHP API which
allows to locate XML sitemaps for a given site and optional site language.
Read more about how to use this API on this page.

..  php:namespace:: EliasHaeussler\Typo3SitemapLocator\Sitemap

..  php:class:: SitemapLocator

    Service to locate XML sitemaps of a given site.

    ..  php:method:: locateBySite($site, $siteLanguage = null)

        Locate XML sitemaps of the given site and site language.

        :param TYPO3\\CMS\\Core\\Site\\Entity\\Site $site: The site whose XML sitemap path should be located.
        :param TYPO3\\CMS\\Core\\Site\\Entity\\SiteLanguage $siteLanguage: An optional site language to include while locating the XML sitemap path.
        :returns: An array of instances of :php:class:`EliasHaeussler\\Typo3SitemapLocator\\Domain\\Model\\Sitemap`.

    ..  php:method:: locateAllBySite($site)

        Locate XML sitemaps of the given site and all their available languages.

        :param TYPO3\\CMS\\Core\\Site\\Entity\\Site $site: The site whose XML sitemap path should be located.
        :returns: An array of instances of :php:class:`EliasHaeussler\\Typo3SitemapLocator\\Domain\\Model\\Sitemap`, indexed by the site language id.

    ..  php:method:: isValidSitemap($sitemap)

        Check whether the given sitemap actually exists.

        :param EliasHaeussler\\Typo3SitemapLocator\\Domain\\Model\\Sitemap $sitemap: The XML sitemap to check for existence
        :returntype: bool

..  _api-example:

Example
=======

::

    use EliasHaeussler\Typo3SitemapLocator;
    use TYPO3\CMS\Core;

    $sitemapLocator = Core\Utility\GeneralUtility::makeInstance(Typo3SitemapLocator\Sitemap\SitemapLocator::class);
    $siteFinder = Core\Utility\GeneralUtility::makeInstance(Core\Site\SiteFinder::class);
    $sitemaps = [];

    // Fetch all available sites
    $sites = $siteFinder->getAllSites();

    // Locate XML sitemaps of each site
    foreach ($sites as $site) {
        $sitemaps[$site->getIdentifier()] = $sitemapLocator->locateBySite($site);
    }

..  seealso::
    View the sources on GitHub:

    -   `SitemapLocator <https://github.com/eliashaeussler/typo3-sitemap-locator/blob/main/Classes/Sitemap/SitemapLocator.php>`__
