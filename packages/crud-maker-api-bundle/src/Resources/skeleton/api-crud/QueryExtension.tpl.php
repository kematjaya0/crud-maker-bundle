<?= "<?php\n" ?>

declare(strict_types=1);

namespace <?= $namespace ?>;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Extension\QueryItemExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use <?= $entity_full_class_name ?>;
use <?= $owner_full_class_name ?>;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class <?= $class_name ?> implements QueryCollectionExtensionInterface, QueryItemExtensionInterface
{
    public function __construct(private Security $security)
    {
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    public function applyToItem(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, array $identifiers, ?Operation $operation = null, array $context = []): void
    {
        $this->restrict($queryBuilder, $queryNameGenerator, $resourceClass);
    }

    private function restrict(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass): void
    {
        if (<?= $entity_class_name ?>::class !== $resourceClass) {
            return;
        }

        $<?= $owner_var ?> = $this->security->getUser();
        if (!$<?= $owner_var ?> instanceof <?= $owner_class_name ?>) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('<?= $owner_property ?>');
        $queryBuilder->andWhere(sprintf('%s.<?= $owner_property ?> = :%s', $alias, $parameter))->setParameter($parameter, $<?= $owner_var ?>);
    }
}
