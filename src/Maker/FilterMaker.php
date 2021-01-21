<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\CrudMakerBundle\Renderer\FilterTypeRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;

/**
 * @package App\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class FilterMaker extends AbstractMaker
{
    private $entityHelper;
    private $filterTypeRenderer;
    
    public function __construct(DoctrineHelper $entityHelper, FilterTypeRenderer $filterTypeRenderer) 
    {
        $this->entityHelper = $entityHelper;
        $this->filterTypeRenderer = $filterTypeRenderer;
    }
    
    public function configureCommand(Command $command, InputConfiguration $inputConfig) 
    {
        $command
            ->setDescription('Creates a new form filter class by lexik form filter bundle')
            ->addArgument('name', InputArgument::OPTIONAL, sprintf('The name of the filter class (e.g. <fg=yellow>%sFilterType</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('bound-class', InputArgument::OPTIONAL, 'The name of Entity or fully qualified model class name that the new form will be bound to (empty for none)')
        ;

        $inputConfig->setArgumentAsNonInteractive('bound-class');
    }

    public function configureDependencies(DependencyBuilder $dependencies) 
    {
        
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
    
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator) 
    {
        $formClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('name'),
            'Filter\\',
            'FilterType'
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
            $identifierField = null;
            if (null !== $doctrineEntityDetails) {
                $formFields = $doctrineEntityDetails->getFormFields();
                $identifierField = $doctrineEntityDetails->getIdentifier();
            } else {
                $classDetails = new ClassDetails($boundClassDetails->getFullName());
                $formFields = $classDetails->getFormFields();
            }
        }
        
        $this->filterTypeRenderer->render(
            $generator,
            $formClassNameDetails,
            $formFields,
            $boundClassDetails,
            $identifierField
        );
        
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text([
            'Next: Add fields to your filter and start using it.',
            'Find the documentation at <fg=yellow>https://github.com/lexik/LexikFormFilterBundle</>',
        ]);
    }

    public static function getCommandName(): string 
    {
        return 'make:kmj-filter';
    }

}
