<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Doctrine\EntityDetails;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * Generates the write-side of an API Platform CRUD (DTO, service, state processor/extension),
 * an optional unit test, and a `.crud-spec.json` sidecar consumed by `@kematjaya/crud-ui-generator`.
 * Assumes the target entity already exists and already carries (or will carry, per the printed
 * next-steps) its own #[ApiResource] attribute — this renderer never edits the entity file itself.
 *
 * @package Kematjaya\CrudMakerBundle\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class ApiCrudRenderer extends AbstractRenderer
{
    /** @var list<string> Doctrine column types treated as system-managed timestamps, excluded from the Input DTO. */
    private const SKIPPED_TYPES = [
        Types::DATETIME_MUTABLE, Types::DATETIME_IMMUTABLE,
        Types::DATETIMETZ_MUTABLE, Types::DATETIMETZ_IMMUTABLE,
        Types::DATE_MUTABLE, Types::DATE_IMMUTABLE,
        Types::TIME_MUTABLE, Types::TIME_IMMUTABLE,
    ];

    public function __construct(ContainerBagInterface $bag, private DoctrineHelper $doctrineHelper)
    {
        parent::__construct($bag);
    }

    public function generate(
        ClassNameDetails $entityClassDetails,
        Generator $generator,
        ?string $ownerProperty,
        ?string $permissionPrefix,
        bool $withAccessControl,
        bool $withTests,
    ): ApiCrudResult {
        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());
        if (null === $entityDoctrineDetails) {
            throw new \RuntimeException(sprintf('"%s" is not a mapped Doctrine entity.', $entityClassDetails->getFullName()));
        }
        if (null === $entityDoctrineDetails->getRepositoryClass()) {
            throw new \RuntimeException(sprintf('"%s" has no repositoryClass configured on #[ORM\Entity] — make:kmj-api-crud needs one to look up entities by id in the write processor.', $entityClassDetails->getShortName()));
        }

        $ownerClassDetails = null;
        if (null !== $ownerProperty) {
            $ownerClassDetails = $this->resolveOwnerClass($generator, $entityClassDetails, $ownerProperty);
        }

        $fields = $this->buildFields($entityDoctrineDetails);
        $entityVar = lcfirst($this->singularize($entityClassDetails->getShortName()));
        $permissionPrefix ??= strtolower($this->pluralize($entityClassDetails->getShortName()));

        // getRepositoryClass() is guaranteed non-null here — guarded above.
        $repositoryClassDetails = $generator->createClassNameDetails('\\'.$entityDoctrineDetails->getRepositoryClass(), 'Repository\\', 'Repository');

        $inputClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'Input', 'Dto\\', 'Input');
        $serviceInterfaceClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'ServiceInterface', 'Service\\', 'ServiceInterface');
        $serviceClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'Service', 'Service\\', 'Service');
        $processorClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'WriteProcessor', 'State\\', 'WriteProcessor');
        $extensionClassDetails = null !== $ownerClassDetails
            ? $generator->createClassNameDetails('CurrentUser'.$entityClassDetails->getRelativeNameWithoutSuffix().'Extension', 'State\\', 'Extension')
            : null;

        $sharedVars = [
            'entity_full_class_name' => $entityClassDetails->getFullName(),
            'entity_class_name' => $entityClassDetails->getShortName(),
            'entity_var' => $entityVar,
            'input_full_class_name' => $inputClassDetails->getFullName(),
            'input_class_name' => $inputClassDetails->getShortName(),
            'fields' => $fields,
            'owner_full_class_name' => $ownerClassDetails?->getFullName(),
            'owner_class_name' => $ownerClassDetails?->getShortName(),
            'owner_property' => $ownerProperty,
            'owner_var' => null !== $ownerProperty ? lcfirst($ownerProperty) : null,
            'with_access_control' => $withAccessControl,
            'permission_prefix' => $permissionPrefix,
            'repository_full_class_name' => $repositoryClassDetails->getFullName(),
            'repository_class_name' => $repositoryClassDetails->getShortName(),
            'repository_var' => lcfirst($this->singularize($repositoryClassDetails->getShortName())),
        ];

        $generator->generateClass(
            $inputClassDetails->getFullName(),
            $this->getPath('api-crud/Input.tpl.php'),
            ['fields' => $fields],
        );

        $generator->generateClass(
            $serviceInterfaceClassDetails->getFullName(),
            $this->getPath('api-crud/ServiceInterface.tpl.php'),
            $sharedVars,
        );

        $generator->generateClass(
            $serviceClassDetails->getFullName(),
            $this->getPath('api-crud/Service.tpl.php'),
            array_merge($sharedVars, [
                'service_interface_full_class_name' => $serviceInterfaceClassDetails->getFullName(),
                'service_interface_class_name' => $serviceInterfaceClassDetails->getShortName(),
            ]),
        );

        $generator->generateClass(
            $processorClassDetails->getFullName(),
            $this->getPath('api-crud/WriteProcessor.tpl.php'),
            array_merge($sharedVars, [
                'service_interface_full_class_name' => $serviceInterfaceClassDetails->getFullName(),
                'service_interface_class_name' => $serviceInterfaceClassDetails->getShortName(),
            ]),
        );

        // $extensionClassDetails is only ever set alongside $ownerClassDetails (see above).
        if (null !== $extensionClassDetails) {
            $generator->generateClass(
                $extensionClassDetails->getFullName(),
                $this->getPath('api-crud/QueryExtension.tpl.php'),
                $sharedVars,
            );
        }

        if ($withTests) {
            $testClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'ServiceTest', 'Tests\\Unit\\Service\\', 'ServiceTest');
            $generator->generateClass(
                $testClassDetails->getFullName(),
                $this->getPath('api-crud/test/ServiceTest.tpl.php'),
                array_merge($sharedVars, [
                    'service_full_class_name' => $serviceClassDetails->getFullName(),
                    'service_class_name' => $serviceClassDetails->getShortName(),
                ]),
            );
        }

        $specPath = $generator->getRootDirectory().'/crud-specs/'.$entityClassDetails->getShortName().'.json';
        $generator->dumpFile($specPath, $this->buildSpecJson($entityClassDetails, $fields, $permissionPrefix, $ownerProperty));

        return new ApiCrudResult($this->buildNextSteps(
            $entityClassDetails,
            $inputClassDetails,
            $processorClassDetails,
            $permissionPrefix,
            $withAccessControl,
            null !== $ownerClassDetails,
            $specPath,
        ));
    }

    private function resolveOwnerClass(Generator $generator, ClassNameDetails $entityClassDetails, string $ownerProperty): ClassNameDetails
    {
        $metadata = $this->doctrineHelper->getMetadata($entityClassDetails->getFullName());
        if (!$metadata instanceof OrmClassMetadata || !isset($metadata->associationMappings[$ownerProperty])) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a mapped association on "%s".', $ownerProperty, $entityClassDetails->getShortName()));
        }

        /** @var class-string $targetEntity */
        $targetEntity = $metadata->associationMappings[$ownerProperty]['targetEntity'];

        return $generator->createClassNameDetails('\\'.$targetEntity, 'Entity\\');
    }

    /**
     * @return list<array{name: string, php_type: string, nullable: bool, doctrine_type: string, length: int|null, constraints: list<string>, spec_type: string}>
     */
    private function buildFields(EntityDetails $entityDoctrineDetails): array
    {
        $identifier = $entityDoctrineDetails->getIdentifier();
        $fields = [];

        foreach ($entityDoctrineDetails->getDisplayFields() as $fieldName => $mapping) {
            if ($fieldName === $identifier) {
                continue;
            }

            $doctrineType = (string) ($mapping['type'] ?? 'string');
            if (in_array($doctrineType, self::SKIPPED_TYPES, true)) {
                continue;
            }

            $phpType = DoctrineHelper::getPropertyTypeForColumn($doctrineType) ?? 'string';
            $nullable = (bool) ($mapping['nullable'] ?? false);
            $length = isset($mapping['length']) ? (int) $mapping['length'] : null;

            $constraints = [];
            if ('string' === $phpType) {
                if (!$nullable) {
                    $constraints[] = "Assert\\NotBlank(normalizer: 'trim')";
                }
                if (null !== $length) {
                    $constraints[] = sprintf('Assert\\Length(max: %d)', $length);
                }
            } elseif (!$nullable) {
                $constraints[] = 'Assert\\NotNull';
            }

            $fields[] = [
                'name' => $fieldName,
                'php_type' => ($nullable ? '?' : '').$phpType,
                'nullable' => $nullable,
                'doctrine_type' => $doctrineType,
                'length' => $length,
                'constraints' => $constraints,
                'spec_type' => $this->toSpecType($phpType, $doctrineType),
            ];
        }

        return $fields;
    }

    private function toSpecType(string $phpType, string $doctrineType): string
    {
        return match (true) {
            Types::TEXT === $doctrineType => 'textarea',
            'bool' === $phpType => 'boolean',
            'int' === $phpType, 'float' === $phpType => 'number',
            default => 'text',
        };
    }

    /**
     * @param list<array{name: string, spec_type: string, nullable: bool, length: int|null}> $fields
     */
    private function buildSpecJson(ClassNameDetails $entityClassDetails, array $fields, string $permissionPrefix, ?string $ownerProperty): string
    {
        $spec = [
            'entity' => $entityClassDetails->getShortName(),
            'permissionPrefix' => $permissionPrefix,
            'ownerProperty' => $ownerProperty,
            'fields' => array_map(
                static fn (array $f) => [
                    'name' => $f['name'],
                    'type' => $f['spec_type'],
                    'required' => !$f['nullable'],
                    'maxLength' => $f['length'],
                ],
                $fields,
            ),
        ];

        return (string) json_encode($spec, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES).\PHP_EOL;
    }

    /**
     * @return list<string>
     */
    private function buildNextSteps(
        ClassNameDetails $entityClassDetails,
        ClassNameDetails $inputClassDetails,
        ClassNameDetails $processorClassDetails,
        string $permissionPrefix,
        bool $withAccessControl,
        bool $hasOwner,
        string $specPath,
    ): array {
        $steps = [
            'Tambahkan #[ApiResource] ke '.$entityClassDetails->getShortName().' (belum diubah otomatis):',
            '',
            '    #[ApiResource(',
            '        operations: [',
            '            new GetCollection(),',
            '            new Get(),',
            '            new Post(input: '.$inputClassDetails->getShortName().'::class, processor: '.$processorClassDetails->getShortName().'::class),',
            '            new Put(input: '.$inputClassDetails->getShortName().'::class, processor: '.$processorClassDetails->getShortName().'::class),',
            $withAccessControl
                ? '            new Delete(security: "is_granted(\''.$permissionPrefix.'.delete\')"),'
                : '            new Delete(),',
            '        ],',
            '    )]',
            '',
        ];

        if ($hasOwner) {
            $steps[] = 'CurrentUser'.$entityClassDetails->getShortName().'Extension ter-generate — pastikan ke-tag otomatis via autoconfigure (default kalau App\\ resource src/ mencakup State/).';
            $steps[] = '';
        }

        if ($withAccessControl) {
            $steps[] = 'Tambahkan permission key ke config/permissions/default.yaml (kematjaya/access-control-bundle):';
            $steps[] = '    gated: true, actions: { create: ..., edit: ..., delete: ... } dengan prefix "'.$permissionPrefix.'"';
            $steps[] = 'Lalu jalankan: bin/console kematjaya:access-control:sync';
            $steps[] = '';
        }

        $steps[] = 'Cek Service yang di-generate — constructor/update() entity diasumsikan urutan parameter sama dengan urutan field Doctrine metadata; sesuaikan manual kalau beda.';
        $steps[] = 'Field bertipe date/datetime otomatis di-skip dari Input DTO — tambahkan manual kalau memang perlu jadi input user.';
        $steps[] = 'Spec untuk @kematjaya/crud-ui-generator ditulis ke: '.$specPath;

        return $steps;
    }
}
