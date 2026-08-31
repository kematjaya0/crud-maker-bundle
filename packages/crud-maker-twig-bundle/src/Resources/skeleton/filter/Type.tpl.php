<?= "<?php\n" ?>

namespace <?= $namespace ?>;

use Symfony\Component\Form\FormBuilderInterface;
use Spiriit\Bundle\FormFilterBundle\Filter\Form\Type as Filters;
use Spiriit\Bundle\FormFilterBundle\Filter\FilterOperands;
<?php foreach ($constraint_use_statements as $className): ?>
use <?= $className ?>;
<?php endforeach; ?>
use Kematjaya\BaseControllerBundle\Filter\AbstractFilterType;

class <?= $class_name ?> extends AbstractFilterType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
<?php foreach ($form_fields as $form_field => $typeOptions): ?>
<?php if (null !== $identifier && $form_field === $identifier): ?>
<?php continue; ?>
<?php endif; ?>
<?php if (!empty($typeOptions['options_code'])): ?>
            ->add('<?= $form_field ?>', Filters\<?= $typeOptions['type'] ?>::class, [
                <?= $typeOptions['options_code'] ?>,
            ])
<?php else: ?>
            ->add('<?= $form_field ?>', Filters\<?= $typeOptions['type'] ?>::class)
<?php endif; ?>
<?php endforeach; ?>
        ;
    }
}
