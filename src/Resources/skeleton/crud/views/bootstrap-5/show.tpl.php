<?php if ($is_modal): ?>
{% extends '@BaseController/modal-layout.html.twig' %}

{% block title %}{{ 'show'|trans }} {{ '<?= strtolower($entity_class_name) ?>'|trans }}{% endblock %}

{% block content %}
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <tbody>
<?php foreach ($entity_fields as $field): ?>
                    <tr>
                        <th class="w-25 bg-light">{{ '<?= $field['fieldName'] ?>'|trans }}</th>
                        <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
{% endblock %}
<?php else: ?>
<?= $helper->getHeadPrintCode($entity_class_name) ?>

{% block body %}
    <h1 class="h4 mb-3">{{ 'show'|trans }} {{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-0">
            <table class="table table-hover align-middle mb-0">
                <tbody>
<?php foreach ($entity_fields as $field): ?>
                    <tr>
                        <th class="w-25 bg-light">{{ '<?= $field['fieldName'] ?>'|trans }}</th>
                        <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
                    </tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="{{ path('<?= $route_name ?>_index') }}"><span class="fa fa-list"></span> {{ 'back'|trans }}</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ path('<?= $route_name ?>_edit', {'<?= $entity_identifier ?>': <?= $entity_twig_var_singular ?>.<?= $entity_identifier ?>}) }}"><span class="fa fa-pen"></span> {{ 'edit'|trans }}</a>
    </div>
{% endblock %}
<?php endif; ?>
