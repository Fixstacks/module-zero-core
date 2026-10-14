<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Controller\Session;

use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\View\Result\LayoutFactory;
use Zero\Core\Controller\PrivateFragmentActionInterface;

/**
 * The visitor's session and all of its per-visitor bits, in one request.
 *
 * Every page is served from the page cache, so what depends on the visitor
 * (cart and wishlist counters, account links, welcome text, flash messages,
 * form_key) comes afterwards. It used to come as one request each, after
 * this one: six or seven Magento bootstraps per page view, and with file
 * sessions they waited for each other on the session lock. Now
 * Zero_Core::js/session.js asks once, `?fragments=cartcounter,welcome,...`,
 * and gets JSON: {"fragments": {name: html}, "formKey": "..."}; it swaps
 * each one in (same markup as each fragment's own controller, which still
 * serves the later refreshes, e.g. the cart counter on cart:updated).
 *
 * It also creates the session before anything else needs it: on a first
 * visit parallel requests each started their own, and the form_key the page
 * got belonged to another one ("Invalid Form Key" on Add to Cart).
 *
 * Without `fragments` (a page cached before this change) it answers as it
 * used to: an empty body and `HX-Trigger: zero:session-ready`.
 */
class Bootstrap implements PrivateFragmentActionInterface
{
    /** Fragment name => [layout handle, block] — the same as each fragment's own controller. */
    private const FRAGMENTS = [
        'messages' => ['zero_theme_messages', 'zero.theme.messages'],
        'welcome' => ['zero_customer_welcome', 'zero.customer.welcome'],
        'accountlinks' => ['zero_customer_accountlinks', 'top.links'],
        'cartcounter' => ['zero_checkout_cartcounter', 'zero.checkout.cartcounter'],
        'wishlistcounter' => ['zero_customer_wishlistcounter', 'zero.customer.wishlistcounter'],
        'comparecounter' => ['zero_customer_comparecounter', 'zero.customer.comparecounter'],
    ];

    public function __construct(
        private readonly ResultFactory $resultFactory,
        private readonly LayoutFactory $resultLayoutFactory,
        private readonly HttpRequest $request,
        private readonly HttpResponse $response,
        private readonly FormKey $formKey
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute()
    {
        $requested = $this->request->getParam('fragments');
        if (!is_string($requested)) {
            $this->response->setHeader('HX-Trigger', 'zero:session-ready');

            return $this->resultFactory->create(ResultFactory::TYPE_RAW)->setContents('');
        }

        $names = array_values(array_intersect(array_keys(self::FRAGMENTS), explode(',', $requested)));
        $html = [];
        if ($names) {
            $resultLayout = $this->resultLayoutFactory->create();
            foreach ($names as $name) {
                $resultLayout->addHandle(self::FRAGMENTS[$name][0]);
            }
            $layout = $resultLayout->getLayout();
            foreach ($names as $name) {
                $html[$name] = (string) $layout->renderElement(self::FRAGMENTS[$name][1]);
            }
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData([
            'fragments' => (object) $html,
            'formKey' => $this->formKey->getFormKey(),
        ]);
    }
}
