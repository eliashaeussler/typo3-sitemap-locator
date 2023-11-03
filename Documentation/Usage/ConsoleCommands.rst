..  include:: /Includes.rst.txt

..  _console-commands:

================
Console commands
================

The extension provides the following console commands:

..  _sitemap-locator-locate:

`sitemap-locator:locate`
========================

A command to locate XML sitemaps of a given site and optional site
language.

..  tabs::

    ..  group-tab:: Composer-based installation

        ..  code-block:: bash

            vendor/bin/typo3 sitemap-locator:locate <site> [<options>]

    ..  group-tab:: Legacy installation

        ..  code-block:: bash

            typo3/sysext/core/bin/typo3 sitemap-locator:locate <site> [<options>]

The following command options are available:

..  confval:: site

    :Required: true
    :type: integer (root page ID) or string (site identifier)
    :Default: none
    :Multiple allowed: false

    Use this mandatory command argument to pass a site identifier or root page
    ID of the site for which to locate XML sitemaps.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 sitemap-locator:locate 47
                vendor/bin/typo3 sitemap-locator:locate main

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 sitemap-locator:locate 47
                typo3/sysext/core/bin/typo3 sitemap-locator:locate main

..  confval:: -l|--language

    :Required: false
    :type: integer
    :Default: none (= default language)
    :Multiple allowed: false

    You can explicitly define a site language for which to locate an
    XML sitemap. If omitted, the XML sitemap of the default site
    language is located. This option is mutually exclusive with the
    `--all` option.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 sitemap-locator:locate --language 1

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 sitemap-locator:locate --language 1

..  confval:: -a|--all

    :Required: false
    :type: boolean
    :Default: false
    :Multiple allowed: false

    Use this option to locate XML sitemaps of all available site languages
    of the given site. This option is mutually exclusive with the `--language`
    option.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 sitemap-locator:locate --all

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 sitemap-locator:locate --all

..  confval:: --validate

    :Required: false
    :type: boolean
    :Default: false
    :Multiple allowed: false

    Validate if located XML sitemaps actually exist and are properly accessible
    By passing this option, the located sitemaps are additionally validated
    for existence. If at least one XML sitemap does not exist, the command
    fails with exit code :php:`0`.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 sitemap-locator:locate --validate

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 sitemap-locator:locate --validate

..  confval:: -j|--json

    :Required: false
    :type: boolean
    :Default: false
    :Multiple allowed: false

    Located XML sitemaps are displayed as list by default. By using this
    option, you can switch to JSON output instead. This may come in handy
    when using the command to automate processes which require easy parsable
    result data.

    Example:

    ..  tabs::

        ..  group-tab:: Composer-based installation

            ..  code-block:: bash

                vendor/bin/typo3 sitemap-locator:locate --json

        ..  group-tab:: Legacy installation

            ..  code-block:: bash

                typo3/sysext/core/bin/typo3 sitemap-locator:locate --json
