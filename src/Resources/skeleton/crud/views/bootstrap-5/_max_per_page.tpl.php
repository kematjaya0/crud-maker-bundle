<form method="GET" action="{{path(url)}}">
    <select class="form-select form-select-sm" name="_limit" onchange="this.form.submit()">
        <option>{{ 'max_per_page'|trans }}</option>
        {% for v in [5, 10, 20, 50, 100] %}
            {% if v == app.request.session.get('limit')%}
                <option value="{{v}}" selected>{{v}}</option>
            {% else %}
                <option value="{{v}}">{{v}}</option>
            {% endif %}
        {% endfor %}
    </select>
</form>