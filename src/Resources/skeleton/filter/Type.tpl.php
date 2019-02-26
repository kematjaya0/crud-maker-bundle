<?= "<?php\n" ?>

/**
* test
**/
namespace <?= $namespace ?>;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Lexik\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Lexik\Bundle\FormFilterBundle\Filter\FilterOperands;
<?php foreach ($constraint_use_statements as $className): ?>
use <?= $className ?>;
<?php endforeach; ?>

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
            ->add('<?= $form_field ?>', Filters\TextFilterType::class, ['condition_pattern' => FilterOperands::STRING_BOTH, 'attr' => ['class' => 'form-control']])
    <?php elseif (null !== $typeOptions['type'] && !isset($typeOptions['options_code'])): ?>
            ->add('<?= $form_field ?>', Filters\<?= $typeOptions['type'] ?>::class)
    <?php else: ?>
            ->add('<?= $form_field ?>', <?= $typeOptions['type'] ? ('Filters\\'.$typeOptions['type'].'::class') : 'null' ?>, [
    <?= $typeOptions['options_code']."\n" ?>
            ])
    <?php endif; ?>
<?php endforeach; ?>
        ;
    }
    
    public function getBlockPrefix()
    {
    return '<?php echo strtolower($bounded_class_name) ?>_filter';
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection'   => false,
            'validation_groups' => array('filtering') // avoid NotBlank() constraint-related message
        ));
    }
}
