/**
 * @return list<<?= $export_return_type_doc ?>>
 */
public function <?= $export_method_name ?>(<?php if (null !== $owner_property): ?><?= $owner_class_name ?> $<?= $owner_var ?>, <?php endif ?><?php if ([] !== $searchable_fields): ?>?string $search, <?php endif ?>int $limit): array
{
    $builder = $this->createQueryBuilder('e')
        ->select('<?= $export_select_fields ?>')
<?php if (null !== $timestamp_field): ?>
        ->orderBy('e.<?= $timestamp_field ?>', 'DESC')
<?php endif ?>
        ->addOrderBy('e.<?= $identifier_field ?>', 'DESC')
        ->setMaxResults($limit);

<?php if (null !== $owner_property): ?>
    $builder->andWhere('e.<?= $owner_property ?> = :owner')->setParameter('owner', $<?= $owner_var ?>);

<?php endif ?>
<?php if ([] !== $searchable_fields): ?>
    if (null !== $search) {
        $builder->andWhere($builder->expr()->orX(
<?php foreach ($searchable_fields as $i => $field): ?>
            $builder->expr()->like('e.<?= $field ?>', ':search')<?= $i < count($searchable_fields) - 1 ? ',' : '' ?><?= "\n" ?>
<?php endforeach ?>
        ))->setParameter('search', '%'.$search.'%');
    }

<?php endif ?>
    /** @var list<<?= $export_return_type_doc ?>> $result */
    $result = $builder->getQuery()->getArrayResult();

    return $result;
}
