<?php if ($is_modal): ?>
{% extends '@BaseController/modal-layout.html.twig' %}

{% block title %}{{ title|trans }} {{ '<?= strtolower($entity_class_name) ?>'|trans }}{% endblock %}

{% block content %}
    {{ form_widget(form) }}
{% endblock %}

{% block form_start %}
    {{ form_start(form) }}
{% endblock %}

{% block form_end %}
    {{ form_end(form) }}
{% endblock %}

{% block actions %}
    <button type="submit" class="btn btn-sm btn-success">
        <span class="fa fa-save"></span> {{ 'save'|trans }}
    </button>
{% endblock %}
<?php else: ?>
<?= $helper->getHeadPrintCode('Edit '.$entity_class_name) ?>

{% block body %}
    <h1 class="h4 mb-3">{{ title|trans }} {{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            {{ form_start(form) }}
                {{ form_widget(form) }}

                <div class="d-flex gap-2 mt-3">
                    <button type="submit" class="btn btn-sm btn-success">
                        <span class="fa fa-save"></span> {{ 'save'|trans }}
                    </button>
                    <a class="btn btn-sm btn-outline-primary" href="{{ path('<?= $route_name ?>_index') }}">
                        <span class="fa fa-list"></span> {{ 'back'|trans }}
                    </a>
                </div>
            {{ form_end(form) }}
        </div>
    </div>
{% endblock %}
<?php endif; ?>
