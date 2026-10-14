<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Zero\Core\Model\Social\Tags;

/**
 * Open Graph and Twitter card tags in <head> (Model\Social\Tags), for link
 * previews in social networks and messengers.
 */
class SocialTags extends Template
{
    /** @var string */
    protected $_template = 'Zero_Core::social-tags.phtml';

    public function __construct(Context $context, private readonly Tags $tags, array $data = [])
    {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, string>
     */
    public function openGraph(): array
    {
        return $this->tags->openGraph();
    }

    /**
     * @return array<string, string>
     */
    public function twitter(): array
    {
        return $this->tags->twitter();
    }
}
