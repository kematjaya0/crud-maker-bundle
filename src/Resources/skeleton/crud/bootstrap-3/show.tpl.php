<?= $helper->getHeadPrintCode($entity_class_name) ?>

{% block body %}

    <h1>{{ 'show'|trans}} <?= $entity_class_name ?></h1>

    <table class="table table-bordered table-hover">
        <tbody>
            <?php foreach ($entity_fields as $field): ?>
            <tr>
                <th><?= ucfirst($field['fieldName']) ?></th>
                <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <a class="btn btn-sm btn-primary" href="{{ path('<?= $route_name ?>_index') }}" ><span class="fa fa-list"></span> {{ 'back'|trans }}</a>

    <a class="btn btn-sm btn-info" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"><span class="fa fa-edit"></span> {{ 'edit'|trans }}</a>

{% endblock %}
