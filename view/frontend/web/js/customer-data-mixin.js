/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * customerData.set() before customerData.initStorage() throws ("Cannot read
 * properties of undefined (reading 'set')"): its storage doesn't exist yet.
 * mage/apply starts element components (the checkout's #checkout app) before
 * the "*" ones (customer-data's own init), and which finishes first is only
 * a matter of load timing. On Magento 2.4.7 the stock checkout lost that
 * race every time (its 2 MB inline SRI hash list changes the timing):
 * checkout-data saved the email before the storage existed and the checkout
 * never rendered. Calls made that early are kept and replayed right after
 * initStorage(), so the order no longer matters.
 */
define(['mage/utils/wrapper'], function (wrapper) {
    'use strict';

    return function (customerData) {
        var ready = false,
            early = [];

        customerData.initStorage = wrapper.wrap(customerData.initStorage, function (original) {
            var result = original();

            ready = true;
            early.splice(0).forEach(function (args) {
                customerData.set.apply(customerData, args);
            });

            return result;
        });

        customerData.set = wrapper.wrap(customerData.set, function (original, sectionName, sectionData) {
            if (!ready) {
                early.push([sectionName, sectionData]);

                return undefined;
            }

            return original(sectionName, sectionData);
        });

        return customerData;
    };
});
