<?php

namespace Kematjaya\CrudMakerBundle\Twig\Maker;

use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

/**
 * One-time setup so `make:kmj-filter`/`make:kmj-crud` work out of the box: makes sure
 * KnpPaginatorBundle has a config file, that Twig's form theme matches the chosen
 * bootstrap version, and that the bundles this bundle's generated code needs at runtime
 * (not just in dev) are registered for every environment.
 *
 * @package App\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class InstallMaker extends AbstractMaker
{
    /**
     * Bundles the generated controllers/forms depend on at runtime — not dev tooling —
     * so they must be enabled in every environment, not just 'dev'/'test'.
     *
     * @var array<int, string>
     */
    private const MANAGED_BUNDLES = [
        'Spiriit\\Bundle\\FormFilterBundle\\SpiriitFormFilterBundle',
        'Knp\\Bundle\\PaginatorBundle\\KnpPaginatorBundle',
    ];

    /**
     * @var array<string, string>
     */
    private const PAGINATION_TEMPLATES = [
        'bootstrap-3' => '@KnpPaginator/Pagination/twitter_bootstrap_v3_pagination.html.twig',
        'bootstrap-4' => '@KnpPaginator/Pagination/twitter_bootstrap_v4_pagination.html.twig',
        'bootstrap-5' => '@KnpPaginator/Pagination/twitter_bootstrap_v5_pagination.html.twig',
    ];

    /**
     * @var array<string, string>
     */
    private const FORM_THEMES = [
        'bootstrap-3' => 'bootstrap_3_layout.html.twig',
        'bootstrap-4' => 'bootstrap_4_layout.html.twig',
        'bootstrap-5' => 'bootstrap_5_layout.html.twig',
    ];

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription('Wires up the config make:kmj-filter/make:kmj-crud need (KnpPaginatorBundle config, Twig form theme, bundle registration)')
            ->addArgument('theme', InputArgument::OPTIONAL, sprintf('View theme, available (<fg=yellow>%s</>)', implode(', ', array_keys(self::PAGINATION_TEMPLATES))))
        ;

        $inputConfig->setArgumentAsNonInteractive('theme');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (null === $input->getArgument('theme')) {
            $argument = $command->getDefinition()->getArgument('theme');
            $question = new Question($argument->getDescription(), 'bootstrap-5');
            $value = $io->askQuestion($question);
            $input->setArgument('theme', $value);
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $theme = $input->getArgument('theme');
        if (!isset(self::PAGINATION_TEMPLATES[$theme])) {
            $io->error(sprintf('Unknown theme "%s". Available: %s', $theme, implode(', ', array_keys(self::PAGINATION_TEMPLATES))));

            return;
        }

        $projectDir = $generator->getRootDirectory();

        $this->ensureKnpPaginatorConfig($projectDir, $theme, $io);
        $this->ensureTwigFormTheme($projectDir, $theme, $io);
        $this->ensureBundlesRegistered($projectDir, $io);

        $this->writeSuccessMessage($io);

        $io->text('Next: run <fg=yellow>make:kmj-crud</> for an existing Doctrine entity.');
    }

    public static function getCommandName(): string
    {
        return 'make:kmj-install';
    }

    public static function getCommandDescription(): string
    {
        return 'one-time setup for kematjaya/crud-maker-twig-bundle (config + bundle registration)';
    }

    private function ensureKnpPaginatorConfig(string $projectDir, string $theme, ConsoleStyle $io): void
    {
        $path = $projectDir.'/config/packages/knp_paginator.yaml';
        if (is_file($path)) {
            $io->text('config/packages/knp_paginator.yaml already exists — leaving it as is.');

            return;
        }

        $template = self::PAGINATION_TEMPLATES[$theme];
        $content = <<<YAML
            knp_paginator:
                default_options:
                    sort_field_name: sort           # sort field query parameter name
                    sort_direction_name: mode       # sort direction query parameter name
                template:
                    pagination: '{$template}'

            YAML;

        $this->dumpFile($path, $content);
        $io->text('Created config/packages/knp_paginator.yaml');
    }

    private function ensureTwigFormTheme(string $projectDir, string $theme, ConsoleStyle $io): void
    {
        $path = $projectDir.'/config/packages/twig.yaml';
        $desiredLayout = self::FORM_THEMES[$theme];

        if (!is_file($path)) {
            $content = <<<YAML
                twig:
                    form_themes:
                        - '{$desiredLayout}'

                YAML;

            $this->dumpFile($path, $content);
            $io->text('Created config/packages/twig.yaml with form_themes: '.$desiredLayout);

            return;
        }

        $config = Yaml::parseFile($path) ?? [];
        $formThemes = $config['twig']['form_themes'] ?? [];

        if (in_array($desiredLayout, $formThemes, true)) {
            $io->text('config/packages/twig.yaml already uses '.$desiredLayout.' — nothing to do.');

            return;
        }

        // Drop any other bootstrap_*_layout.html.twig entry so the two don't clash, keep everything else untouched.
        $formThemes = array_values(array_filter($formThemes, static fn ($layout) => 1 !== preg_match('/^bootstrap_\d+_layout\.html\.twig$/', (string) $layout)));
        $formThemes[] = $desiredLayout;

        $config['twig']['form_themes'] = $formThemes;

        file_put_contents($path, Yaml::dump($config, 4, 4));
        $io->text('Updated config/packages/twig.yaml form_themes to '.$desiredLayout.' (file rewritten via the YAML component — any comments in it were dropped).');
    }

    private function ensureBundlesRegistered(string $projectDir, ConsoleStyle $io): void
    {
        $path = $projectDir.'/config/bundles.php';
        if (!is_file($path)) {
            $io->warning('config/bundles.php not found — skipping bundle registration check.');

            return;
        }

        $content = file_get_contents($path);
        $original = $content;

        foreach (self::MANAGED_BUNDLES as $fqcn) {
            $needle = $fqcn.'::class';
            $pattern = '/^(\s*)'.preg_quote($needle, '/').'\s*=>\s*\[[^\]]*\],?\s*$/m';

            if (preg_match($pattern, $content, $matches)) {
                if (str_contains($matches[0], "'all' => true") || str_contains($matches[0], '"all" => true')) {
                    $io->text($fqcn.' already registered for all environments.');

                    continue;
                }

                $content = preg_replace($pattern, $matches[1]."{$needle} => ['all' => true],", $content, 1);
                $io->text('Updated '.$fqcn.' to [\'all\' => true] in config/bundles.php');

                continue;
            }

            $content = preg_replace('/\n\];\s*$/', "\n    {$needle} => ['all' => true],\n];\n", $content, 1);
            $io->text('Registered '.$fqcn.' in config/bundles.php');
        }

        if ($content !== $original) {
            file_put_contents($path, $content);
        }
    }

    private function dumpFile(string $path, string $content): void
    {
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);
    }
}
