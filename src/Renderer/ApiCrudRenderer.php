<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping\ClassMetadata as OrmClassMetadata;
use Kematjaya\CrudMakerBundle\Util\EntityAttributeWriter;
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

    public function __construct(ContainerBagInterface $bag, private DoctrineHelper $doctrineHelper, private EntityAttributeWriter $entityAttributeWriter)
    {
        parent::__construct($bag);
    }

    /**
     * @param list<string> $searchableFields
     */
    public function generate(
        ClassNameDetails $entityClassDetails,
        Generator $generator,
        ?string $ownerProperty,
        ?string $permissionPrefix,
        bool $withAccessControl,
        bool $withTests,
        array $searchableFields = [],
        bool $writeEntityAttributes = false,
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
        $timestampField = $this->findTimestampField($entityDoctrineDetails);
        $timestampFieldClass = $this->timestampFieldClass($entityDoctrineDetails, $timestampField);
        $idType = $this->detectIdType($entityDoctrineDetails);
        $writeMode = $this->detectWriteMode($entityClassDetails->getFullName(), $fields, $ownerProperty);
        $entityVar = lcfirst($this->singularize($entityClassDetails->getShortName()));
        $permissionPrefix ??= strtolower($this->pluralize($entityClassDetails->getShortName()));

        $fieldNames = array_column($fields, 'name');
        $searchableFields = array_values(array_intersect($searchableFields, $fieldNames));

        // getRepositoryClass() is guaranteed non-null here — guarded above.
        $repositoryClassDetails = $generator->createClassNameDetails('\\'.$entityDoctrineDetails->getRepositoryClass(), 'Repository\\', 'Repository');

        $inputClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'Input', 'Dto\\', 'Input');
        $serviceInterfaceClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'ServiceInterface', 'Service\\', 'ServiceInterface');
        $serviceClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'Service', 'Service\\', 'Service');
        $processorClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'WriteProcessor', 'State\\', 'WriteProcessor');
        $extensionClassDetails = null !== $ownerClassDetails
            ? $generator->createClassNameDetails('CurrentUser'.$entityClassDetails->getRelativeNameWithoutSuffix().'Extension', 'State\\', 'Extension')
            : null;
        $exportClassDetails = $generator->createClassNameDetails($entityClassDetails->getRelativeNameWithoutSuffix().'ExportDataController', 'Controller\\', 'ExportDataController');

        $sharedVars = [
            'entity_full_class_name' => $entityClassDetails->getFullName(),
            'entity_class_name' => $entityClassDetails->getShortName(),
            'entity_var' => $entityVar,
            'input_full_class_name' => $inputClassDetails->getFullName(),
            'input_class_name' => $inputClassDetails->getShortName(),
            'fields' => $fields,
            'searchable_fields' => $searchableFields,
            'timestamp_field' => $timestampField,
            'timestamp_field_class' => $timestampFieldClass,
            'write_mode' => $writeMode,
            'owner_full_class_name' => $ownerClassDetails?->getFullName(),
            'owner_class_name' => $ownerClassDetails?->getShortName(),
            'owner_property' => $ownerProperty,
            'owner_var' => null !== $ownerProperty ? lcfirst($ownerProperty) : null,
            'with_access_control' => $withAccessControl,
            'permission_prefix' => $permissionPrefix,
            'repository_full_class_name' => $repositoryClassDetails->getFullName(),
            'repository_class_name' => $repositoryClassDetails->getShortName(),
            'repository_var' => lcfirst($this->singularize($repositoryClassDetails->getShortName())),
            'export_route_path' => '/api/'.$permissionPrefix.'/export-data',
            'export_route_name' => 'app_'.str_replace('-', '_', $permissionPrefix).'_export_data',
            'export_limiter_name' => str_replace('-', '_', $permissionPrefix).'_export_data',
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

        $generator->generateClass(
            $exportClassDetails->getFullName(),
            $this->getPath('api-crud/ExportDataController.tpl.php'),
            $sharedVars,
        );

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
        $generator->dumpFile($specPath, $this->buildSpecJson($entityClassDetails, $fields, $permissionPrefix, $ownerProperty, $searchableFields, $timestampField, $idType));

        $attributesWritten = false;
        $attributeWriteError = null;
        if ($writeEntityAttributes) {
            $attributeWriteError = $this->tryWriteEntityAttributes(
                $generator,
                $entityClassDetails,
                $inputClassDetails,
                $processorClassDetails,
                $permissionPrefix,
                $withAccessControl,
                $searchableFields,
            );
            $attributesWritten = null === $attributeWriteError;
        }

        return new ApiCrudResult($this->buildNextSteps(
            $entityClassDetails,
            $inputClassDetails,
            $processorClassDetails,
            $permissionPrefix,
            $withAccessControl,
            null !== $ownerClassDetails,
            $specPath,
            $searchableFields,
            $sharedVars['export_route_path'],
            $sharedVars['export_limiter_name'],
            $attributesWritten,
            $attributeWriteError,
        ));
    }

    /**
     * Attempts to add `#[ApiResource(...)]` (and, if there are searchable fields,
     * `#[ApiFilter(...)]`) directly to the entity file via {@see EntityAttributeWriter}. Returns
     * null on success, or a human-readable reason on failure (entity already has one of these
     * attributes, or the file couldn't be parsed) — callers fall back to printing the manual
     * paste-in-by-hand instructions in that case, rather than treating it as a hard error for
     * the whole `make:kmj-api-crud` run.
     *
     * @param list<string> $searchableFields
     */
    private function tryWriteEntityAttributes(
        Generator $generator,
        ClassNameDetails $entityClassDetails,
        ClassNameDetails $inputClassDetails,
        ClassNameDetails $processorClassDetails,
        string $permissionPrefix,
        bool $withAccessControl,
        array $searchableFields,
    ): ?string {
        $entityFullClassName = $entityClassDetails->getFullName();

        try {
            $reflection = new \ReflectionClass($entityFullClassName);
        } catch (\ReflectionException $e) {
            return $e->getMessage();
        }

        $path = $reflection->getFileName();
        if (false === $path) {
            return sprintf('Could not locate the source file for "%s".', $entityFullClassName);
        }

        $sourceCode = file_get_contents($path);
        if (false === $sourceCode) {
            return sprintf('Could not read "%s".', $path);
        }

        [$attributeCode, $useStatements] = $this->buildEntityAttributeCode(
            $inputClassDetails,
            $processorClassDetails,
            $permissionPrefix,
            $withAccessControl,
            $searchableFields,
        );

        try {
            $newSourceCode = $this->entityAttributeWriter->addClassAttributes($sourceCode, $attributeCode, $useStatements);
        } catch (\RuntimeException $e) {
            return $e->getMessage();
        }

        $generator->dumpFile($path, $newSourceCode);

        return null;
    }

    /**
     * Builds the raw `#[ApiResource(...)]` / `#[ApiFilter(...)]` attribute source and the
     * `use` statements it needs — shared between the auto-write path
     * ({@see tryWriteEntityAttributes}) and the manual paste-in-by-hand next-steps text
     * ({@see buildNextSteps}), so the two never drift apart.
     *
     * @param list<string> $searchableFields
     * @return array{0: string, 1: list<string>}
     */
    private function buildEntityAttributeCode(
        ClassNameDetails $inputClassDetails,
        ClassNameDetails $processorClassDetails,
        string $permissionPrefix,
        bool $withAccessControl,
        array $searchableFields,
    ): array {
        $useStatements = [
            'ApiPlatform\\Metadata\\ApiResource',
            'ApiPlatform\\Metadata\\GetCollection',
            'ApiPlatform\\Metadata\\Get',
            'ApiPlatform\\Metadata\\Post',
            'ApiPlatform\\Metadata\\Put',
            'ApiPlatform\\Metadata\\Delete',
            $inputClassDetails->getFullName(),
            $processorClassDetails->getFullName(),
        ];

        $lines = [
            '#[ApiResource(',
            '    operations: [',
            '        new GetCollection(),',
            '        new Get(),',
            '        new Post(input: '.$inputClassDetails->getShortName().'::class, processor: '.$processorClassDetails->getShortName().'::class),',
            '        new Put(input: '.$inputClassDetails->getShortName().'::class, processor: '.$processorClassDetails->getShortName().'::class),',
            $withAccessControl
                ? '        new Delete(security: "is_granted(\''.$permissionPrefix.'.delete\')"),'
                : '        new Delete(),',
            '    ],',
            ')]',
        ];

        if ([] !== $searchableFields) {
            $useStatements[] = 'ApiPlatform\\Metadata\\ApiFilter';
            $useStatements[] = 'ApiPlatform\\Doctrine\\Orm\\Filter\\SearchFilter';
            $lines[] = '#[ApiFilter(SearchFilter::class, properties: ['.implode(', ', array_map(static fn (string $f) => "'".$f."' => 'partial'", $searchableFields)).'])]';
        }

        return [implode("\n", $lines), $useStatements];
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

    /**
     * Best-effort detection of a "created at" style timestamp field (e.g. `createdAt`), used to
     * add a read-only "Created" column/CSV export field on the frontend. Returns null if none
     * of the entity's datetime-typed fields look like a creation timestamp by name — the entity
     * may simply not have one, which is fine (frontend omits the column).
     */
    private function findTimestampField(EntityDetails $entityDoctrineDetails): ?string
    {
        $identifier = $entityDoctrineDetails->getIdentifier();

        foreach ($entityDoctrineDetails->getDisplayFields() as $fieldName => $mapping) {
            if ($fieldName === $identifier) {
                continue;
            }

            $doctrineType = (string) ($mapping['type'] ?? 'string');
            if (in_array($doctrineType, self::SKIPPED_TYPES, true) && 1 === preg_match('/^created/i', $fieldName)) {
                return $fieldName;
            }
        }

        return null;
    }

    /**
     * PHP class to instantiate for the detected timestamp field (see findTimestampField()) when
     * a setter-mode entity needs it set at create() time — a constructor-mode entity (Note-style)
     * already does this itself inside its own constructor, but a setter-mode one (no constructor)
     * has nothing else to set it, so the generated Service does it explicitly instead.
     */
    private function timestampFieldClass(EntityDetails $entityDoctrineDetails, ?string $timestampField): ?string
    {
        if (null === $timestampField) {
            return null;
        }

        $doctrineType = (string) ($entityDoctrineDetails->getDisplayFields()[$timestampField]['type'] ?? '');

        return match ($doctrineType) {
            Types::DATETIME_IMMUTABLE, Types::DATETIMETZ_IMMUTABLE => '\\DateTimeImmutable',
            default => '\\DateTime',
        };
    }

    /**
     * Identifies the entity's id column shape so the frontend generator can pick a matching
     * `validId()` check instead of hard-assuming UUIDs — not every entity uses them (e.g. a
     * plain `make:entity`-scaffolded one with an auto-increment integer id).
     */
    private function detectIdType(EntityDetails $entityDoctrineDetails): string
    {
        $identifier = $entityDoctrineDetails->getIdentifier();
        $mapping = $entityDoctrineDetails->getDisplayFields()[$identifier] ?? null;
        $doctrineType = (string) ($mapping['type'] ?? 'string');

        return match ($doctrineType) {
            'uuid', Types::GUID => 'uuid',
            Types::INTEGER, Types::SMALLINT, Types::BIGINT => 'int',
            default => 'string',
        };
    }

    /**
     * Picks how the generated Service creates/updates the entity: `constructor` (calls
     * `new Entity(...$fields)` + `$entity->update(...$fields)`, the Note/TestArticle style this
     * renderer was originally built around) or `setter` (calls `new Entity()` then
     * `$entity->setField(...)` per field, for plain `make:entity`-scaffolded entities that don't
     * declare a matching constructor/update()). Throws instead of silently generating broken code
     * (calling a constructor/update() that doesn't match the entity just gets args silently
     * dropped or a fatal "undefined method" at runtime) if neither pattern is viable.
     *
     * @param list<array{name: string}> $fields
     */
    private function detectWriteMode(string $entityFullClassName, array $fields, ?string $ownerProperty): string
    {
        $reflection = new \ReflectionClass($entityFullClassName);
        $constructor = $reflection->getConstructor();

        $hasCompatibleConstructor = null !== $constructor
            && $constructor->getNumberOfParameters() >= count($fields)
            && $reflection->hasMethod('update');

        if ($hasCompatibleConstructor) {
            return 'constructor';
        }

        $hasNoArgConstructor = null === $constructor || 0 === $constructor->getNumberOfRequiredParameters();

        $missingSetters = [];
        foreach ($fields as $field) {
            $setter = 'set'.ucfirst($field['name']);
            if (!$reflection->hasMethod($setter)) {
                $missingSetters[] = $setter.'()';
            }
        }
        if (null !== $ownerProperty) {
            $ownerSetter = 'set'.ucfirst($ownerProperty);
            if (!$reflection->hasMethod($ownerSetter)) {
                $missingSetters[] = $ownerSetter.'()';
            }
        }

        if ($hasNoArgConstructor && [] === $missingSetters) {
            return 'setter';
        }

        throw new \RuntimeException(sprintf(
            '"%s" tidak punya pola constructor+update() maupun setter yang lengkap untuk make:kmj-api-crud. '.
            'Constructor yang ada butuh %d parameter wajib (perlu 0, atau constructor(%d+ parameter) DAN method update()). '.
            'Setter yang hilang: %s. Tambahkan salah satu pola itu ke entity dulu, lalu jalankan ulang make:kmj-api-crud.',
            $entityFullClassName,
            $constructor?->getNumberOfRequiredParameters() ?? 0,
            count($fields),
            [] !== $missingSetters ? implode(', ', $missingSetters) : '(constructor tidak 0-argumen)',
        ));
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
     * @param list<string> $searchableFields
     */
    private function buildSpecJson(ClassNameDetails $entityClassDetails, array $fields, string $permissionPrefix, ?string $ownerProperty, array $searchableFields, ?string $timestampField, string $idType): string
    {
        $spec = [
            'entity' => $entityClassDetails->getShortName(),
            'permissionPrefix' => $permissionPrefix,
            'ownerProperty' => $ownerProperty,
            'timestampField' => $timestampField,
            'idType' => $idType,
            'fields' => array_map(
                static fn (array $f) => [
                    'name' => $f['name'],
                    'type' => $f['spec_type'],
                    'required' => !$f['nullable'],
                    'maxLength' => $f['length'],
                    'searchable' => in_array($f['name'], $searchableFields, true),
                ],
                $fields,
            ),
        ];

        return (string) json_encode($spec, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES).\PHP_EOL;
    }

    /**
     * @param list<string> $searchableFields
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
        array $searchableFields,
        string $exportRoutePath,
        string $exportLimiterName,
        bool $attributesWritten,
        ?string $attributeWriteError,
    ): array {
        if ($attributesWritten) {
            $steps = [
                '#[ApiResource]'.([] !== $searchableFields ? ' dan #[ApiFilter]' : '').' sudah ditambahkan otomatis ke '.$entityClassDetails->getShortName().' — cek diff-nya sebelum commit.',
                '',
            ];
        } else {
            [$attributeCode] = $this->buildEntityAttributeCode($inputClassDetails, $processorClassDetails, $permissionPrefix, $withAccessControl, $searchableFields);
            $indented = implode("\n", array_map(static fn (string $line) => '    '.$line, explode("\n", $attributeCode)));

            $steps = [
                null !== $attributeWriteError
                    ? 'Tidak bisa menambahkan attribute otomatis ke '.$entityClassDetails->getShortName().' ('.$attributeWriteError.') — tempel manual:'
                    : 'Tambahkan attribute berikut ke '.$entityClassDetails->getShortName().' (belum diubah otomatis):',
                '',
                $indented,
                '',
            ];
        }

        if ($hasOwner) {
            $steps[] = 'CurrentUser'.$entityClassDetails->getShortName().'Extension ter-generate — pastikan ke-tag otomatis via autoconfigure (default kalau App\\ resource src/ mencakup State/).';
            $steps[] = '';
        }

        if ($withAccessControl) {
            $steps[] = 'Tambahkan permission key ke config/permissions/default.yaml (kematjaya/access-control-bundle):';
            $steps[] = '    gated: true, actions: { create: ..., edit: ..., delete: ..., bulk_delete: ..., export_all: ..., export_selected: ... } dengan prefix "'.$permissionPrefix.'"';
            $steps[] = '    (bulk_delete & export_selected murni dipakai untuk gating tombol di frontend, tidak ada endpoint backend terpisah untuk itu)';
            $steps[] = 'Lalu jalankan: bin/console kematjaya:access-control:sync';
            $steps[] = '';
        }

        $steps[] = $entityClassDetails->getShortName().'ExportDataController ter-generate (endpoint export CSV, route "'.$exportRoutePath.'") — controller ini mengasumsikan getter standar get{Field}() ada di entity untuk tiap field yang di-export; sesuaikan manual kalau nama getter beda (mis. boolean pakai is{Field}()).';
        $steps[] = 'Tambahkan rate limiter untuk export ke config/packages/framework.yaml:';
        $steps[] = '';
        $steps[] = '    rate_limiter:';
        $steps[] = '        '.$exportLimiterName.':';
        $steps[] = '            policy: fixed_window';
        $steps[] = '            limit: 5';
        $steps[] = "            interval: '1 minute'";
        $steps[] = '';
        $steps[] = 'Cek Service yang di-generate — constructor/update() entity diasumsikan urutan parameter sama dengan urutan field Doctrine metadata; sesuaikan manual kalau beda.';
        $steps[] = 'Field bertipe date/datetime otomatis di-skip dari Input DTO — tambahkan manual kalau memang perlu jadi input user.';
        $steps[] = 'Spec untuk @kematjaya/crud-ui-generator ditulis ke: '.$specPath;

        return $steps;
    }
}
