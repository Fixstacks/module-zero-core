<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * Renders flash messages server-side.
 *
 * Stock's MessagePlugin moves session messages into the `mage-messages`
 * cookie, which only a Knockout component reads, so without JS no flash
 * message (errors, successes from any controller) ever showed. This parses
 * that cookie (JSON array of {type, text}) and deletes it, like the JS
 * component's purgeMessages, so a message shows once.
 */
class CookieMessages implements ArgumentInterface
{
    private const COOKIE_NAME = 'mage-messages';

    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly Json $serializer
    ) {
    }

    /**
     * @return array<int, array{type: string, text: string}>
     */
    public function getMessages(): array
    {
        $raw = $this->cookieManager->getCookie(self::COOKIE_NAME);
        if (!$raw) {
            return [];
        }

        try {
            $messages = $this->serializer->unserialize($raw);
        } catch (\InvalidArgumentException $e) {
            return [];
        }

        if (!is_array($messages)) {
            return [];
        }

        $this->cookieManager->deleteCookie(
            self::COOKIE_NAME,
            $this->cookieMetadataFactory->createPublicCookieMetadata()->setPath('/')
        );

        return $messages;
    }
}
