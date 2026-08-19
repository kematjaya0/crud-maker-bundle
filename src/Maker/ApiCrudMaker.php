<?php

namespace Kematjaya\CrudMakerBundle\Maker;

use Kematjaya\CrudMakerBundle\Renderer\ApiCrudRenderer;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Command\Command;
use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;

/**
 * Generates the write-side (DTO, service, API Platform state processor/extension) plus
 * an optional unit test and a `.crud-spec.json` sidecar for `@kematjaya/crud-ui-generator`,
 * for an EXISTING Doctrine entity that already carries #[ApiResource] (see make:entity /
 * hand-written entity). This does not touch the entity itself — see the printed next-steps
 * for the #[ApiResource] attribute block to add by hand.
 *
 * @package Kematjaya\CrudMakerBundle\Maker
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class ApiCrudMaker extends AbstractMaker
{
    public function __construct(private ApiCrudRenderer $renderer, private DoctrineHelper $doctrineHelper)
    {
    }

    public static function getCommandName(): string
    {
        return 'make:kmj-api-crud';
    }

    public static function getCommandDescription(): string
    {
        return 'generator for API Platform CRUD (DTO + service + state processor) with a frontend spec sidecar';
    }

    public function configureCommand(Command $command, InputConfiguration $inputConfig): void
    {
        $command
            ->setDescription($this->getCommandDescription())
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD for (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
            ->addArgument('owner-property', InputArgument::OPTIONAL, 'Nama property owner (association ke User), kosongkan/"-" kalau entity tidak punya owner')
            ->addArgument('permission-prefix', InputArgument::OPTIONAL, 'Prefix permission key (mis. "notes" -> notes.create/notes.edit), kosongkan untuk ditebak dari nama entity')
            ->addArgument('with-access-control', InputArgument::OPTIONAL, 'Cek permission via kematjaya/access-control-bundle di WriteProcessor? (y/n)')
            ->addArgument('with-tests', InputArgument::OPTIONAL, 'Generate unit test untuk service? (y/n)')
            ->addArgument('searchable-fields', InputArgument::OPTIONAL, 'Field yang bisa dicari (search) di frontend, pisah dengan koma (mis. "title"), kosongkan/"-" kalau tidak ada')
            ->addArgument('write-entity-attributes', InputArgument::OPTIONAL, 'Tambahkan #[ApiResource]/#[ApiFilter] otomatis ke entity? (y/n) — kalau tidak, di-print untuk ditempel manual')
        ;

        $inputConfig->setArgumentAsNonInteractive('entity-class');
        $inputConfig->setArgumentAsNonInteractive('owner-property');
        $inputConfig->setArgumentAsNonInteractive('permission-prefix');
        $inputConfig->setArgumentAsNonInteractive('with-access-control');
        $inputConfig->setArgumentAsNonInteractive('with-tests');
        $inputConfig->setArgumentAsNonInteractive('searchable-fields');
        $inputConfig->setArgumentAsNonInteractive('write-entity-attributes');
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

        if (null === $input->getArgument('owner-property')) {
            $argument = $command->getDefinition()->getArgument('owner-property');
            $question = new Question($argument->getDescription(), '-');
            $value = $io->askQuestion($question);
            $input->setArgument('owner-property', null !== $value ? $value : '-');
        }

        if (null === $input->getArgument('permission-prefix')) {
            $argument = $command->getDefinition()->getArgument('permission-prefix');
            $question = new Question($argument->getDescription(), '-');
            $value = $io->askQuestion($question);
            $input->setArgument('permission-prefix', null !== $value ? $value : '-');
        }

        if (null === $input->getArgument('with-access-control')) {
            $question = new ConfirmationQuestion('Cek permission via kematjaya/access-control-bundle di WriteProcessor?', false);
            $input->setArgument('with-access-control', $io->askQuestion($question));
        }

        if (null === $input->getArgument('with-tests')) {
            $question = new ConfirmationQuestion('Generate unit test untuk service?', true);
            $input->setArgument('with-tests', $io->askQuestion($question));
        }

        if (null === $input->getArgument('searchable-fields')) {
            $argument = $command->getDefinition()->getArgument('searchable-fields');
            $question = new Question($argument->getDescription(), '-');
            $value = $io->askQuestion($question);
            $input->setArgument('searchable-fields', null !== $value ? $value : '-');
        }

        if (null === $input->getArgument('write-entity-attributes')) {
            $question = new ConfirmationQuestion('Tambahkan #[ApiResource]/#[ApiFilter] otomatis ke entity?', true);
            $input->setArgument('write-entity-attributes', $io->askQuestion($question));
        }
    }

    public function configureDependencies(DependencyBuilder $dependencies): void
    {
        // api-platform/symfony is intentionally not a hard dependency of this bundle (unlike
        // make:kmj-crud's twig/form/csrf deps) — a plain string avoids requiring the class to
        // actually be autoloadable just to reference it here.
        $dependencies->addClassDependency(
            'ApiPlatform\\Metadata\\ApiResource',
            'api-platform/symfony'
        );

        $dependencies->addClassDependency(
            OrmClassMetadata::class,
            'orm'
        );
    }

    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator): void
    {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );

        $ownerProperty = $input->getArgument('owner-property');
        $ownerProperty = (is_string($ownerProperty) && '-' !== $ownerProperty && '' !== trim($ownerProperty)) ? trim($ownerProperty) : null;

        $permissionPrefix = $input->getArgument('permission-prefix');
        $permissionPrefix = (is_string($permissionPrefix) && '-' !== $permissionPrefix && '' !== trim($permissionPrefix)) ? trim($permissionPrefix) : null;

        $searchableFieldsRaw = $input->getArgument('searchable-fields');
        $searchableFields = [];
        if (is_string($searchableFieldsRaw) && '-' !== $searchableFieldsRaw && '' !== trim($searchableFieldsRaw)) {
            $searchableFields = array_values(array_filter(array_map('trim', explode(',', $searchableFieldsRaw))));
        }

        $result = $this->renderer->generate(
            $entityClassDetails,
            $generator,
            $ownerProperty,
            $permissionPrefix,
            (bool) $input->getArgument('with-access-control'),
            (bool) $input->getArgument('with-tests'),
            $searchableFields,
            (bool) $input->getArgument('write-entity-attributes'),
        );

        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text($result->nextSteps);
    }
}
