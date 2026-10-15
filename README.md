# Zero_Core

The storefront core of **Zero for Magento Open Source**: what the Zero theme
needs from the server so pages work without RequireJS, jQuery or Knockout.

- HTMX endpoints for private content: the cart counter, minicart, welcome
  message, account links, wishlist and compare counters, messages, form key
  and session bootstrap (`Controller/`), so full-page-cached pages stay
  cacheable.
- View models for the theme's templates: home page sections, product page,
  catalog, customer account (`ViewModel/`).
- Region lookup, search suggestions, captcha refresh, recently viewed and
  compared products.
- Admin: Stores > Configuration > **Zero**.

Install it on its own to build your own theme on top of it, or install the
theme (`fixstacks/theme-frontend-zero`), which requires it.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4 with the GD extension built with WebP (`php -r 'var_dump(function_exists("imagewebp"));'` says `true`; Magento's own PHP requirements cover the rest). Without WebP in GD, Zero skips its WebP image copies and serves the originals.

```bash
composer require fixstacks/module-zero-core
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

<a href="https://vilmate.com"><picture>
  <source media="(prefers-color-scheme: dark)" srcset="https://raw.githubusercontent.com/Fixstacks/zero/main/docs/images/vilmate-logo-dark.svg">
  <img src="https://raw.githubusercontent.com/Fixstacks/zero/main/docs/images/vilmate-logo.svg" alt="Vilmate" height="34" align="right">
</picture></a>

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com), a software development company.
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
