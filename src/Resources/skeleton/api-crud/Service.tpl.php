<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $input_full_class_name ?>;
use <?= $entity_full_class_name ?>;
<?php if (null !== $owner_full_class_name): ?>
use <?= $owner_full_class_name ?>;
<?php endif ?>
use Doctrine\ORM\EntityManagerInterface;

final readonly class <?= $class_name ?> implements <?= $service_interface_class_name ?><?= "\n" ?>
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

<?php if (null !== $owner_class_name): ?>
    public function create(<?= $owner_class_name ?> $<?= $owner_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>($<?= $owner_var ?>, <?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['spec_type'] || 'text' === $field['doctrine_type'] ? sprintf('trim($input->%s)', $field['name']) : sprintf('$input->%s', $field['name']) ?><?php endforeach ?>);
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php else: ?>
    public function create(<?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['spec_type'] || 'text' === $field['doctrine_type'] ? sprintf('trim($input->%s)', $field['name']) : sprintf('$input->%s', $field['name']) ?><?php endforeach ?>);
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php endif ?>

    public function update(<?= $entity_class_name ?> $<?= $entity_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?>->update(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['spec_type'] || 'text' === $field['doctrine_type'] ? sprintf('trim($input->%s)', $field['name']) : sprintf('$input->%s', $field['name']) ?><?php endforeach ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
}
