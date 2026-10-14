/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * Only pages that run RequireJS read this: the stock checkout fallback and
 * pages rendered with another theme (Zero pages load no RequireJS).
 */
var config = {
    config: {
        mixins: {
            'Magento_Customer/js/customer-data': {
                'Zero_Core/js/customer-data-mixin': true
            }
        }
    }
};
