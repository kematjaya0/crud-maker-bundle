<form method="GET" action="{{path(url_path)}}">
<select class="form-control" name="_limit" onchange="this.form.submit()">
    <option>{{ 'max_per_page'|trans }}</option>
    {% for v in max_per_page %}
        {% if v == app.request.session.get('limit')%}
            <option value="{{v}}" selected>{{v}}</option>
        {% else %}
            <option value="{{v}}">{{v}}</option>
        {% endif %}

    {% endfor %}
</select>
</form>