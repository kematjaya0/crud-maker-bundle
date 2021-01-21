<?= $helper->getHeadPrintCode(strtolower($entity_class_name)); ?>

{% block body %}
    <h1>{{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>
    <div class="pull-right">
        <a class="btn btn-sm btn-success" href="{{ path('<?= $route_name ?>_new') }}">
            <span class="fa fa-plus"></span> {{ 'create'|trans }}</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                <?php foreach ($entity_fields as $k => $field): ?>
                    <?php if (in_array($k, $fields_skip)):?>
                        <?php continue; ?>
                    <?php endif ?>
                    <th>{{ '<?= strtolower($field['fieldName']) ?>'|trans }}</th>
                <?php endforeach; ?>
                    <th>{{ 'actions'|trans }}</th>
                </tr>
            </thead>
            <tbody>

                <?php if ($use_filter):?>
                {{ include('<?= $templates_path ?>/_filters.html.twig', {<?= $filter_name ?>: <?= $filter_name ?>}) }} 
                <?php endif ?>
            {% for <?= $entity_twig_var_singular ?> in <?= $entity_twig_var_plural ?> %}
                <tr>
                <?php foreach ($entity_fields as $k => $field): ?>
                    <?php if (in_array($k, $fields_skip)):?>
                        <?php continue; ?>
                    <?php endif ?>
                    <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
                <?php endforeach; ?>
                    <td>
                        <a class="btn btn-xs btn-primary" href="{{ path('<?= $route_name ?>_show', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"><span class="fa fa-desktop"></span> {{ 'show'|trans }}</a>
                        <a class="btn btn-xs btn-info" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"><span class="fa fa-edit"></span> {{ 'edit'|trans }}</a>
                        {{ include('<?= $templates_path ?>/_delete_form.html.twig', {<?= $entity_twig_var_singular ?>: <?= $entity_twig_var_singular ?>}) }}
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan="<?= (count($entity_fields) + 1) ?>">{{ 'no_records_found'|trans }}</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
    <div class="col-lg-2">
        {{ include('<?= $templates_path ?>/_max_per_page.html.twig', {url: '<?= $route_name ?>_index'}) }}
    </div>
    <div class="pull-right">
        <style>
            .pagination {
                margin : 0px;
            }
        </style>
        <div class="pagerfanta pull-right">
            {{ knp_pagination_render(<?= $entity_twig_var_plural ?>) }}
        </div>
    </div>
{% endblock %}
