<?php

namespace Kematjaya\CrudMakerBundle\Twig\Renderer;

use Kematjaya\CrudMakerBundle\Renderer\AbstractRenderer;
use Kematjaya\CrudMakerBundle\Twig\Formatter\CodeFormatter;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * @package App\Maker\Helper
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class ControllerRenderer extends AbstractRenderer
{
    /**
     * Doctrine DBAL types that don't make sense as a flat CSV column.
     *
     * @var array<int, string>
     */
    private const CSV_UNSUPPORTED_TYPES = ['array', 'simple_array', 'json', 'object', 'binary', 'blob', 'guid'];

    /**
     * Doctrine DBAL types formatted as a date/time string in CSV export.
     *
     * @var array<int, string>
     */
    private const CSV_DATE_TYPES = ['date', 'date_immutable', 'datetime', 'datetime_immutable', 'datetimetz', 'datetimetz_immutable', 'time', 'time_immutable'];

    public function __construct(ContainerBagInterface $bag, private DoctrineHelper $doctrineHelper, private FilterTypeRenderer $filterTypeRenderer, private CodeFormatter $codeFormatter)
    {
        $config = $bag->get('crud_maker_twig');
        parent::__construct(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton', $config['templates']['path']);
    }
    
    public function generate(ClassNameDetails $entityClassDetails, Generator $generator, string $additionalNameSpace, string $theme, bool $includeFilter = false, bool $modalForm = false): ClassNameDetails
    {
        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());

        $repositoryVars = [];
        if (null !== $entityDoctrineDetails->getRepositoryClass()) {
            $repositoryClassDetails = $generator->createClassNameDetails(
                '\\'.$entityDoctrineDetails->getRepositoryClass(),
                'Repository\\',
                'Repository'
            );

            $repositoryVars = [
                'repository_full_class_name' => $repositoryClassDetails->getFullName(),
                'repository_class_name' => $repositoryClassDetails->getShortName(),
                'repository_var' => lcfirst($this->singularize($repositoryClassDetails->getShortName())),
            ];
        }
        
        $controllerNameSpace = "-" !== $additionalNameSpace ? sprintf("Controller\\%s\\", $additionalNameSpace) : "Controller\\";
        $controllerClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix().'Controller',
            $controllerNameSpace,
            'Controller'
        );
        
        $formClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix().'Type',
            'Form\\',
            'Type'
        );
        
        $classes = get_declared_classes();
        if (!in_array($formClassDetails->getFullName(), $classes)) {
            $formTypeRenderer = new FormTypeRenderer($generator);
            $formTypeRenderer->render(
                $formClassDetails,
                $entityDoctrineDetails->getFormFields(),
                $entityClassDetails
            );
        }
        
        $entityVarPlural = lcfirst($this->pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst($this->singularize($entityClassDetails->getShortName()));

        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $additionalNameSpaceLower = "-" !== $additionalNameSpace ? implode(DIRECTORY_SEPARATOR, array_filter(explode("\\", strtolower($additionalNameSpace)))) : null;
        $routeName = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $templatesPath = null !== $additionalNameSpaceLower ? $additionalNameSpaceLower . DIRECTORY_SEPARATOR . Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix()) : Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());
        $filterClassNameDetails = null;
        $filterName = 'filter';
        if ($includeFilter) {
            $filterClassNameDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix(),
                'Filter\\',
                'FilterType'
            );
        }
        
        $templates = [
            '_delete_form' => [
                'route_name' => $routeName,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
            ],
            '_max_per_page' => [],
            '_filters' => [
                'route_name' => $routeName,
                'fields_skip' => [$entityDoctrineDetails->getIdentifier()],
                'fields' => array_keys($entityDoctrineDetails->getDisplayFields()),
                'filter_name' => $filterName,
                'entity_twig_var_singular' => $entityTwigVarSingular
            ],
            'form' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'route_name' => $routeName,
                'is_modal' => $modalForm
            ],
            'index' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_plural' => $entityTwigVarPlural,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'templates_path' => $templatesPath,
                'use_filter' => $filterClassNameDetails ? true : false,
                'filter_name' => $filterName,
                'route_name' => $routeName,
                'fields_skip' => [$entityDoctrineDetails->getIdentifier()],
                'is_modal' => $modalForm,
            ],
            'show' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'route_name' => $routeName,
                'is_modal' => $modalForm
            ],
        ];

        $generatedControllerPath = $generator->generateController(
            $controllerClassDetails->getFullName(),
            $this->getPath('crud/controller/Controller.tpl.php'),
            array_merge([
                    'is_modal' => $modalForm,
                    'filter_name' => $filterName,
                    'filter_full_class_name' => $filterClassNameDetails ? $filterClassNameDetails->getFullName() : null,
                    'filter_class_name' => $filterClassNameDetails ? $filterClassNameDetails->getShortName() : null,
                    'entity_full_class_name' => $entityClassDetails->getFullName(),
                    'entity_class_name' => $entityClassDetails->getShortName(),
                    'form_full_class_name' => $formClassDetails->getFullName(),
                    'form_class_name' => $formClassDetails->getShortName(),
                    'route_path' => Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix()),
                    'route_name' => $routeName,
                    'templates_path' => $templatesPath,
                    'entity_var_plural' => $entityVarPlural,
                    'entity_twig_var_plural' => $entityTwigVarPlural,
                    'entity_var_singular' => $entityVarSingular,
                    'entity_twig_var_singular' => $entityTwigVarSingular,
                    'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                    'csv_fields' => $this->buildCsvFields($entityDoctrineDetails->getDisplayFields(), $entityDoctrineDetails->getIdentifier()),
                ],
                $repositoryVars
            )
        );

        $this->codeFormatter->format($generator->getRootDirectory() . DIRECTORY_SEPARATOR . $generatedControllerPath);

        foreach ($templates as $template => $variables) {
            $generator->generateTemplate(
                $templatesPath.'/'.$template.'.html.twig',
                $this->getPath(
                    sprintf('crud/views/%s/%s.tpl.php', $theme, $template)
                ),
                $variables
            );
        }
        
        if ($filterClassNameDetails) {
            $identifierField = null;
            if (null !== $entityDoctrineDetails) {
                $formFields = $entityDoctrineDetails->getFormFields();
                $identifierField = $entityDoctrineDetails->getIdentifier();
            } else {
                $classDetails = new ClassDetails($entityClassDetails->getFullName());
                $formFields = $classDetails->getFormFields();
            }

            $this->filterTypeRenderer->render(
                $generator,
                $filterClassNameDetails,
                $formFields,
                $entityClassDetails,
                $identifierField
            );
        }
            
        
        return $controllerClassDetails;
    }

    /**
     * Precomputes, at generation time, the label/getter/kind for each field the generated
     * controller's CSV export should include — the same field set already shown in the
     * index table, minus the identifier and columns that don't flatten into a CSV cell.
     *
     * @param array<string, mixed> $fieldMappings Doctrine field mappings, keyed by property name
     * @return array<int, array{label: string, getter: string, kind: string}>
     */
    private function buildCsvFields(array $fieldMappings, string $identifierField): array
    {
        $fields = [];
        foreach ($fieldMappings as $propertyName => $mapping) {
            if ($propertyName === $identifierField) {
                continue;
            }

            $type = $mapping['type'] ?? null;
            if (in_array($type, self::CSV_UNSUPPORTED_TYPES, true)) {
                continue;
            }

            $isBoolean = 'boolean' === $type;
            $kind = $isBoolean ? 'boolean' : (in_array($type, self::CSV_DATE_TYPES, true) ? 'date' : (in_array($type, ['string', 'text'], true) ? 'string' : 'scalar'));

            $fields[] = [
                'label' => ucfirst(Str::asLowerCamelCase($propertyName)),
                'getter' => $this->getGetterName($propertyName, $isBoolean),
                'kind' => $kind,
            ];
        }

        return $fields;
    }

    /**
     * Mirrors Symfony MakerBundle's own getter-naming convention (ClassSourceManipulator::getGetterName),
     * so the generated CSV export calls the same accessor `make:entity` would have generated.
     */
    private function getGetterName(string $propertyName, bool $isBoolean): string
    {
        if (!$isBoolean) {
            return 'get'.Str::asCamelCase($propertyName);
        }

        if (0 !== strncasecmp($propertyName, 'is', 2) && 0 !== strncasecmp($propertyName, 'has', 3)) {
            return 'is'.Str::asCamelCase($propertyName);
        }

        return Str::asLowerCamelCase($propertyName);
    }
}
