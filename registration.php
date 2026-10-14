<?php
/**
 * Zero_Core: the PHP side of the Zero theme (app/design/frontend/Zero/default) —
 * RequireJS removal, HTMX fragment endpoints/layout handles, and server-side
 * data the templates need that stock only exposes to JS.
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Zero_Core',
    __DIR__
);
