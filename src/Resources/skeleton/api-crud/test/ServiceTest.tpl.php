<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $input_full_class_name ?>;
use <?= $entity_full_class_name ?>;
<?php if (null !== $owner_full_class_name): ?>
use <?= $owner_full_class_name ?>;
<?php endif ?>
use <?= $service_full_class_name ?>;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class <?= $class_name ?> extends TestCase
{
    public function testCreatePersistsEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(<?= $entity_class_name ?>::class));
        $entityManager->expects(self::once())->method('flush');

<?php if (null !== $owner_class_name): ?>
        $<?= $owner_var ?> = new <?= $owner_class_name ?>(<?= "'owner@example.com'" ?>);
        $<?= $entity_var ?> = (new <?= $service_class_name ?>($entityManager))->create($<?= $owner_var ?>, new <?= $input_class_name ?>(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['doctrine_type'] ? "'test'" : ('bool' === $field['php_type'] ? 'true' : '1') ?><?php endforeach ?>));
<?php else: ?>
        $<?= $entity_var ?> = (new <?= $service_class_name ?>($entityManager))->create(new <?= $input_class_name ?>(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['doctrine_type'] ? "'test'" : ('bool' === $field['php_type'] ? 'true' : '1') ?><?php endforeach ?>));
<?php endif ?>

        self::assertInstanceOf(<?= $entity_class_name ?>::class, $<?= $entity_var ?>);
    }

    public function testUpdateFlushesEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        // TODO: build a real <?= $entity_class_name ?> fixture (constructor args match the entity, not necessarily this Input)
        $<?= $entity_var ?> = $this->createMock(<?= $entity_class_name ?>::class);
        $<?= $entity_var ?>->expects(self::once())->method('update');

        (new <?= $service_class_name ?>($entityManager))->update($<?= $entity_var ?>, new <?= $input_class_name ?>(<?php foreach ($fields as $i => $field): ?><?= $i > 0 ? ', ' : '' ?><?= 'string' === $field['doctrine_type'] || 'text' === $field['doctrine_type'] ? "'updated'" : ('bool' === $field['php_type'] ? 'false' : '2') ?><?php endforeach ?>));
    }
}
