<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\CrudMakerBundle\Renderer\ControllerRenderer;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
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
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Validation;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;


/**
 * @package App\Maker\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class CRUDMaker extends AbstractMaker
{
    
    private $controllerRenderer;
    
    private $doctrineHelper;
    
    private $formTypeRenderer;
    
    public function __construct(ControllerRenderer $controllerRenderer, FormTypeRenderer $formTypeRenderer, DoctrineHelper $doctrineHelper) 
    {
        $this->controllerRenderer = $controllerRenderer;
        $this->formTypeRenderer = $formTypeRenderer;
        $this->doctrineHelper = $doctrineHelper;
    }
    
    public function configureCommand(Command $command, InputConfiguration $inputConfig) 
    {
        $command
            ->setDescription('Creates CRUD for Doctrine entity class provide by kematjaya/crud-maker-bundle')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('include-filter', InputArgument::OPTIONAL, sprintf('include filter ?'))
        ;

        $inputConfig->setArgumentAsNonInteractive('entity-class');
        $inputConfig->setArgumentAsNonInteractive('include-filter');
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
    }
    
    public function configureDependencies(DependencyBuilder $dependencies) 
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

        $dependencies->addClassDependency(
            ParamConverter::class,
            'annotations'
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator) 
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );
        
        $controllerClassDetails = $this->controllerRenderer->generate($entityClassDetails, $generator, $input->getArgument('include-filter'));
        
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text(sprintf('Next: Check your new CRUD by going to <fg=yellow>%s/</>', Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix())));
    }

    public static function getCommandName(): string 
    {
        return 'make:kmj-crud';
    }

}
