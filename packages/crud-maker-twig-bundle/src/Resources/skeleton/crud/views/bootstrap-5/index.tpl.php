{% extends 'base.html.twig' %}

{% block title %}{{ '<?= strtolower($entity_class_name) ?>'|trans }}{% endblock %}

{% block actions %}
    <span class="text-muted small me-1 d-none d-sm-inline">
        {{ <?= $entity_twig_var_plural ?>.totalItemCount }} {{ 'data'|trans }}
    </span>
<?php if ($use_filter): ?>
    <button class="btn btn-sm btn-outline-secondary" type="button" id="filter-open" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
        <span class="fa fa-filter bi bi-funnel"></span> {{ 'filter'|trans }}
    </button>
<?php endif; ?>
<?php if ($is_modal): ?>
    {{ link_to('<?= $route_name ?>_create', {}, {class: 'btn btn-sm btn-primary', 'data-bs-toggle': 'modal', 'data-bs-target': '#myModal', icon: '<span class="fa fa-plus bi bi-plus-lg"></span>', label: 'create'|trans}) }}
<?php else: ?>
    {{ link_to('<?= $route_name ?>_create', {}, {class: 'btn btn-sm btn-primary', icon: '<span class="fa fa-plus bi bi-plus-lg"></span>', label: 'create'|trans}) }}
<?php endif; ?>
{% endblock %}

{% block body %}
<?php if ($use_filter): ?>
    {{ include('<?= $templates_path ?>/_filters.html.twig', {<?= $filter_name ?>: <?= $filter_name ?>}) }}
<?php endif; ?>

    <form id="bulk-form" method="post" action="{{ path('<?= $route_name ?>_bulk_execute') }}" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <input type="hidden" name="_token" value="{{ csrf_token('bulk_execute') }}">
        <select name="execution" id="bulk-execution" class="form-select form-select-sm w-auto" required>
            <option value="">{{ 'bulk_action'|trans }}</option>
            <option value="delete">{{ 'delete'|trans }}</option>
            <option value="export">{{ 'export'|trans }}</option>
        </select>
        <button type="submit" id="bulk-apply" class="btn btn-sm btn-outline-primary" disabled hidden><span class="fa fa-plus bi bi-check-circle"></span> {{ 'apply'|trans }}</button>
        <span id="bulk-selected-count" class="text-muted small" hidden>{{ '0 selected'|trans }}</span>
    </form>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 2.5rem;">
                                <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="{{ 'select_all'|trans }}">
                            </th>
<?php foreach ($entity_fields as $k => $field): ?>
<?php if (in_array($k, $fields_skip)): ?>
<?php continue; ?>
<?php endif; ?>
                            <th>{{ '<?= strtolower($field['fieldName']) ?>'|trans }}</th>
<?php endforeach; ?>
                            <th class="text-nowrap">{{ 'actions'|trans }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    {% for <?= $entity_twig_var_singular ?> in <?= $entity_twig_var_plural ?> %}
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input bulk-row-checkbox" form="bulk-form" name="ids[]" value="{{ <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?> }}" aria-label="{{ 'select'|trans }}">
                            </td>
<?php $count = 0; ?>
<?php foreach ($entity_fields as $k => $field): ?>
<?php if (in_array($k, $fields_skip)): ?>
<?php continue; ?>
<?php endif; ?>
<?php $count++; ?>
                            <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
<?php endforeach; ?>
                            <td class="text-nowrap">
                                <div class="btn-group btn-group-sm" role="group" aria-label="actions">
<?php if ($is_modal): ?>
                                    {{ link_to('<?= $route_name ?>_show', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class: 'btn btn-sm btn-outline-primary', 'data-bs-toggle': 'modal', 'data-bs-target': '#myModal', icon: '<span class="fa fa-eye bi bi-eye"></span>', label: '' }) }}
                                    {{ link_to('<?= $route_name ?>_edit', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class: 'btn btn-sm btn-outline-secondary', 'data-bs-toggle': 'modal', 'data-bs-target': '#myModal', icon: '<span class="fa fa-pen bi bi-pencil-square"></span>', label: '' }) }}
<?php else: ?>
                                    {{ link_to('<?= $route_name ?>_show', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class: 'btn btn-sm btn-outline-primary', icon: '<span class="fa fa-eye bi bi-eye"></span>', label: '' }) }}
                                    {{ link_to('<?= $route_name ?>_edit', {<?= $entity_identifier ?>: <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}, {class: 'btn btn-sm btn-outline-secondary', icon: '<span class="fa fa-pen bi bi-pencil-square"></span>', label: '' }) }}
<?php endif; ?>
                                </div>
                                {{ include('<?= $templates_path ?>/_delete_form.html.twig', {<?= $entity_twig_var_singular ?>: <?= $entity_twig_var_singular ?>}) }}
                            </td>
                        </tr>
                    {% else %}
                        <tr>
                            <td colspan="<?= $count + 1 ?>" class="text-center text-muted py-4">{{ 'no_records_found'|trans }}</td>
                        </tr>
                    {% endfor %}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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

        document.addEventListener('DOMContentLoaded', function () {
            const filterEl = document.getElementById('filter');
            const filterOpenBtn = document.getElementById('filter-open');

            if (filterEl && filterOpenBtn) {
                filterEl.addEventListener('shown.bs.collapse', function () {
                    filterOpenBtn.classList.add('d-none');
                });

                filterEl.addEventListener('hidden.bs.collapse', function () {
                    filterOpenBtn.classList.remove('d-none');
                });
            }

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
                    // Submitted via fetch, Turbo would swallow the file response silently.
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
