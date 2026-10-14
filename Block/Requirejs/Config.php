<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block\Requirejs;

/**
 * Stock RequireJs head config, for the stock-checkout fallback only.
 * - The parent inserts after `requirejs/require.js`, which Zero removes
 *   sitewide (a removed asset name can't come back), so this anchors on the
 *   renamed copy `Zero_Core::js/vendor/require.js`.
 * - It adds `Magento_Csp::js/sri.js` itself: declared in the head it formed
 *   its own group in the wrong place.
 */
class Config extends \Magento\RequireJs\Block\Html\Head\Config
{
    private const REPLACEMENT_AFTER = 'Zero_Core::js/vendor/require.js';

    private const SRI_JS_ID = 'Magento_Csp::js/sri.js';

    /**
     * The parent's $fileManager/$bundleConfig are private, so the same services are
     * injected again under local names. $assetRepo builds the sri.js asset.
     *
     * @param \Magento\Framework\View\Element\Context $context
     * @param \Magento\Framework\RequireJs\Config $config
     * @param \Magento\RequireJs\Model\FileManager $fileManagerLocal
     * @param \Magento\Framework\View\Page\Config $pageConfig
     * @param \Magento\Framework\View\Asset\ConfigInterface $bundleConfigLocal
     * @param \Magento\Framework\View\Asset\Minification $minification
     * @param \Magento\Framework\View\Asset\Repository $assetRepo
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Context $context,
        \Magento\Framework\RequireJs\Config $config,
        private readonly \Magento\RequireJs\Model\FileManager $fileManagerLocal,
        \Magento\Framework\View\Page\Config $pageConfig,
        private readonly \Magento\Framework\View\Asset\ConfigInterface $bundleConfigLocal,
        \Magento\Framework\View\Asset\Minification $minification,
        private readonly \Magento\Framework\View\Asset\Repository $assetRepo,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $config,
            $fileManagerLocal,
            $pageConfig,
            $bundleConfigLocal,
            $minification,
            $data
        );
    }

    /**
     * @inheritdoc
     */
    protected function _prepareLayout()
    {
        $after = self::REPLACEMENT_AFTER;
        $assetCollection = $this->pageConfig->getAssetCollection();
        if ($this->minification->isEnabled('js')) {
            $minResolver = $this->fileManagerLocal->createMinResolverAsset();
            $assetCollection->insert(
                $minResolver->getFilePath(),
                $minResolver,
                $after
            );
            $after = $minResolver->getFilePath();
        }
        $requireJsMapConfig = $this->fileManagerLocal->createRequireJsMapConfigAsset();
        if ($requireJsMapConfig) {
            $urlResolverAsset = $this->fileManagerLocal->createUrlResolverAsset();
            $assetCollection->insert(
                $urlResolverAsset->getFilePath(),
                $urlResolverAsset,
                $after
            );
            $after = $urlResolverAsset->getFilePath();
            $assetCollection->insert(
                $requireJsMapConfig->getFilePath(),
                $requireJsMapConfig,
                $after
            );
            $after = $requireJsMapConfig->getFilePath();
        }
        if ($this->bundleConfigLocal->isBundlingJsFiles()) {
            $bundleAssets = $this->fileManagerLocal->createBundleJsPool();
            $staticAsset = $this->fileManagerLocal->createStaticJsAsset();
            if (!empty($bundleAssets) && $staticAsset !== false) {
                $assetCollection->insert(
                    $staticAsset->getFilePath(),
                    $staticAsset,
                    $after
                );
                $after = $staticAsset->getFilePath();
                foreach ($bundleAssets as $bundleAsset) {
                    $assetCollection->insert(
                        $bundleAsset->getFilePath(),
                        $bundleAsset,
                        $after
                    );
                    $after = $bundleAsset->getFilePath();
                }
            }
        }

        // See class docblock: sri.js is built and inserted here, rather
        // than repositioned after the fact, so it joins our loader's
        // existing property-group instead of forming its own.
        if (!$assetCollection->has(self::SRI_JS_ID)) {
            $sriAsset = $this->assetRepo->createAsset(self::SRI_JS_ID);
            $assetCollection->insert(self::SRI_JS_ID, $sriAsset, $after);
            $after = self::SRI_JS_ID;
        }

        $requireJsConfig = $this->fileManagerLocal->createRequireJsConfigAsset();
        $assetCollection->insert(
            $requireJsConfig->getFilePath(),
            $requireJsConfig,
            $after
        );
        $after = $requireJsConfig->getFilePath();
        $requireJsMixinsConfig = $this->fileManagerLocal->createRequireJsMixinsAsset();
        $assetCollection->insert(
            $requireJsMixinsConfig->getFilePath(),
            $requireJsMixinsConfig,
            $after
        );
        return \Magento\Framework\View\Element\AbstractBlock::_prepareLayout();
    }
}
