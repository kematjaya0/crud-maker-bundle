<?php if ($is_modal): ?>
{% extends '@BaseController/modal-layout.html.twig' %}

{% block title %}{{ title|trans}} {{ '<?= strtolower($entity_class_name) ?>'|trans }}{% endblock %}

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
    <button class="btn btn-sm btn-success">
        <span class="fa fa-save"></span> 
        {{ 'save'|trans }}
    </button>
{% endblock %}
    
<?php else: ?>
<?= $helper->getHeadPrintCode('Edit '.$entity_class_name) ?>

{% block body %}
    <h1>{{ title|trans}} {{ '<?= strtolower($entity_class_name) ?>'|trans }}</h1>

    {{ form_start(form) }}
        
        {{ form_widget(form) }}
        
        <button class="btn btn-sm btn-success">
            <span class="fa fa-save"></span> 
            {{ 'save'|trans }}
        </button>
        <a class="btn btn-sm btn-primary" href="{{ path('<?= $route_name ?>_index') }}" >
            <span class="fa fa-list"></span> {{ 'back'|trans }}
        </a>
        
    {{ form_end(form) }} 
    
{% endblock %} 
<?php endif ?>