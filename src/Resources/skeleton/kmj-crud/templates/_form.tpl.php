{{ form_start(form) }}
    {{ form_widget(form) }}
    <button class="btn btn-success btn-sm"><span class="fa fa-save"></span> {{ 'save'|trans }}</button>
    <a href="{{path('<?= $route_name ?>_index')}}" class="btn btn-primary btn-sm"><span class="fa fa-list"></span> {{ 'back'|trans }}</a>
{{ form_end(form) }}
