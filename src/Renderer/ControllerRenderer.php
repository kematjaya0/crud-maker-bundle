<?php

/**
 * This file is part of the symfony.
 */

namespace Kematjaya\CrudMakerBundle\Renderer;

use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Util\ClassNameDetails;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

/**
 * @package App\Maker\Helper
 * @license https://opensource.org/licenses/MIT MIT
 * @author  Nur Hidayatullah <kematjaya0@gmail.com>
 */
class ControllerRenderer extends AbstractRenderer
{
    private readonly Inflector $inflector;

    public function __construct(
        ContainerBagInterface $bag,
        private readonly DoctrineHelper $doctrineHelper,
        private readonly FilterTypeRenderer $filterTypeRenderer,
    ) {
        $this->inflector = InflectorFactory::create()->build();

        parent::__construct($bag);
    }

    public function generate(ClassNameDetails $entityClassDetails, Generator $generator, ?string $additionalNameSpace, ?string $theme, bool $includeFilter = false, bool $modalForm = false): ClassNameDetails
    {
        $additionalNameSpace = null === $additionalNameSpace || '' === trim($additionalNameSpace) ? '-' : trim($additionalNameSpace, " \\");
        $theme = $theme ?: $this->config['templates']['theme'];
        if (!in_array($theme, $this->getAvailableThemes(), true)) {
            throw new \InvalidArgumentException(sprintf("theme '%s' not found, available: %s", $theme, implode(", ", $this->getAvailableThemes())));
        }

        $entityDoctrineDetails = $this->doctrineHelper->createDoctrineDetails($entityClassDetails->getFullName());
        if (null === $entityDoctrineDetails) {
            throw new \InvalidArgumentException(sprintf("'%s' is not a doctrine entity", $entityClassDetails->getFullName()));
        }

        $repositoryVars = [];
        if (null !== $entityDoctrineDetails->getRepositoryClass()) {
            $repositoryClassDetails = $generator->createClassNameDetails(
                '\\' . $entityDoctrineDetails->getRepositoryClass(),
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
            $entityClassDetails->getRelativeNameWithoutSuffix() . 'Controller',
            $controllerNameSpace,
            'Controller'
        );

        $formClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix() . 'Type',
            'Form\\',
            'Type'
        );

        // form type yang sudah ada (walau belum di-load) tidak di-generate ulang
        if (!class_exists($formClassDetails->getFullName())) {
            (new FormTypeRenderer($generator))->render(
                $formClassDetails,
                $entityDoctrineDetails->getFormFields(),
                $entityClassDetails
            );
        }

        $entityVarPlural = lcfirst($this->pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst($this->singularize($entityClassDetails->getShortName()));

        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $additionalNameSpaceLower = "-" !== $additionalNameSpace ? implode('/', array_filter(explode("\\", strtolower($additionalNameSpace)))) : null;
        // namespace tambahan ikut di nama & path route agar tidak bentrok dengan controller tanpa namespace
        $routeBase = ("-" !== $additionalNameSpace ? $additionalNameSpace . '\\' : '') . $controllerClassDetails->getRelativeNameWithoutSuffix();
        $routeName = Str::asRouteName($routeBase);
        $templatesPath = null !== $additionalNameSpaceLower ? $additionalNameSpaceLower . '/' . Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix()) : Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());
        $filterClassNameDetails = null;
        $filterName = 'filter';
        $identifier = $entityDoctrineDetails->getIdentifier();
        $displayFields = $entityDoctrineDetails->getDisplayFields();
        $filterFields = [];
        if ($includeFilter) {
            $filterClassNameDetails = $generator->createClassNameDetails(
                $entityClassDetails->getRelativeNameWithoutSuffix(),
                $this->config['filter']['namespace_prefix'],
                $this->config['filter']['suffix']
            );
            $filterFields = $this->filterTypeRenderer->buildFilterFields($entityDoctrineDetails->getFormFields(), $displayFields, $identifier);
        }

        $templates = [
            '_delete_form' => [
                'route_name' => $routeName,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $identifier,
            ],
            '_max_per_page' => [],
            '_filters' => [
                'route_name' => $routeName,
                'fields_skip' => [$identifier],
                'fields' => array_keys($displayFields),
                'filter_fields' => array_keys($filterFields),
                'filter_name' => $filterName,
                'entity_twig_var_singular' => $entityTwigVarSingular,
            ],
            'form' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $identifier,
                'route_name' => $routeName,
                'is_modal' => $modalForm,
            ],
            'index' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_plural' => $entityTwigVarPlural,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $identifier,
                'entity_fields' => $displayFields,
                'templates_path' => $templatesPath,
                'use_filter' => $filterClassNameDetails ? true : false,
                'filter_name' => $filterName,
                'route_name' => $routeName,
                'fields_skip' => [$identifier],
                'is_modal' => $modalForm,
            ],
            'show' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $identifier,
                'entity_fields' => $displayFields,
                'route_name' => $routeName,
                'is_modal' => $modalForm,
            ],
        ];
        if (!$filterClassNameDetails) {
            unset($templates['_filters']);
        }

        $generator->generateController(
            $controllerClassDetails->getFullName(),
            $this->getPath('crud/controller/Controller.tpl.php'),
            array_merge(
                [
                    'is_modal' => $modalForm,
                    'filter_name' => $filterName,
                    'filter_full_class_name' => $filterClassNameDetails ? $filterClassNameDetails->getFullName() : null,
                    'filter_class_name' => $filterClassNameDetails ? $filterClassNameDetails->getShortName() : null,
                    'entity_full_class_name' => $entityClassDetails->getFullName(),
                    'entity_class_name' => $entityClassDetails->getShortName(),
                    'form_full_class_name' => $formClassDetails->getFullName(),
                    'form_class_name' => $formClassDetails->getShortName(),
                    'route_path' => Str::asRoutePath($routeBase),
                    'route_name' => $routeName,
                    'templates_path' => $templatesPath,
                    'entity_var_plural' => $entityVarPlural,
                    'entity_twig_var_plural' => $entityTwigVarPlural,
                    'entity_var_singular' => $entityVarSingular,
                    'entity_twig_var_singular' => $entityTwigVarSingular,
                    'entity_identifier' => $identifier,
                ],
                $repositoryVars
            )
        );

        foreach ($templates as $template => $variables) {
            $generator->generateTemplate(
                $templatesPath . '/' . $template . '.html.twig',
                $this->getPath(
                    sprintf('crud/views/%s/%s.tpl.php', $theme, $template)
                ),
                $variables
            );
        }

        if ($filterClassNameDetails) {
            $this->filterTypeRenderer->render(
                $generator,
                $filterClassNameDetails,
                $entityDoctrineDetails->getFormFields(),
                $entityClassDetails,
                $identifier,
                [],
                [],
                $displayFields
            );
        }

        return $controllerClassDetails;
    }

    private function pluralize(string $word): string
    {
        return $this->inflector->pluralize($word);
    }

    private function singularize(string $word): string
    {
        return $this->inflector->singularize($word);
    }
}
