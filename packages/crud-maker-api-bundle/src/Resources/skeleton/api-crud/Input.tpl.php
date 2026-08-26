<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class <?= $class_name ?><?= "\n" ?>
{
    public function __construct(
<?php foreach ($fields as $field): ?>
<?php foreach ($field['constraints'] as $constraint): ?>
        #[<?= $constraint ?>]
<?php endforeach ?>
        public <?= $field['php_type'] ?> $<?= $field['name'] ?>,
<?php endforeach ?>
    ) {
    }
}
