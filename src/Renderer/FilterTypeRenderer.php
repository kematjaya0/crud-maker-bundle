<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Renderer;

use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;

/**
 * @package App\Maker\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class FilterTypeRenderer extends AbstractRenderer
{
    /**
     * Doctrine type yang tidak bisa difilter
     */
    public const array SKIPPED_TYPES = ['json', 'array', 'simple_array', 'object', 'blob', 'binary'];

    /**
     * Render filter type class
     *
     * @param array                 $formFields       EntityDetails::getFormFields()
     * @param array                 $fieldMappings    EntityDetails::getDisplayFields(), untuk menentukan tipe filter
     */
    public function render(Generator $generator, ClassNameDetails $formClassDetails, array $formFields, ?ClassNameDetails $boundClassDetails = null, ?string $identifierField = null, array $extraUseClasses = [], array $constraintClasses = [], array $fieldMappings = []): void
    {
        $generator->generateClass(
            $formClassDetails->getFullName(),
            $this->getPath('filter/Type.tpl.php'),
            [
                'bounded_full_class_name' => $boundClassDetails ? $boundClassDetails->getFullName() : null,
                'bounded_class_name' => $boundClassDetails ? $boundClassDetails->getShortName() : null,
                'form_fields' => $this->buildFilterFields($formFields, $fieldMappings, $identifierField),
                'field_type_use_statements' => $extraUseClasses,
                'constraint_use_statements' => $constraintClasses,
                'identifier' => $identifierField,
            ]
        );
    }

    /**
     * Filter fields: [name => ['type' => short filter type class, 'options_code' => string]]
     */
    public function buildFilterFields(array $formFields, array $fieldMappings = [], ?string $identifierField = null): array
    {
        $fields = [];
        foreach ($formFields as $name => $fieldTypeOptions) {
            if (null !== $identifierField && $name === $identifierField) {
                continue;
            }

            if (is_array($fieldTypeOptions) && EntityType::class === ($fieldTypeOptions['type'] ?? null)) {
                $fields[$name] = $this->getEntityFieldTypeOptions($fieldTypeOptions);
                continue;
            }

            $mapping = $fieldMappings[$name] ?? null;
            $doctrineType = null;
            if (null !== $mapping) {
                $doctrineType = is_array($mapping) ? ($mapping['type'] ?? null) : ($mapping->type ?? null);
            }

            if (in_array($doctrineType, self::SKIPPED_TYPES, true)) {
                continue;
            }

            $fields[$name] = $this->getFieldTypeOptions(['type' => $doctrineType]);
        }

        return $fields;
    }

    protected function getFieldTypeOptions(array $fieldTypeOptions): array
    {
        return match ($fieldTypeOptions['type']) {
            'boolean' => ['type' => 'BooleanFilterType', 'options_code' => ''],
            'integer', 'smallint', 'bigint', 'decimal', 'float' => ['type' => 'NumberFilterType', 'options_code' => ''],
            'date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable' => ['type' => 'DateRangeFilterType', 'options_code' => "'left_date_options' => ['widget' => 'single_text'],\n            'right_date_options' => ['widget' => 'single_text'],"],
            default => ['type' => 'TextFilterType', 'options_code' => "'condition_pattern' => FilterOperands::STRING_CONTAINS,"],
        };
    }

    /**
     * Relation (many to one / many to many) memakai EntityFilterType
     */
    protected function getEntityFieldTypeOptions(array $fieldTypeOptions): array
    {
        $targetEntity = $fieldTypeOptions['extra_use_classes'][0] ?? null;
        if (null === $targetEntity) {
            return ['type' => 'TextFilterType', 'options_code' => "'condition_pattern' => FilterOperands::STRING_CONTAINS,"];
        }

        $options = sprintf("'class' => \\%s::class,", ltrim($targetEntity, '\\'));
        if (str_contains((string) ($fieldTypeOptions['options_code'] ?? ''), "'multiple' => true")) {
            $options .= "\n            'multiple' => true,";
        }

        return ['type' => 'EntityFilterType', 'options_code' => $options];
    }
}
