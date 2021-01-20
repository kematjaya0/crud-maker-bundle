<?php

/*
 * This file is part of the Symfony MakerBundle package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kematjaya\CrudMakerBundle\Renderer;

use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;

/**
 * @internal
 */
final class FormFilterTypeRenderer
{
    private $generator;

    public function __construct(Generator $generator)
    {
        $this->generator = $generator;
    }

    public function render(ClassNameDetails $formClassDetails, array $formFields, ClassNameDetails $boundClassDetails = null, $identifierField = null, array $constraintClasses = [])
    {
        $fieldTypeUseStatements = [];
        $fields = [];
        foreach ($formFields as $name => $fieldTypeOptions) {
            $fieldTypeOptions = ($fieldTypeOptions) ? ['type' => null, 'options_code' => null] : ['type' => 'string'];
            
            $fields[$name] = $this->getFieldTypeOptions($fieldTypeOptions);
            $fieldTypeUseStatements[] = $fieldTypeOptions['type'];
        }
        
        $this->generator->generateClass(
            $formClassDetails->getFullName(),
            dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources/skeleton/filter/Type.tpl.php',
            [
                'bounded_full_class_name' => $boundClassDetails ? $boundClassDetails->getFullName() : null,
                'bounded_class_name' => $boundClassDetails ? $boundClassDetails->getShortName() : null,
                'form_fields' => $fields,
                'field_type_use_statements' => $fieldTypeUseStatements,
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
                $fieldTypeOptions['options_code'] = "'condition_pattern' => FilterOperands::STRING_BOTH";
                break;
        }
        
        $fieldTypeOptions['type'] = Str::getShortClassName($fieldTypeOptions['type']);
        
        return $fieldTypeOptions;
    }
}
