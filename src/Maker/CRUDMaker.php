<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\CrudMakerBundle\Renderer\ControllerRenderer;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Validation;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\Security\Csrf\CsrfTokenManager;


/**
 * @package App\Maker\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class CRUDMaker extends AbstractMaker
{
    use InputArgumentTrait;
    
    /**
     * 
     * @var ControllerRenderer
     */
    private $controllerRenderer;
    
    
    /**
     * 
     * @var DoctrineHelper
     */
    private $doctrineHelper;
    
    public function __construct(ControllerRenderer $controllerRenderer, DoctrineHelper $doctrineHelper) 
    {
        $this->controllerRenderer = $controllerRenderer;
        $this->doctrineHelper = $doctrineHelper;
    }
    
    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription('Creates CRUD for Doctrine entity class provide by kematjaya/crud-maker-bundle')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('namespace', InputArgument::OPTIONAL, sprintf("additional controller namespace (<fg=yellow>%s</>)", "enter if default"))
            ->addArgument('include-filter', InputArgument::OPTIONAL, sprintf('include filter ?'))
            ->addArgument('modal-form', InputArgument::OPTIONAL, sprintf('modal form ?'))
            ->addArgument("theme", InputArgument::OPTIONAL, sprintf("view theme, available (<fg=yellow>%s</>)", implode(", ", $this->controllerRenderer->getAvailableThemes())))
        ;

        $inputConfig->setArgumentAsNonInteractive('entity-class');
        $inputConfig->setArgumentAsNonInteractive('namespace');
        $inputConfig->setArgumentAsNonInteractive('include-filter');
        $inputConfig->setArgumentAsNonInteractive('modal-form');
        $inputConfig->setArgumentAsNonInteractive('theme');
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
        
        if (null === $input->getArgument('namespace')) {
            $argument = $command->getDefinition()->getArgument('namespace');
            $question = new Question($argument->getDescription());
            $value = $io->askQuestion($question);
            $input->setArgument('namespace', null !== $value ? $value : "-");
        }
        
        if (null === $input->getArgument('theme')) {
            $argument = $command->getDefinition()->getArgument('theme');
            $question = new Question($argument->getDescription(), $this->controllerRenderer->getConfig()['templates']['theme']);
            $question->setAutocompleterValues($this->controllerRenderer->getAvailableThemes());
            $value = $io->askQuestion($question);
            $input->setArgument('theme', $value);
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
            AbstractType::class,
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator'
        );

        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig-bundle'
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm-pack'
        );

        $dependencies->addClassDependency(
            CsrfTokenManager::class,
            'security-csrf'
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityConfig = $this->controllerRenderer->getConfig()['entity'];
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            $entityConfig['namespace_prefix'],
            $entityConfig['suffix']
        );
        
        $controllerClassDetails = $this->controllerRenderer->generate(
            $entityClassDetails, 
            $generator, 
            $input->getArgument('namespace'),
            $input->getArgument('theme'),
            $this->isYes($input->getArgument('include-filter')),
            $this->isYes($input->getArgument('modal-form'))
        );
        
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $namespace = trim((string) $input->getArgument('namespace'), " \\");
        $routeBase = ('' !== $namespace && '-' !== $namespace ? $namespace . '\\' : '') . $controllerClassDetails->getRelativeNameWithoutSuffix();
        $io->text(sprintf('Next: Check your new CRUD by going to <fg=yellow>%s.html</>', Str::asRoutePath($routeBase)));
    }

    public static function getCommandName(): string 
    {
        return 'make:kmj-crud';
    }

    /**
     * 
     * @return string
     */
    public static function getCommandDescription(): string
    {
        return 'generator for crud with bootstrap';
    }
}
