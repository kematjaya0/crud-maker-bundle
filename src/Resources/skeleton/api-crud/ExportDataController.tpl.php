<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $entity_full_class_name ?>;
use <?= $repository_full_class_name ?>;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('<?= $export_route_path ?>', name: '<?= $export_route_name ?>', methods: ['GET'], priority: 10)]
final readonly class <?= $class_name ?><?= "\n" ?>
{
    private const MAX_ITEMS = 5000;

    public function __construct(
        private <?= $repository_class_name ?> $<?= $repository_var ?>,
        private Security $security,
        #[Target('<?= $export_limiter_name ?>')] private RateLimiterFactoryInterface $exportLimiter,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) {
            return new JsonResponse(['title' => 'Authentication required.'], 401);
        }

<?php if ($with_access_control): ?>
        if (!$this->security->isGranted('<?= $permission_prefix ?>.export_all')) {
            return new JsonResponse(['title' => 'Forbidden.'], 403);
        }

<?php endif ?>
<?php if ([] !== $searchable_fields): ?>
        $search = trim((string) $request->query->get('search', ''));
        if (mb_strlen($search) > 200) {
            return new JsonResponse(['title' => 'Search term too long.'], 422);
        }

<?php endif ?>
        $limit = $this->exportLimiter->create($user->getUserIdentifier())->consume(1);
        if (!$limit->isAccepted()) {
            return new JsonResponse(['title' => 'Too Many Requests.'], 429, ['Retry-After' => (string) $limit->getRetryAfter()->getTimestamp()]);
        }

        $qb = $this-><?= $repository_var ?>->createQueryBuilder('e')
            ->orderBy('e.<?= $timestamp_field ?? 'id' ?>', 'DESC')
            ->setMaxResults(self::MAX_ITEMS + 1);

<?php if (null !== $owner_property): ?>
        $qb->andWhere('e.<?= $owner_property ?> = :owner')->setParameter('owner', $user);

<?php endif ?>
<?php if ([] !== $searchable_fields): ?>
        if ('' !== $search) {
            $qb->andWhere($qb->expr()->orX(
<?php foreach ($searchable_fields as $i => $field): ?>
                $qb->expr()->like('e.<?= $field ?>', ':search')<?= $i < count($searchable_fields) - 1 ? ',' : '' ?><?= "\n" ?>
<?php endforeach ?>
            ))->setParameter('search', '%'.$search.'%');
        }

<?php endif ?>
        /** @var list<<?= $entity_class_name ?>> $items */
        $items = $qb->getQuery()->getResult();
        if (count($items) > self::MAX_ITEMS) {
            return new JsonResponse(['title' => 'Persempit filter pencarian, terlalu banyak data untuk diexport.'], 422);
        }

        return new JsonResponse([
            'totalItems' => count($items),
            'member' => array_map(static fn (<?= $entity_class_name ?> $e): array => [
<?php foreach ($fields as $field): ?>
                '<?= $field['name'] ?>' => $e->get<?= ucfirst($field['name']) ?>(),
<?php endforeach ?>
<?php if (null !== $timestamp_field): ?>
                '<?= $timestamp_field ?>' => $e->get<?= ucfirst($timestamp_field) ?>()->format(\DATE_ATOM),
<?php endif ?>
            ], $items),
        ]);
    }
}
