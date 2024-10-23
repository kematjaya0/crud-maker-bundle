<?php

namespace Kematjaya\CrudMakerBundle\Renderer;

use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Str;

/**
 * @package App\Maker\Renderer
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
final class FilterTypeRenderer extends AbstractRenderer
{
    public function render(Generator $generator, ClassNameDetails $formClassDetails, array $formFields, ClassNameDetails $boundClassDetails = null, string $identifierField = null, array $extraUseClasses = [], array $constraintClasses = [])
    {
        $fieldTypeUseStatements = [];
        $fields = [];
        foreach ($formFields as $name => $fieldTypeOptions) {
            $fieldTypeOptions = ($fieldTypeOptions) ? ['type' => null, 'options_code' => null] : ['type' => 'string'];
            
            $fields[$name] = $this->getFieldTypeOptions($fieldTypeOptions);
            $fieldTypeUseStatements[] = $fieldTypeOptions['type'];
        }

        $mergedTypeUseStatements = array_unique(array_merge($fieldTypeUseStatements, $extraUseClasses));
        sort($mergedTypeUseStatements);
        
        $generator->generateClass(
            $formClassDetails->getFullName(),
            $this->getPath('filter/Type.tpl.php'),
            [
                'bounded_full_class_name' => $boundClassDetails ? $boundClassDetails->getFullName() : null,
                'bounded_class_name' => $boundClassDetails ? $boundClassDetails->getShortName() : null,
                'form_fields' => $fields,
                'field_type_use_statements' => $mergedTypeUseStatements,
                'constraint_use_statements' => $constraintClasses,
                'identifier' => $identifierField
            ]
        );
    }
    
    protected function getFieldTypeOptions(array $fieldTypeOptions)
    {
        if (!isset($fieldTypeOptions['type'])) {
            return $fieldTypeOptions;
        }
        
        switch ($fieldTypeOptions['type']) {
            case 'boolean':
                $fieldTypeOptions['type'] = 'Filters\BooleanFilterType';
                $fieldTypeOptions['options_code'] = "";
                break;
            case 'array':
                $fieldTypeOptions['type'] = 'Filters\ChoiceFilterType';
                $fieldTypeOptions['options_code'] = "'choices' => []";
                break;
            default:
                $fieldTypeOptions['type'] = 'Filters\TextFilterType';
                $fieldTypeOptions['options_code'] = "'condition_pattern' => FilterOperands::STRING_CONTAINS";
                break;
        }
        
        $fieldTypeOptions['type'] = Str::getShortClassName($fieldTypeOptions['type']);
        
        return $fieldTypeOptions;
    }
}
