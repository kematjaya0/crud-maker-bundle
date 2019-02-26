<?php

/**
 * Description of MakeKmjCrud
 *
 * @author Nur Hidayatullah <kematjaya0@gmail.com>
 */

namespace Kematjaya\CrudMakerBundle\Maker;


use Kematjaya\CrudMakerBundle\Renderer\FormFilterTypeRenderer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Bundle\MakerBundle\ConsoleStyle;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Bundle\MakerBundle\InputConfiguration;
use Symfony\Bundle\MakerBundle\DependencyBuilder;
use Symfony\Bundle\MakerBundle\Generator;
use Symfony\Bundle\MakerBundle\Renderer\FormTypeRenderer;
use Symfony\Bundle\MakerBundle\Validator;
use Symfony\Bundle\MakerBundle\Str;
use Symfony\Bundle\MakerBundle\Doctrine\DoctrineHelper;
use Symfony\Bundle\MakerBundle\FileManager;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Common\Inflector\Inflector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class MakeKmjCrud extends AbstractMaker{
    
    private $doctrineHelper;
    private $formTypeRenderer;
    private $formFilterTypeRenderer;
    private $fileManager;
    
    public function __construct(DoctrineHelper $doctrineHelper, FormFilterTypeRenderer $formFilterTypeRenderer, FormTypeRenderer $formTypeRenderer, FileManager $fileManager)
    {
        $this->doctrineHelper = $doctrineHelper;
        $this->formTypeRenderer = $formTypeRenderer;
        $this->formFilterTypeRenderer = $formFilterTypeRenderer;
        $this->fileManager = $fileManager;
    }
    
    public function configureCommand(Command $command, InputConfiguration $inputConfig) {
        $command
            ->setDescription('Creates CRUD for Doctrine entity class')
            ->addArgument('entity-class', InputArgument::OPTIONAL, sprintf('The class name of the entity to create CRUD (e.g. <fg=yellow>%s</>)', Str::asClassName(Str::getRandomTerm())))
        ;

        $inputConfig->setArgumentAsNonInteractive('entity-class');
    }

    public function configureDependencies(DependencyBuilder $dependencies) {
        $dependencies->addClassDependency(
            Route::class,
            'router'
        );

        $dependencies->addClassDependency(
            AbstractType::class,
            'form'
        );

        $dependencies->addClassDependency(
            Validation::class,
            'validator'
        );

        $dependencies->addClassDependency(
            TwigBundle::class,
            'twig-bundle'
        );

        $dependencies->addClassDependency(
            DoctrineBundle::class,
            'orm-pack'
        );

        $dependencies->addClassDependency(
            CsrfTokenManager::class,
            'security-csrf'
        );

        $dependencies->addClassDependency(
            ParamConverter::class,
            'annotations'
        );
    }
    
    public function interact(InputInterface $input, ConsoleStyle $io, Command $command)
    {
        if (null === $input->getArgument('entity-class')) {
            $argument = $command->getDefinition()->getArgument('entity-class');

            $entities = $this->doctrineHelper->getEntitiesForAutocomplete();

            $question = new Question($argument->getDescription());
            $question->setAutocompleterValues($entities);

            $value = $io->askQuestion($question);

            $input->setArgument('entity-class', $value);
        }
    }
    
    public function generate(InputInterface $input, ConsoleStyle $io, Generator $generator) {
        $entityClassDetails = $generator->createClassNameDetails(
            Validator::entityExists($input->getArgument('entity-class'), $this->doctrineHelper->getEntitiesForAutocomplete()),
            'Entity\\'
        );
        
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
                'repository_var' => lcfirst(Inflector::singularize($repositoryClassDetails->getShortName())),
            ];
        }

        $baseControllerClassDetails = $generator->createClassNameDetails(
            'BaseController',
            'Controller\\Base\\',
            'Controller'
        );
        
        $controllerDir = $io->ask('controller directory (ex : backend, frontend)', null, function ($input){
            if(is_null($input)) {
                return null;
            }
            
            if(!ctype_alpha($input)){
                throw new \RuntimeException("directory only containt words.");
            }
            
            return strtolower($input);
        });
        
        $controllerNamespace    = 'Controller\\';
        $templateNamespace      = null;
        if(!is_null($controllerDir)){
            $controllerNamespace = 'Controller\\'. ucwords($controllerDir).'\\';
            $templateNamespace = $controllerDir.'/';
        }
        
        $controllerClassDetails = $generator->createClassNameDetails(
            $entityClassDetails->getRelativeNameWithoutSuffix().'Controller',
            $controllerNamespace,
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

        $entityVarPlural = lcfirst(Inflector::pluralize($entityClassDetails->getShortName()));
        $entityVarSingular = lcfirst(Inflector::singularize($entityClassDetails->getShortName()));

        $entityTwigVarPlural = Str::asTwigVariable($entityVarPlural);
        $entityTwigVarSingular = Str::asTwigVariable($entityVarSingular);

        $routeName = Str::asRouteName($controllerClassDetails->getRelativeNameWithoutSuffix());
        $templatesPath = $templateNamespace.Str::asFilePath($controllerClassDetails->getRelativeNameWithoutSuffix());
        
        $this->formTypeRenderer->render(
            $formClassDetails,
            $entityDoctrineDetails->getFormFields(),
            $entityClassDetails
        );
        
        $filterClassNameDetails = $generator->createClassNameDetails(
            $input->getArgument('entity-class').'FilterType', 'Filter\\', 'FilterType'
        );
        
        
        $this->formFilterTypeRenderer->render(
            $filterClassNameDetails,
            $entityDoctrineDetails->getFormFields(),
            $entityClassDetails,
            $entityDoctrineDetails->getIdentifier()
        );
        // ------------ generate controller and template 
        $baseControllerPath = 'src/Controller/Base/'.$baseControllerClassDetails->getShortName().'.php';
        
        if (!$this->fileManager->fileExists($baseControllerPath))
        {
            $generator->generateController(
                $baseControllerClassDetails->getFullName(),
                __DIR__ .'\../Resources/skeleton/kmj-crud/controller/BaseController.tpl.php',
                [
                    'namespace' => str_replace('\\'.$baseControllerClassDetails->getShortName(), '', $baseControllerClassDetails->getFullName()),
                    'class_name' => $baseControllerClassDetails->getShortName()
                ]
            );
            
        }
        
        $generator->generateController(
            $controllerClassDetails->getFullName(),
            __DIR__ .'\../Resources/skeleton/kmj-crud/controller/Controller.tpl.php',
            array_merge([
                    'base_controller_full_name' => $baseControllerClassDetails->getFullName(),
                    'base_controller' => $baseControllerClassDetails->getShortName(),
                    'entity_full_class_name' => $entityClassDetails->getFullName(),
                    'entity_class_name' => $entityClassDetails->getShortName(),
                    'form_full_class_name' => $formClassDetails->getFullName(),
                    'form_class_name' => $formClassDetails->getShortName(),
                    'form_filter_full_class_name' => $filterClassNameDetails->getFullName(),
                    'form_filter_class_name' => $filterClassNameDetails->getShortName(),
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
        
        $templates = [
            '_form' => [
                'route_name' => $routeName
            ],
            'edit' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'route_name' => $routeName,
                'template_namespace' => $templateNamespace
            ],
            'index' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_plural' => $entityTwigVarPlural,
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'route_name' => $routeName,
                'template_namespace' => $templateNamespace
            ],
            '_selected_data' => [
                'route_name' => $routeName
            ],
            '_list_actions' => [
                'route_name' => $routeName,
                'entity_twig_var_singular' => $entityTwigVarSingular,
            ],
            '_filters' => [
                'filter_fields' => $entityDoctrineDetails->getFormFields(),
                'route_name' => $routeName,
            ],
            '_list_footer' => [
                'route_name' => $routeName,
                'template_namespace' => $templateNamespace
            ],
            'create' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'route_name' => $routeName,
                'template_namespace' => $templateNamespace
            ],
            'show' => [
                'entity_class_name' => $entityClassDetails->getShortName(),
                'entity_twig_var_singular' => $entityTwigVarSingular,
                'entity_identifier' => $entityDoctrineDetails->getIdentifier(),
                'entity_fields' => $entityDoctrineDetails->getDisplayFields(),
                'route_name' => $routeName,
                'template_namespace' => $templateNamespace
            ],
        ];
        //dump($templates);exit;
        $baseTemplates = [
            'templates/'.$templateNamespace.'base.html.twig' => __DIR__ .'\../Resources/skeleton/kmj-crud/base.tpl.php', 
            'templates/'.$templateNamespace.'_flashes.html.twig' => __DIR__ .'\../Resources/skeleton/kmj-crud/_flashes.tpl.php', 
            'templates/'.$templateNamespace.'_max_per_page.html.twig' => __DIR__ .'\../Resources/skeleton/kmj-crud/_max_per_page.tpl.php'
        ];
        
        foreach($baseTemplates as $targetPath => $tpl) {
            if (!$this->fileManager->fileExists($targetPath)) {
                $generator->generateFile(
                    $targetPath,
                    $tpl,
                    []
                );
            }
        }
        
        foreach ($templates as $template => $variables) {
            $targetPath = 'templates/'.$templatesPath.'/'.$template.'.html.twig';
            if (!$this->fileManager->fileExists($targetPath)) {
                $generator->generateFile(
                    $targetPath,
                    __DIR__ .'\../Resources/skeleton/kmj-crud/templates/'.$template.'.tpl.php',
                    $variables
                );
            }
        }
        
        // ------ end ------------------
        $generator->writeChanges();

        $this->writeSuccessMessage($io);

        $io->text(sprintf('Next: Check your new CRUD by going to <fg=yellow>%s/</>', Str::asRoutePath($controllerClassDetails->getRelativeNameWithoutSuffix())));
        
    }

    public static function getCommandName(): string {
        return 'make:kmj-crud';
    }

}
