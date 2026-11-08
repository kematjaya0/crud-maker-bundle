<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\CrudMakerBundle\Renderer\ControllerRenderer;
use Symfony\Bundle\FrameworkBundle\Test\WebTestAssertionsTrait;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\Panther\PantherTestCaseTrait;
use Symfony\Component\Routing\Route;

/**
 * @package App\Maker\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class CRUDUnitTestMaker extends AbstractMaker
{
    use InputArgumentTrait;

    public function __construct(
        private readonly DoctrineHelper $doctrineHelper,
        private readonly ControllerRenderer $controllerRenderer,
    ) {}

    public static function getCommandName(): string
    {
        return 'make:kmj-functional-test';
    }

    public static function getCommandDescription(): string
    {
        return 'generator for functional test of CRUD controller';
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityConfig = $this->controllerRenderer->getConfig()['entity'];
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            $entityConfig['namespace_prefix'],
            $entityConfig['suffix']
        );

        $testClassNameDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix(),
            'Tests\\Controller\\',
            'ControllerTest'
        );
        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());

        $baseTemplatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton';

        $generator->generateClass(
            $testClassNameDetails->getFullName(),
            $baseTemplatePath . DIRECTORY_SEPARATOR . 'test/Controller.tpl.php',
            [
                'entity_full_class_name' => $entityClassDetails->getFullName(),
                'entity_var_singular' => lcfirst($entityClassDetails->getShortName()),
                'entity_class_name' => $entityClassDetails->getShortName(),
                'route_name' => Str::asRouteName($entityClassDetails->getRelativeNameWithoutSuffix()),
                'entity_identifier' => $entityDoctrineDetails ? $entityDoctrineDetails->getIdentifier() : 'id',
                'web_assertions_are_available' => trait_exists(WebTestAssertionsTrait::class),
                'panther_is_available' => trait_exists(PantherTestCaseTrait::class),
                'include_filter' => $this->isYes($input->getArgument('include-filter')),
                'is_modal' => $this->isYes($input->getArgument('modal-form')),
            ]
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Open your new test class and start customizing it.',
            'Find the documentation at <fg=yellow>https://symfony.com/doc/current/testing.html#functional-tests</>',
        ]);
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription('Creates a new unit test class for CRUD controller')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('include-filter', InputArgument::OPTIONAL, sprintf('include filter ?'))
            ->addArgument('modal-form', InputArgument::OPTIONAL, sprintf('modal form ?'))
        ;

        $inputConfig->setArgumentAsNonInteractive('entity-class');
        $inputConfig->setArgumentAsNonInteractive('include-filter');
        $inputConfig->setArgumentAsNonInteractive('modal-form');
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command): void
    {
        if (null === $input->getArgument('entity-class')) {
            $argument = $command->getDefinition()->getArgument('entity-class');

            $entities = $this->doctrineHelper->getEntitiesForAutocomplete();

            $question = new Question($argument->getDescription());
            $question->setAutocompleterValues($entities);

            $value = $io->askQuestion($question);

            $input->setArgument('entity-class', $value);
        }

        if (null === $input->getArgument('include-filter')) {
            $argument = $command->getDefinition()->getArgument('include-filter');
            $question = new Question($argument->getDescription(), 'no');
            $input->setArgument('include-filter', $io->askQuestion($question));
        }

        if (null === $input->getArgument('modal-form')) {
            $argument = $command->getDefinition()->getArgument('modal-form');
            $question = new Question($argument->getDescription(), 'no');
            $input->setArgument('modal-form', $io->askQuestion($question));
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        $dependencies->addClassDependency(
            Route::class,
            'router'
        );
        $dependencies->addClassDependency(
            History::class,
            'browser-kit',
            true,
            true
        );
        $dependencies->addClassDependency(
            CssSelectorConverter::class,
            'css-selector',
            true,
            true
        );
    }
}
