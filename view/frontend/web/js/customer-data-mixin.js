/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 *
 * customerData.set() before initStorage() throws, and mage/apply may start
 * the checkout app before customer-data's init (on 2.4.7 it always did, and
 * the stock checkout never rendered). Early calls are kept and replayed
 * after initStorage().
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
