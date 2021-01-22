<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Question\Question;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\BrowserKit\History;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Validator;

/**
 * @package App\Maker\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class CRUDUnitTestMaker extends AbstractMaker
{
    /**
     * 
     * @var DoctrineHelper
     */
    private $doctrineHelper;
    
    public function __construct(DoctrineHelper $doctrineHelper) 
    {
        $this->doctrineHelper = $doctrineHelper;
    }
    
    public static function getCommandName(): string
    {
        return 'make:kmj-functional-test';
    }
    
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator) 
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );
        
        $testClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('entity-class'),
            'Tests\\Controller\\',
            'ControllerTest'
        );
        
        $baseTemplatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton';
        
        $generator->generateClass(
            $testClassNameDetails->getFullName(),
            $baseTemplatePath . DIRECTORY_SEPARATOR . 'test/Controller.tpl.php',
            [
                'entity_full_class_name' => $entityClassDetails->getFullName(),
                'entity_var_singular' => lcfirst($entityClassDetails->getShortName()), 
                'entity_class_name' => $entityClassDetails->getShortName(),
                'route_name' => Str::asRouteName($entityClassDetails->getRelativeNameWithoutSuffix()),
                'web_assertions_are_available' => trait_exists(WebTestAssertionsTrait::class),
                'panther_is_available' => trait_exists(PantherTestCaseTrait::class),
                'include_filter' => $input->getArgument('include-filter'),
                'is_modal' => $input->getArgument('modal-form')
            ]
        );
        
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Open your new test class and start customizing it.',
            'Find the documentation at <fg=yellow>https://symfony.com/doc/current/testing.html#functional-tests</>',
        ]);
    }
    
    public function configureCommand(Command $command, InputConfiguration $inputConfig)
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
    
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
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
            $value = $io->askQuestion($question);
            $input->setArgument('include-filter', 'y' == strtolower($value) or 'yes' == strtolower($value) ? true: false);
        }
        
        if (null === $input->getArgument('modal-form')) {
            $argument = $command->getDefinition()->getArgument('modal-form');
            $question = new Question($argument->getDescription(), 'no');
            $value = $io->askQuestion($question);
            $input->setArgument('modal-form', 'y' == strtolower($value) or 'yes' == strtolower($value) ? true: false);
        }
    }
    
    public function configureDependencies(DependencyBuilder $dependencies) 
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
