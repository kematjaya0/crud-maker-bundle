<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use <?= $repository_full_class_name ?>;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('<?= $export_route_path ?>', name: '<?= $export_route_name ?>', methods: ['GET'], priority: 10)]
final readonly class <?= $class_name ?><?= "\n" ?>
{
    private const MAX_ITEMS = 5000;
<?php if ([] !== $searchable_fields): ?>
    private const SEARCH_MAX_LENGTH = 200;
<?php endif ?>

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
            return $this->problem('Authentication required.', Response::HTTP_UNAUTHORIZED);
        }

<?php if ($with_access_control): ?>
        if (!$this->security->isGranted('<?= $permission_prefix ?>.export_all')) {
            return $this->problem('Forbidden.', Response::HTTP_FORBIDDEN);
        }

<?php endif ?>
<?php if ([] !== $searchable_fields): ?>
        $search = $this->normalizeSearch($request);
        if (is_string($search) && self::SEARCH_MAX_LENGTH < mb_strlen($search)) {
            return $this->problem('Search term too long.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

<?php endif ?>
        $limit = $this->exportLimiter->create($user->getUserIdentifier())->consume(1);
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return $this->problem('Too Many Requests.', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => (string) $retryAfter]);
        }

        $items = $this-><?= $repository_var ?>-><?= $export_method_name ?>(<?php if (null !== $owner_property): ?>$user, <?php endif ?><?php if ([] !== $searchable_fields): ?>$search, <?php endif ?>self::MAX_ITEMS + 1);
        if (self::MAX_ITEMS < count($items)) {
            return $this->problem(
                'Persempit filter pencarian, terlalu banyak data untuk diexport.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                [],
                'Export Too Large',
            );
        }

        return new JsonResponse([
            'totalItems' => count($items),
            'member' => array_map(static fn (array $item): array => [
<?php foreach ($fields as $field): ?>
                '<?= $field['name'] ?>' => $item['<?= $field['name'] ?>'],
<?php endforeach ?>
<?php if (null !== $timestamp_field): ?>
                '<?= $timestamp_field ?>' => $item['<?= $timestamp_field ?>']?->format(\DATE_ATOM) ?? '',
<?php endif ?>
            ], $items),
        ]);
    }
<?php if ([] !== $searchable_fields): ?>

    private function normalizeSearch(Request $request): ?string
    {
        $search = $request->query->get('search');
        if (!is_string($search)) {
            return null;
        }

        $search = trim($search);

        return '' === $search ? null : $search;
    }
<?php endif ?>

    /** @param array<string, string> $headers */
    private function problem(string $detail, int $status, array $headers = [], ?string $title = null): JsonResponse
    {
        return new JsonResponse([
            'title' => $title ?? Response::$statusTexts[$status] ?? 'Error',
            'detail' => $detail,
            'status' => $status,
        ], $status, ['Content-Type' => 'application/problem+json'] + $headers);
    }
}
