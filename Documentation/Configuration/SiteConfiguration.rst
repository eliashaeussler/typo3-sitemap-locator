..  include:: /Includes.rst.txt

..  _site-configuration:

==================
Site configuration
==================

Within the site configuration of each site, it is possible to explicitly configure
the path to a related XML sitemap. The following configuration options are available
for this purpose:

..  confval:: xml_sitemap_path (site)
    :Path: xml_sitemap_path
    :type: string

    Path to the XML sitemap of the configured site's **base language**.
    It must be relative to the entry point of the site.

    ..  image:: ../Images/site-configuration.png
        :alt: Configuration of XML sitemap path within the Sites module

..  confval:: xml_sitemap_path (site_language)
    :Path: languages > (site language) > xml_sitemap_path
    :type: string

    Path to the XML sitemap of an **additional site language**.
    It must be relative to the entry point of the site language.

..  seealso::

    Take a look at :ref:`sitemap-providers` to learn how the extension
    internally evaluates the site configuration values to determine the
    path to the XML sitemap of a site.
