<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use <?= $input_full_class_name ?>;
use <?= $entity_full_class_name ?>;
<?php if (null !== $owner_full_class_name): ?>
use <?= $owner_full_class_name ?>;
<?php endif ?>
use <?= $service_interface_full_class_name ?>;
<?php if (null !== $repository_full_class_name): ?>
use <?= $repository_full_class_name ?>;
<?php endif ?>
<?php if (null !== $owner_class_name): ?>
use Symfony\Bundle\SecurityBundle\Security;
<?php endif ?>
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
<?php if ($with_access_control): ?>
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
<?php endif ?>

/** @implements ProcessorInterface<<?= $input_class_name ?>, <?= $entity_class_name ?>> */
final readonly class <?= $class_name ?> implements ProcessorInterface
{
    public function __construct(
        private <?= $service_interface_class_name ?> $<?= lcfirst($entity_var) ?>s,
<?php if (null !== $repository_full_class_name): ?>
        private <?= $repository_class_name ?> $<?= $repository_var ?>,
<?php endif ?>
<?php if (null !== $owner_class_name): ?>
        private Security $security,
<?php endif ?>
<?php if ($with_access_control): ?>
        private AuthorizationCheckerInterface $authChecker,
<?php endif ?>
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): <?= $entity_class_name ?><?= "\n" ?>
    {
<?php if (null !== $owner_class_name): ?>
        $<?= $owner_var ?> = $this->security->getUser();
        if (!$<?= $owner_var ?> instanceof <?= $owner_class_name ?>) {
            throw new AccessDeniedHttpException();
        }

<?php endif ?>
<?php if ($with_access_control): ?>
        $permissionKey = $operation instanceof Post ? '<?= $permission_prefix ?>.create' : '<?= $permission_prefix ?>.edit';
        if (!$this->authChecker->isGranted($permissionKey)) {
            throw new AccessDeniedHttpException();
        }

<?php endif ?>
        if ($operation instanceof Post) {
<?php if (null !== $owner_class_name): ?>
            return $this-><?= lcfirst($entity_var) ?>s->create($<?= $owner_var ?>, $data);
<?php else: ?>
            return $this-><?= lcfirst($entity_var) ?>s->create($data);
<?php endif ?>
        }

        $<?= $entity_var ?> = $this-><?= $repository_var ?>->find($uriVariables['id'] ?? null);
        if (!$<?= $entity_var ?> instanceof <?= $entity_class_name ?>) {
            throw new AccessDeniedHttpException();
        }
<?php if (null !== $owner_var): ?>
        if ($<?= $entity_var ?>->get<?= ucfirst($owner_property) ?>() !== $<?= $owner_var ?>) {
            throw new AccessDeniedHttpException();
        }
<?php endif ?>

        return $this-><?= lcfirst($entity_var) ?>s->update($<?= $entity_var ?>, $data);
    }
}
