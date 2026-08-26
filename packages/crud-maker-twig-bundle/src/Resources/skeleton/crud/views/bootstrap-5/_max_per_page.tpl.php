<form method="get" action="{{ path(url) }}" class="d-inline-block">
    <label for="max-per-page" class="form-label visually-hidden">{{ 'max_per_page'|trans }}</label>
    <select id="max-per-page" class="form-select form-select-sm" name="_limit" onchange="this.form.submit()">
        <option value="">{{ 'max_per_page'|trans }}</option>
        {% for v in [5, 10, 20, 50, 100] %}
            <option value="{{ v }}" {{ v == app.request.session.get('limit') ? 'selected' : '' }}>{{ v }}</option>
        {% endfor %}
    </select>
</form>
