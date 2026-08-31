<?php

namespace Kematjaya\CrudMakerBundle\Twig\Renderer;

use Kematjaya\CrudMakerBundle\Renderer\AbstractRenderer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * @package App\Maker\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class FilterTypeRenderer extends AbstractRenderer
{
    /**
     * Doctrine DBAL types that don't need any extra filter option besides the base type.
     *
     * @var array<string, string>
     */
    private const SIMPLE_TYPE_MAP = [
        'boolean' => 'BooleanFilterType',
        'integer' => 'NumberFilterType',
        'smallint' => 'NumberFilterType',
        'bigint' => 'NumberFilterType',
        'decimal' => 'NumberFilterType',
        'float' => 'NumberFilterType',
    ];

    public function __construct(ContainerBagInterface $bag)
    {
        $config = $bag->get('crud_maker_twig');
        parent::__construct(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton', $config['templates']['path']);
    }

    /**
     * @param array<string, mixed> $formFields
     * @param array<string, string> $doctrineFieldTypes field name => Doctrine DBAL type (e.g. 'string', 'boolean', 'datetime_immutable')
     * @param array<int, string> $extraUseClasses
     * @param array<int, string> $constraintClasses
     * @return string the path of the generated file, relative to the project root
     */
    public function render(Generator $generator, ClassNameDetails $formClassDetails, array $formFields, ?ClassNameDetails $boundClassDetails = null, ?string $identifierField = null, array $extraUseClasses = [], array $constraintClasses = [], array $doctrineFieldTypes = []): string
    {
        $extraUseStatements = [];
        $fields = [];
        foreach ($formFields as $name => $fieldTypeOptions) {
            if (is_array($fieldTypeOptions) && EntityType::class === ($fieldTypeOptions['type'] ?? null)) {
                // association field (ManyToOne, OneToOne, ManyToMany, ...): filter it through the related entity
                $fields[$name] = [
                    'type' => 'EntityFilterType',
                    'options_code' => $fieldTypeOptions['options_code'] ?? null,
                ];

                foreach ($fieldTypeOptions['extra_use_classes'] ?? [] as $extraClass) {
                    $extraUseStatements[] = $extraClass;
                }

                continue;
            }

            $fields[$name] = $this->resolveFilterType($doctrineFieldTypes[$name] ?? null);
        }

        $mergedUseStatements = array_values(array_unique(array_merge($constraintClasses, $extraUseStatements, $extraUseClasses)));
        sort($mergedUseStatements);

        return $generator->generateClass(
            $formClassDetails->getFullName(),
            $this->getPath('filter/Type.tpl.php'),
            [
                'bounded_full_class_name' => $boundClassDetails ? $boundClassDetails->getFullName() : null,
                'bounded_class_name' => $boundClassDetails ? $boundClassDetails->getShortName() : null,
                'form_fields' => $fields,
                'constraint_use_statements' => $mergedUseStatements,
                'identifier' => $identifierField
            ]
        );
    }

    /**
     * Maps a Doctrine DBAL column type to the matching Spiriit filter type and its options.
     *
     * @return array{type: string, options_code: ?string}
     */
    private function resolveFilterType(?string $doctrineType): array
    {
        if (null !== $doctrineType && isset(self::SIMPLE_TYPE_MAP[$doctrineType])) {
            return ['type' => self::SIMPLE_TYPE_MAP[$doctrineType], 'options_code' => null];
        }

        return match ($doctrineType) {
            'date', 'date_immutable' => ['type' => 'DateFilterType', 'options_code' => "'widget' => 'single_text'"],
            'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => ['type' => 'DateTimeFilterType', 'options_code' => "'widget' => 'single_text'"],
            'json', 'simple_array', 'array' => ['type' => 'ChoiceFilterType', 'options_code' => "'choices' => []"],
            default => ['type' => 'TextFilterType', 'options_code' => "'condition_pattern' => FilterOperands::STRING_CONTAINS"],
        };
    }
}
