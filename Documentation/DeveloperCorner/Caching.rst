..  include:: /Includes.rst.txt

..  _caching:

=======
Caching
=======

Once a sitemap is located by a :ref:`sitemap provider <sitemap-providers>`,
the path to the XML sitemap is cached. Caching happens with a custom
`sitemap_locator` cache which defaults to a filesystem cache located at
:file:`var/cache/code/sitemap_locator`.

..  php:namespace:: EliasHaeussler\Typo3SitemapLocator\Cache

..  php:class:: SitemapsCache

    Read and write sitemap cache entries from custom `sitemap_locator` cache.

    ..  php:method:: get($site, $siteLanguage = null)

        Get the located sitemaps of a given site.

        :param TYPO3\\CMS\\Core\\Site\\Entity\\Site $site: The sitemap's site object.
        :param TYPO3\\CMS\\Core\\Site\\Entity\\SiteLanguage $siteLanguage: An optional site language.
        :returns: Located sitemaps of a given site.

    ..  php:method:: set($sitemaps)

        Add the located sitemaps to the `sitemap_locator` cache.

        :param array $sitemaps: The located sitemaps to be cached.

..  seealso::

    View the sources on GitHub:

    -   `SitemapsCache <https://github.com/eliashaeussler/typo3-sitemap-locator/blob/main/Classes/Cache/SitemapsCache.php>`__
