<?php

/**
 * Description of MakeFilter
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\MakerBundle\Renderer\FormFilterTypeRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;

class MakeFilter extends AbstractMaker{
    
    private $entityHelper;
    private $formTypeRenderer;
    
    public function __construct(DoctrineHelper $entityHelper, FormFilterTypeRenderer $formTypeRenderer)
    {
        $this->entityHelper = $entityHelper;
        $this->formTypeRenderer = $formTypeRenderer;
    }
    
    public static function getCommandName(): string
    {
        return 'make:kmj-filter';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig) {
        $command
            ->setDescription('Creates a new form filter class')
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('The name of the form filter class (e.g. <fg=yellow>%sFilterType</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('bound-class', InputArgument::OPTIONAL, 'The name of Entity or fully qualified model class name that the new form will be bound to (empty for none)')
        ;
        $inputConfig->setArgumentAsNonInteractive('bound-class');
    }

    public function configureDependencies(DependencyBuilder $dependencies) {
        $dependencies->addClassDependency(
            DoctrineBundle::class, 'orm', false
        );
    }

    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (null === $input->getArgument('bound-class')) {
            $argument = $command->getDefinition()->getArgument('bound-class');
            $entities = $this->entityHelper->getEntitiesForAutocomplete();
            $question = new Question($argument->getDescription());
            $question->setValidator(function ($answer) use ($entities) {return Validator::existsOrNull($answer, $entities); });
            $question->setAutocompleterValues($entities);
            $question->setMaxAttempts(3);
            $input->setArgument('bound-class', $io->askQuestion($question));
        }
    }
    
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator) {
        $formClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'), 'Filter\\', 'FilterType'
        );
        
        $formFields = ['field_name' => null];
        $boundClass = $input->getArgument('bound-class');
        $boundClassDetails = null;
        if (null !== $boundClass) {
            $boundClassDetails = $generator->createClassNameDetails(
                $boundClass,
                'Entity\\'
            );
            $doctrineEntityDetails = $this->entityHelper->createDoctrineDetails($boundClassDetails->getFullName());
            if (null !== $doctrineEntityDetails) {
                $formFields = $doctrineEntityDetails->getFormFields();
            } else {
                $classDetails = new ClassDetails($boundClassDetails->getFullName());
                $formFields = $classDetails->getFormFields();
            }
        }
        
        $this->formTypeRenderer->render(
            $formClassNameDetails,
            $formFields,
            $boundClassDetails
        );
        $generator->writeChanges();
        $this->writeSuccessMessage($io);
    }

}
