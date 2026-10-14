<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\ViewModel;

use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The component library at /zero/ui/ (Controller\Ui\Index). Each component
 * is one template under Zero_Core::ui/components/: the page renders it live
 * and shows that same file as the code to copy, so example and code can't
 * drift apart. Add a component: a template there plus an entry here (a
 * theme or module can add entries through di.xml, `components`).
 */
class UiComponents implements ArgumentInterface
{
    /**
     * @param array<string, array{title: string, description: string, template: string}> $components
     */
    public function __construct(
        private readonly array $components = [
            'buttons' => [
                'title' => 'Buttons',
                'description' => 'DaisyUI btn classes. Icon-only buttons need an aria-label.',
                'template' => 'Zero_Core::ui/components/buttons.phtml',
            ],
            'form-fields' => [
                'title' => 'Form fields',
                'description' => 'Every field has a <label for>; required fields carry `required` (the browser checks them) and the .required marker; an error is tied to its field with aria-describedby.',
                'template' => 'Zero_Core::ui/components/form-fields.phtml',
            ],
            'messages' => [
                'title' => 'Messages',
                'description' => 'The same markup as Magento\'s flash messages (div.message), styled by the theme. Text contrast meets WCAG AA in both color schemes.',
                'template' => 'Zero_Core::ui/components/messages.phtml',
            ],
            'badges' => [
                'title' => 'Badges',
                'description' => 'Product badges as on cards (Zero\\Core\\ViewModel\\ProductBadges renders them from the catalog), and DaisyUI badges.',
                'template' => 'Zero_Core::ui/components/badges.phtml',
            ],
            'accordion' => [
                'title' => 'Accordion',
                'description' => 'Native <details>: keyboard, screen readers and find-in-page work without JavaScript. Give a group the same `name` to keep one open at a time.',
                'template' => 'Zero_Core::ui/components/accordion.phtml',
            ],
            'tabs' => [
                'title' => 'Tabs',
                'description' => 'DaisyUI radio tabs: no JavaScript, arrow keys move between tabs.',
                'template' => 'Zero_Core::ui/components/tabs.phtml',
            ],
            'dialog' => [
                'title' => 'Dialog',
                'description' => 'Native <dialog>: focus is trapped and Escape closes it. data-dialog-open (web/js/zero.js) opens it; a <form method="dialog"> button closes it.',
                'template' => 'Zero_Core::ui/components/dialog.phtml',
            ],
            'slider' => [
                'title' => 'Slider',
                'description' => 'Scroll-snap track: swipe and trackpad work natively; the buttons (data-scroller-prev/next, web/js/zero.js) add a page step and mirror in right-to-left stores. A named, focusable region, so keyboard users can scroll it too.',
                'template' => 'Zero_Core::ui/components/slider.phtml',
            ],
            'quantity' => [
                'title' => 'Quantity stepper',
                'description' => 'A number input between two buttons (data-qty-step, web/js/zero.js). Without JavaScript the input still works.',
                'template' => 'Zero_Core::ui/components/quantity.phtml',
            ],
            'steps' => [
                'title' => 'Steps',
                'description' => 'Progress through a sequence, as in the step checkout. Mark the current one with aria-current="step".',
                'template' => 'Zero_Core::ui/components/steps.phtml',
            ],
            'loading' => [
                'title' => 'Loading (HTMX)',
                'description' => 'htmx-indicator shows only while its request runs (hx-indicator points at it). The button sends a real request.',
                'template' => 'Zero_Core::ui/components/loading.phtml',
            ],
        ]
    ) {
    }

    /**
     * @return array<string, array{title: string, description: string, template: string}>
     */
    public function getComponents(): array
    {
        return $this->components;
    }

    /**
     * A component template's code as shown on the page: without its leading
     * PHP docblock (the file's own documentation), trimmed.
     *
     * @param string|false $file Absolute path of the template
     * @return string
     */
    public function getSource($file): string
    {
        if (!$file || !is_file($file)) {
            return '';
        }
        $source = (string) file_get_contents($file);
        $source = (string) preg_replace('#^<\?php\s*/\*\*.*?\*/\s*\?>\s*#s', '', $source, 1);

        return trim($source) . "\n";
    }
}
