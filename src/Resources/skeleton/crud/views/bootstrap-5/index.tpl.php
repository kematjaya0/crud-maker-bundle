<?= $helper->getHeadPrintCode(strtolower($entity_class_name)); ?>

{% block title %}{{ '<?= strtolower($entity_class_name) ?>'|trans }}{% endblock %}

{% block actions %}
<?php if ($use_filter):?>
<button class="btn btn-sm btn-outline-secondary" type="button" id="filter-open" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
    <span class="fa fa-filter"></span> {{ "filter"|trans }}
</button>
<?php endif ?>
<?php if ($is_modal): ?>
    {{ link_to('<?= $route_name ?>_create', {}, {class:"btn btn-sm btn-outline-primary", "data-bs-toggle": "modal", "data-bs-target": "#myModal", icon: '<span class="fa fa-pencil"></span>', label: "create"|trans }) }}
<?php else:?>
    {{ link_to('<?= $route_name ?>_create', {}, {class:"btn btn-sm btn-outline-primary", icon: '<span class="fa fa-pencil"></span>', label: "create"|trans }) }}
<?php endif ?>
{% endblock %}

{% block body %}
    <?php if ($use_filter):?>
    <div class="collapse" id="filter">
        {{ form_start(<?= $filter_name ?>) }}
        <div class="card">
            <div class="card-body">
                {{ form_widget(<?= $filter_name ?>) }}
            </div>
            <div class="card-footer pb-4 pt-1">
                <div class="pull-right" style="float: right">
                    <button type="submit" name="submit" class="btn btn-outline-success btn-sm">
                        <i class="fa fa-filter"></i>
                        <span>{{ 'filter'|trans }}</span>
                    </button>
                    <a href="{{ path('<?= $route_name ?>_index', {'_reset' : true }) }}" class="btn btn-outline-primary btn-sm">
                        <i class="fa fa-recycle"></i> <span>{{ 'reset'|trans }}</span>
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                        <span class="fa fa-close"></span> {{ "close"|trans }}
                    </button>
                </div>
            </div>
        </div>
        {{ form_end(<?= $filter_name ?>) }}
    </div>
    <?php endif ?>
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
        {% for <?= $entity_twig_var_singular ?> in <?= $entity_twig_var_plural ?> %}
            <tr>
            <?php $count = 0 ?>
            <?php foreach ($entity_fields as $k => $field): ?>
                <?php if (in_array($k, $fields_skip)):?>
                    <?php continue; ?>
                <?php endif ?>
                <?php $count++ ?>
                <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
            <?php endforeach; ?>
                <td>
                    <?php if ($is_modal): ?>
                        {{ link_to('<?= $route_name ?>_show', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class:"btn btn-sm btn-outline-primary", "data-bs-toggle": "modal", "data-bs-target": "#myModal", icon: '<span class="fa fa-desktop"></span>', label: "view"|trans }) }}
                        {{ link_to('<?= $route_name ?>_edit', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class:"btn btn-sm btn-outline-info", "data-bs-toggle": "modal", "data-bs-target": "#myModal", icon: '<span class="fa fa-edit"></span>', label: "edit"|trans }) }}
                    <?php else:?>
                        {{ link_to('<?= $route_name ?>_show', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class:"btn btn-sm btn-outline-primary", icon: '<span class="fa fa-desktop"></span>', label: "view"|trans }) }}
                        {{ link_to('<?= $route_name ?>_edit', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class:"btn btn-sm btn-outline-info", icon: '<span class="fa fa-edit"></span>', label: "edit"|trans }) }}
                    <?php endif?>
                    {{ include('<?= $templates_path ?>/_delete_form.html.twig', {<?= $entity_twig_var_singular ?>: <?= $entity_twig_var_singular ?>}) }}
                </td>
            </tr>
        {% else %}
            <tr>
                <td colspan="<?= $count ?>">{{ 'no_records_found'|trans }}</td>
            </tr>
        {% endfor %}
        </tbody>
    </table>
{% endblock %}

{% block maxpage %}
    {{ include('<?= $templates_path ?>/_max_per_page.html.twig', {url: '<?= $route_name ?>_index'}) }}
{% endblock %}

{% block pagination %}
    {{ knp_pagination_render(<?= $entity_twig_var_plural ?>) }}
{% endblock %}

{% block javascripts %}
{{ parent() }}
<script>
    var myCollapsible = document.getElementById('filter')
    myCollapsible.addEventListener('shown.bs.collapse', function () {
        document.getElementById('filter-open').style.display = "none";
    })
    myCollapsible.addEventListener('hidden.bs.collapse', function () {
        document.getElementById('filter-open').style = '';
    })
</script>
{% endblock %}

