<?= $helper->getHeadPrintCode(strtolower($entity_class_name)); ?>

{% block body %}
    <h1>{{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>
    <div class="pull-right">
<?php if ($use_filter): ?>
        <button class="btn btn-sm btn-outline-secondary" type="button" id="filter-open" data-toggle="collapse" data-target="#filter" aria-expanded="false" aria-controls="filter">
            <i class="fa fa-filter"></i> {{ 'filter'|trans }}
        </button>
<?php endif ?>
<?php if ($is_modal): ?>
        <a class="btn btn-sm btn-outline-success" href="{{ path('<?= $route_name ?>_create') }}" data-toggle="modal" data-target="#modal">
            <span class="fa fa-plus"></span> {{ 'create'|trans }}</a>
<?php else:?>
        <a class="btn btn-sm btn-outline-success" href="{{ path('<?= $route_name ?>_create') }}">
            <span class="fa fa-plus"></span> {{ 'create'|trans }}</a>
<?php endif ?>
    </div>
<?php if ($use_filter): ?>
    {{ include('<?= $templates_path ?>/_filters.html.twig', {<?= $filter_name ?>: <?= $filter_name ?>}) }}
<?php endif ?>
    <form id="bulk-form" method="post" action="{{ path('<?= $route_name ?>_bulk_execute') }}" class="d-flex flex-wrap align-items-center mb-3">
        <input type="hidden" name="_token" value="{{ csrf_token('bulk_execute') }}">
        <select name="execution" id="bulk-execution" class="form-control form-control-sm mr-2" style="width: auto;" required>
            <option value="">{{ 'bulk_action'|trans }}</option>
            <option value="delete">{{ 'delete'|trans }}</option>
            <option value="export">{{ 'export'|trans }}</option>
        </select>
        <button type="submit" id="bulk-apply" class="btn btn-sm btn-outline-primary mr-2" disabled hidden>{{ 'apply'|trans }}</button>
        <span id="bulk-selected-count" class="text-muted small" hidden>{{ '0 selected'|trans }}</span>
    </form>
    <div class="table-responsive">
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th style="width: 2.5rem;">
                        <input type="checkbox" id="bulk-select-all" aria-label="{{ 'select_all'|trans }}">
                    </th>
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
                    <td>
                        <input type="checkbox" class="bulk-row-checkbox" form="bulk-form" name="ids[]" value="{{ <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?> }}" aria-label="{{ 'select'|trans }}">
                    </td>
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
                        <a class="btn btn-xs btn-outline-primary" href="{{ path('<?= $route_name ?>_show', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}" data-toggle="modal" data-target="#modal">
                            <span class="fa fa-desktop"></span> {{ 'show'|trans }}
                        </a>
                        <a class="btn btn-xs btn-outline-info" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}" data-toggle="modal" data-target="#modal">
                            <span class="fa fa-edit"></span> {{ 'edit'|trans }}
                        </a>
<?php else:?>
                        <a class="btn btn-xs btn-outline-primary" href="{{ path('<?= $route_name ?>_show', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}">
                            <span class="fa fa-desktop"></span> {{ 'show'|trans }}
                        </a>
                        <a class="btn btn-xs btn-outline-info" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}">
                            <span class="fa fa-edit"></span> {{ 'edit'|trans }}
                        </a>
<?php endif?>
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
    <script>
        $(function () {
            $('#filter').on('shown.bs.collapse', function () {
                $('#filter-open').addClass('d-none');
            }).on('hidden.bs.collapse', function () {
                $('#filter-open').removeClass('d-none');
            });
        });

        document.addEventListener('DOMContentLoaded', function () {
            const bulkForm = document.getElementById('bulk-form');
            const selectAllEl = document.getElementById('bulk-select-all');
            const rowCheckboxes = document.querySelectorAll('.bulk-row-checkbox');
            const applyBtn = document.getElementById('bulk-apply');
            const executionEl = document.getElementById('bulk-execution');
            const selectedCountEl = document.getElementById('bulk-selected-count');

            if (!bulkForm || !applyBtn || !executionEl || !selectedCountEl) {
                return;
            }

            function updateBulkState() {
                const checked = document.querySelectorAll('.bulk-row-checkbox:checked');
                const isReady = checked.length > 0 && executionEl.value !== '';

                selectedCountEl.textContent = checked.length + ' {{ 'selected'|trans }}';
                selectedCountEl.hidden = !isReady;
                applyBtn.disabled = !isReady;
                applyBtn.hidden = !isReady;

                if (selectAllEl) {
                    selectAllEl.checked = rowCheckboxes.length > 0 && checked.length === rowCheckboxes.length;
                    selectAllEl.indeterminate = checked.length > 0 && checked.length < rowCheckboxes.length;
                }
            }

            if (selectAllEl) {
                selectAllEl.addEventListener('change', function () {
                    rowCheckboxes.forEach(function (checkbox) {
                        checkbox.checked = selectAllEl.checked;
                    });
                    updateBulkState();
                });
            }

            rowCheckboxes.forEach(function (checkbox) {
                checkbox.addEventListener('change', updateBulkState);
            });

            executionEl.addEventListener('change', updateBulkState);

            bulkForm.addEventListener('submit', function (event) {
                if (executionEl.value === 'delete') {
                    const confirmed = confirm('{{ 'confirm_bulk_delete'|trans|e('js') }}');
                    if (!confirmed) {
                        event.preventDefault();
                        return;
                    }
                }

                if (executionEl.value === 'export') {
                    // CSV download: bypass Turbo so the browser handles this as a real
                    // navigation and honors the Content-Disposition: attachment header.
                    bulkForm.setAttribute('data-turbo', 'false');
                    window.setTimeout(function () {
                        bulkForm.removeAttribute('data-turbo');
                    }, 0);
                }
            });

            updateBulkState();
        });
    </script>
{% endblock %}
