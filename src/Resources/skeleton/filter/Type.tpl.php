<?= "<?php\n" ?>

/**
 * Generated by kematjaya/crud-maker-bundle 
 * Report any bug on Girhub: https://github.com/kematjaya0/crud-maker-bundle 
 */
namespace <?= $namespace ?>;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Lexik\Bundle\FormFilterBundle\Filter\FilterOperands;
<?php foreach ($constraint_use_statements as $className): ?>
use <?= $className ?>;
<?php endforeach; ?>

/**
 * Description of <?= $namespace ?>\<?= $class_name ?>
 *
 */
class <?= $class_name ?> extends AbstractType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
<?php foreach ($form_fields as $form_field => $typeOptions): ?>
    <?php if(!is_null($identifier) && $form_field == $identifier):?>
        <?php continue;?>
    <?php endif;?>
    <?php if (null === $typeOptions['type'] && !$typeOptions['options_code']): ?>
        ->add('<?= $form_field ?>', Filters\TextFilterType::class, ['condition_pattern' => FilterOperands::STRING_BOTH])
    <?php elseif (null !== $typeOptions['type'] && !isset($typeOptions['options_code'])): ?>
        ->add('<?= $form_field ?>', Filters\<?= $typeOptions['type'] ?>::class])
    <?php else: ?>
        ->add('<?= $form_field ?>', <?= $typeOptions['type'] ? ('Filters\\'.$typeOptions['type'].'::class') : 'null' ?>, [
           <?= $typeOptions['options_code']."\n" ?>
        ])
    <?php endif; ?>
<?php endforeach; ?>
        ;
    }
    
    /**
     * Form Name
     * @return string name of filter form
     **/
    public function getBlockPrefix()
    {
        return '<?php echo strtolower($bounded_class_name) ?>_filter';
    }

    /**
     * add configurations
     **/
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection'   => false,
            'validation_groups' => array('filtering')
        ));
    }
}
