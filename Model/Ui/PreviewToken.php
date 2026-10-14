<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Model\Ui;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * Signed, expiring access to the storefront component library (/zero/ui/)
 * for the admin's Content > Design > UI Components page: an HMAC of the
 * store view and expiry with the installation's crypt key. Nobody without
 * admin access can make one, so the storefront page needs no public switch.
 */
class PreviewToken
{
    public const LIFETIME = 3600;

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    /**
     * @param int $storeId
     * @param int|null $now
     * @return array{zero_ui_token: string, zero_ui_expires: int}
     */
    public function create(int $storeId, ?int $now = null): array
    {
        $expires = ($now ?? time()) + self::LIFETIME;

        return ['zero_ui_token' => $this->sign($storeId, $expires), 'zero_ui_expires' => $expires];
    }

    public function isValid(string $token, int $expires, int $storeId, ?int $now = null): bool
    {
        return $token !== ''
            && $expires >= ($now ?? time())
            && $expires <= ($now ?? time()) + self::LIFETIME
            && hash_equals($this->sign($storeId, $expires), $token);
    }

    private function sign(int $storeId, int $expires): string
    {
        $key = (string) $this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY);

        return hash_hmac('sha256', 'zero-ui|' . $storeId . '|' . $expires, 'zero-ui-preview|' . $key);
    }
}
