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

<?php $fieldValue = static function (array $field): string {
    $isStringy = 'string' === $field['doctrine_type'] || 'text' === $field['spec_type'] || 'text' === $field['doctrine_type'];
    if (!$isStringy) {
        return sprintf('$input->%s', $field['name']);
    }
    if ($field['nullable']) {
        return sprintf('(null !== $input->%1$s ? trim($input->%1$s) : null)', $field['name']);
    }

    return sprintf('trim($input->%s)', $field['name']);
}; ?>
<?php if ('setter' === $write_mode): ?>
<?php if (null !== $owner_class_name): ?>
    public function create(<?= $owner_class_name ?> $<?= $owner_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>();
        $<?= $entity_var ?>->set<?= ucfirst($owner_property) ?>($<?= $owner_var ?>);
<?php foreach ($fields as $field): ?>
        $<?= $entity_var ?>->set<?= ucfirst($field['name']) ?>(<?= $fieldValue($field) ?>);
<?php endforeach ?>
<?php if (null !== $timestamp_field): ?>
        $<?= $entity_var ?>->set<?= ucfirst($timestamp_field) ?>(new <?= $timestamp_field_class ?>());
<?php endif ?>
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php else: ?>
    public function create(<?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>();
<?php foreach ($fields as $field): ?>
        $<?= $entity_var ?>->set<?= ucfirst($field['name']) ?>(<?= $fieldValue($field) ?>);
<?php endforeach ?>
<?php if (null !== $timestamp_field): ?>
        $<?= $entity_var ?>->set<?= ucfirst($timestamp_field) ?>(new <?= $timestamp_field_class ?>());
<?php endif ?>
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php endif ?>

    public function update(<?= $entity_class_name ?> $<?= $entity_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
<?php foreach ($fields as $field): ?>
        $<?= $entity_var ?>->set<?= ucfirst($field['name']) ?>(<?= $fieldValue($field) ?>);
<?php endforeach ?>
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php else: ?>
<?php if (null !== $owner_class_name): ?>
    public function create(<?= $owner_class_name ?> $<?= $owner_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>($<?= $owner_var ?>, <?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= $fieldValue($field) ?><?php endforeach ?>);
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php else: ?>
    public function create(<?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?> = new <?= $entity_class_name ?>(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= $fieldValue($field) ?><?php endforeach ?>);
        $this->entityManager->persist($<?= $entity_var ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php endif ?>

    public function update(<?= $entity_class_name ?> $<?= $entity_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?><?= "\n" ?>
    {
        $<?= $entity_var ?>->update(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= $fieldValue($field) ?><?php endforeach ?>);
        $this->entityManager->flush();

        return $<?= $entity_var ?>;
    }
<?php endif ?>
}
