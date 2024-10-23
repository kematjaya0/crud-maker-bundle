<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterOperands;
<?php foreach ($constraint_use_statements as $className): ?>
use <?= $className ?>;
<?php endforeach; ?>
use Kematjaya\BaseControllerBundle\Filter\AbstractFilterType;

/**
 * Description of <?= $namespace ?>\<?= $class_name ?>
 *
 */
class <?= $class_name ?> extends AbstractFilterType
{

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
<?php foreach ($form_fields as $form_field => $typeOptions): ?>
    <?php if(!is_null($identifier) && $form_field == $identifier):?>
        <?php continue;?>
    <?php endif;?>
    <?php if (null === $typeOptions['type'] && !$typeOptions['options_code']): ?>
        ->add('<?= $form_field ?>', Filters\TextFilterType::class, ['condition_pattern' => FilterOperands::STRING_CONTAINS])
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
}
