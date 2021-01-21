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
    