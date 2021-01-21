<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Renderer;

use Kematjaya\CrudMakerBundle\Renderer\FilterTypeRenderer;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Doctrine\Inflector\Inflector as LegacyInflector;
use Doctrine\Inflector\InflectorFactory;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassDetails;

/**
 * @package App\Maker\Helper
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class ControllerRenderer 
{
    
    /**
     * 
     * @var DoctrineHelper
     */
    private $doctrineHelper;
    
    /**
     * 
     * @var FilterTypeRenderer
     */
    private $filterTypeRenderer;
    
    private $inflector;
    
    public function __construct(DoctrineHelper $doctrineHelper, FilterTypeRenderer $filterTypeRenderer) 
    {
        $this->filterTypeRenderer = $filterTypeRenderer;
        $this->doctrineHelper = $doctrineHelper;
        
        if (class_exists(InflectorFactory::class)) {
            $this->inflector = InflectorFactory::create()->build();
        }
    }
    
    public function generate(ClassNameDetails $entityClassDetails, Generator $generator, bool $includeFilter = false, bool $modalForm = false): ClassNameDetails
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
        
        $controllerClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix().'Controller',
            'Controller\\',
            'Controller'
        );
        
        $iter = 0;
        do {
            $formClassDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix().($iter ?: '').'Type',
                'Form\\',
                'Type'
            );
            ++$iter;
        } while (class_exists($formClassDetails->getFullName()));
        
        $entityVarPlural = lcfirst($this->pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst($this->singularize($entityClassDetails->getShortName()));

        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $routeName = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $templatesPath = Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());
        
        $filterClassNameDetails = null;
        $filterName = 'filter';
        if ($includeFilter) {
            $filterClassNameDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix(),
                'Filter\\',
                'FilterType'
            );
        }
            
        $formTypeRenderer = new FormTypeRenderer($generator);
        $formTypeRenderer->render(
            $formClassDetails,
            $entityDoctrineDetails->getFormFields(),
            $entityClassDetails
        );
        
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
                'is_modal' => $modalForm
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

        $baseTemplatePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR . 'skeleton';
        $generator->generateController(
            $controllerClassDetails->getFullName(),
            $baseTemplatePath . DIRECTORY_SEPARATOR . 'crud/controller/Controller.tpl.php',
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
                ],
                $repositoryVars
            )
        );
        
        foreach ($templates as $template => $variables) {
            $generator->generateTemplate(
                $templatesPath.'/'.$template.'.html.twig',
                $baseTemplatePath . DIRECTORY_SEPARATOR . 'crud/bootstrap-3/'.$template.'.tpl.php',
                $variables
            );
        }
        
        if ($filterClassNameDetails) {
            $identifierField = null;
            if (null !== $entityDoctrineDetails) {
                $formFields = $entityDoctrineDetails->getFormFields();
                $identifierField = $entityDoctrineDetails->getIdentifier();
            } else {
                $classDetails = new ClassDetails($entityDoctrineDetails->getFullName());
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
    
    private function pluralize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->pluralize($word);
        }

        return LegacyInflector::pluralize($word);
    }

    private function singularize(string $word): string
    {
        if (null !== $this->inflector) {
            return $this->inflector->singularize($word);
        }

        return LegacyInflector::singularize($word);
    }
}
