/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Component library (/zero/ui/): [data-copy="#code"] copies that element's
 * text and says so (button label and a status for screen readers).
 */
(function () {
    'use strict';

    var status = document.querySelector('[data-copy-status]');

    document.addEventListener('click', function (event) {
        var button = event.target instanceof Element ? event.target.closest('[data-copy]') : null;
        var source = button && document.querySelector(button.getAttribute('data-copy'));
        if (!source || !navigator.clipboard) {
            return;
        }
        var label = button.textContent;
        navigator.clipboard.writeText(source.textContent).then(function () {
            button.textContent = button.getAttribute('data-copied-label') || 'Copied';
            if (status) {
                status.textContent = button.textContent;
            }
            setTimeout(function () {
                button.textContent = label;
            }, 1500);
        });
    });
})();
