<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $input_full_class_name ?>;
use <?= $entity_full_class_name ?>;
<?php if (null !== $owner_full_class_name): ?>
use <?= $owner_full_class_name ?>;
<?php endif ?>

interface <?= $class_name ?><?= "\n" ?>
{
<?php if (null !== $owner_class_name): ?>
    public function create(<?= $owner_class_name ?> $<?= $owner_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?>;
<?php else: ?>
    public function create(<?= $input_class_name ?> $input): <?= $entity_class_name ?>;
<?php endif ?>

    public function update(<?= $entity_class_name ?> $<?= $entity_var ?>, <?= $input_class_name ?> $input): <?= $entity_class_name ?>;
}
