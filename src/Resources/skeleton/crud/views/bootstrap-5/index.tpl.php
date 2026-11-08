<?= $helper->getHeadPrintCode(strtolower($entity_class_name)); ?>

{% block body %}
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1>{{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>
        <div>
<?php if ($use_filter):?>
            <button class="btn btn-sm btn-outline-secondary" type="button" id="filter-open" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                <span class="fa fa-filter"></span> {{ "filter"|trans }}
            </button>
<?php endif ?>
<?php if ($is_modal): ?>
            <a class="btn btn-sm btn-outline-primary" href="{{ path('<?= $route_name ?>_create') }}" data-bs-toggle="modal" data-bs-target="#myModal">
                <span class="fa fa-pencil"></span> {{ 'create'|trans }}
            </a>
<?php else:?>
            <a class="btn btn-sm btn-outline-primary" href="{{ path('<?= $route_name ?>_create') }}">
                <span class="fa fa-pencil"></span> {{ 'create'|trans }}
            </a>
<?php endif ?>
        </div>
    </div>
<?php if ($use_filter):?>
    {{ include('<?= $templates_path ?>/_filters.html.twig', {<?= $filter_name ?>: <?= $filter_name ?>}) }}
<?php endif ?>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
<?php foreach ($entity_fields as $k => $field): ?>
<?php if (in_array($k, $fields_skip)) { continue; } ?>
                    <th>{{ '<?= strtolower($field['fieldName']) ?>'|trans }}</th>
<?php endforeach; ?>
                    <th>{{ 'actions'|trans }}</th>
                </tr>
            </thead>
            <tbody>
            {% for <?= $entity_twig_var_singular ?> in <?= $entity_twig_var_plural ?> %}
                <tr>
<?php $count = 0 ?>
<?php foreach ($entity_fields as $k => $field): ?>
<?php if (in_array($k, $fields_skip)) { continue; } ?>
<?php $count++ ?>
                    <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
<?php endforeach; ?>
                    <td>
<?php $modalAttr = $is_modal ? ' data-bs-toggle="modal" data-bs-target="#myModal"' : '' ?>
                        <a class="btn btn-sm btn-outline-primary" href="{{ path('<?= $route_name ?>_show', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"<?= $modalAttr ?>>
                            <span class="fa fa-desktop"></span> {{ 'view'|trans }}
                        </a>
                        <a class="btn btn-sm btn-outline-info" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"<?= $modalAttr ?>>
                            <span class="fa fa-edit"></span> {{ 'edit'|trans }}
                        </a>
                        {{ include('<?= $templates_path ?>/_delete_form.html.twig', {<?= $entity_twig_var_singular ?>: <?= $entity_twig_var_singular ?>}) }}
                    </td>
                </tr>
            {% else %}
                <tr>
                    <td colspan="<?= $count + 1 ?>">{{ 'no_records_found'|trans }}</td>
                </tr>
            {% endfor %}
            </tbody>
        </table>
    </div>
    <div class="d-flex justify-content-between">
        <div>
            {{ include('<?= $templates_path ?>/_max_per_page.html.twig', {url: '<?= $route_name ?>_index'}) }}
        </div>
        <div>
            {{ knp_pagination_render(<?= $entity_twig_var_plural ?>) }}
        </div>
    </div>
<?php if ($use_filter):?>
    <script>
    var filterCollapse = document.getElementById('filter');
    if (filterCollapse) {
        filterCollapse.addEventListener('shown.bs.collapse', function () {
            document.getElementById('filter-open').style.display = "none";
        });
        filterCollapse.addEventListener('hidden.bs.collapse', function () {
            document.getElementById('filter-open').style = '';
        });
    }
    </script>
<?php endif ?>
{% endblock %}
