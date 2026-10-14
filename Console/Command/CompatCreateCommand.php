<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\Core\Console\Command;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\Component\ComponentRegistrarInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Scaffolds a compat module adapting a module to the Zero theme
 * (registration, module.xml, Compat\Registry entry, tailwind.css, and
 * template copies with --template):
 *
 *   bin/magento zero:compat:create Vendor_Module --template=path/form.phtml
 *
 * → app/code/ZeroCompat/VendorModule; then enable, setup:upgrade, build CSS.
 */
class CompatCreateCommand extends Command
{
    public function __construct(
        private readonly ComponentRegistrarInterface $componentRegistrar,
        private readonly DirectoryList $directoryList
    ) {
        parent::__construct();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this->setName('zero:compat:create')
            ->setDescription('Creates a compat module that adapts a module to the Zero theme')
            ->addArgument('module', InputArgument::REQUIRED, 'Module to adapt, e.g. Vendor_Module')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Compat module name (default ZeroCompat_<Vendor><Module>)')
            ->addOption(
                'template',
                't',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Template to copy for rewriting, relative to the module\'s view/frontend/templates'
            );
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $module = (string) $input->getArgument('module');
        $moduleDir = $this->componentRegistrar->getPath(ComponentRegistrar::MODULE, $module);
        if (!preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/', $module) || !$moduleDir) {
            $output->writeln(sprintf('<error>Unknown module "%s".</error>', $module));

            return Command::FAILURE;
        }
        $name = (string) ($input->getOption('name') ?: 'ZeroCompat_' . str_replace('_', '', $module));
        if (!preg_match('/^[A-Z][A-Za-z0-9]*_[A-Z][A-Za-z0-9]*$/', $name)) {
            $output->writeln(sprintf('<error>"%s" is not a module name (Vendor_Module).</error>', $name));

            return Command::FAILURE;
        }
        [$vendor, $short] = explode('_', $name);
        $dir = $this->directoryList->getRoot() . '/app/code/' . $vendor . '/' . $short;
        if (file_exists($dir)) {
            $output->writeln(sprintf('<error>%s already exists.</error>', $dir));

            return Command::FAILURE;
        }

        $templates = [];
        foreach ((array) $input->getOption('template') as $template) {
            $template = ltrim(str_replace($module . '::', '', (string) $template), '/');
            $source = $moduleDir . '/view/frontend/templates/' . $template;
            if (!is_file($source)) {
                $output->writeln(sprintf('<error>%s has no template %s.</error>', $module, $template));

                return Command::FAILURE;
            }
            $templates[$template] = $source;
        }

        foreach ($this->files($module, $name) as $path => $content) {
            $this->write($dir . '/' . $path, $content);
        }
        foreach ($templates as $template => $source) {
            $this->write($dir . '/view/frontend/templates/' . $template, (string) file_get_contents($source));
        }

        $output->writeln(sprintf('Created %s in %s', $name, $dir));
        foreach (array_keys($templates) as $template) {
            $output->writeln(sprintf('  view/frontend/templates/%s (copy of %s::%s, rewrite it for Zero)', $template, $module, $template));
        }
        $output->writeln('Next: bin/magento module:enable ' . $name . ' && bin/magento setup:upgrade,');
        $output->writeln('then rebuild the theme CSS (npm run build:css in app/design/frontend/Zero/default).');

        return Command::SUCCESS;
    }

    private function write(string $file, string $content): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0775, true);
        }
        file_put_contents($file, $content);
    }

    /**
     * The module skeleton, by path.
     *
     * @param string $module
     * @param string $name
     * @return array<string, string>
     */
    private function files(string $module, string $name): array
    {
        return [
            'registration.php' => <<<PHP
<?php
declare(strict_types=1);

use Magento\\Framework\\Component\\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, '{$name}', __DIR__);

PHP,
            'etc/module.xml' => <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:Module/etc/module.xsd">
    <module name="{$name}">
        <sequence>
            <module name="{$module}"/>
            <module name="Zero_Core"/>
        </sequence>
    </module>
</config>

XML,
            'etc/di.xml' => <<<XML
<?xml version="1.0"?>
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:framework:ObjectManager/etc/config.xsd">
    <!-- On Zero themes, {$module}'s templates come from this module where it has them. -->
    <type name="Zero\\Core\\Model\\Compat\\Registry">
        <arguments>
            <argument name="compatModules" xsi:type="array">
                <item name="{$module}" xsi:type="array">
                    <item name="{$name}" xsi:type="string">{$name}</item>
                </item>
            </argument>
        </arguments>
    </type>
</config>

XML,
            'view/frontend/zero/tailwind.css' => <<<CSS
/* Built into the Zero theme's CSS while {$name} is enabled. */
@source "../templates/**/*.phtml";

CSS,
            'view/frontend/layout/.gitkeep' => '',
            'README.md' => <<<MD
# {$name}

Adapts {$module} to the Zero theme.

- Templates: put a template at the same path as in {$module}
  (`view/frontend/templates/...`) and Zero themes render it instead of the
  original. Templates not here keep coming from {$module}.
- Layout: `view/frontend/layout/` merges after {$module}'s layout
  (`<referenceBlock name="..." remove="true"/>`, new blocks, arguments).
- Styles: Tailwind and DaisyUI classes used in this module's templates are
  built into the theme CSS (`view/frontend/zero/tailwind.css`).
- JavaScript: Zero pages don't load RequireJS or Knockout; use HTMX
  attributes or a small script, as the Zero theme does.

MD,
        ];
    }
}
