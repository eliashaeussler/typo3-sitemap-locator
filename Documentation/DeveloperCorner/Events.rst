..  include:: /Includes.rst.txt

..  _events:

======
Events
======

The extension dispatches some :ref:`PSR-14 events <t3coreapi:EventDispatcher>`
to provide end users the ability to step in to the sitemap
location and validation process.

The following events are currently dispatched:

..  _sitemaps-located-event:

SitemapsLocatedEvent
====================

This event is dispatched right after an XML sitemap is located via
:php:`\EliasHaeussler\Typo3SitemapLocator\Sitemap\SitemapLocator::locateBySite`.
It allows to modify the list of located XML sitemaps and also provides
the used site and site language.

..  _sitemap-validated-event:

SitemapValidatedEvent
=====================

When :php:`\EliasHaeussler\Typo3SitemapLocator\Sitemap\SitemapLocator::isValidSitemap`
is called, a request to the given sitemap URL is dispatched. If this
request fails or returns a status code of `400` or higher, the sitemap
is considered invalid. Right after the validity check, this event is
dispatched. It contains the located XML sitemap and the URL response
as well as the final validity result, which can be modified by an event
listener.

..  seealso::

    View the sources on GitHub:

    -   `SitemapsLocatedEvent <https://github.com/eliashaeussler/typo3-sitemap-locator/blob/main/Classes/Event/SitemapsLocatedEvent.php>`__
    -   `SitemapValidatedEvent <https://github.com/eliashaeussler/typo3-sitemap-locator/blob/main/Classes/Event/SitemapValidatedEvent.php>`__
