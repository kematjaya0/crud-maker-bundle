<div class="collapse" id="filter">
    {{ form_start(<?= $filter_name ?>) }}
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            {{ form_widget(<?= $filter_name ?>) }}
        </div>
        <div class="card-footer bg-white border-0 pt-0">
            <div class="d-flex justify-content-end gap-2">
                <button type="submit" name="submit" class="btn btn-sm btn-success">
                    <i class="fa fa-filter"></i> <span>{{ 'filter'|trans }}</span>
                </button>
                <a href="{{ path('<?= $route_name ?>_index', {'_reset': true}) }}" class="btn btn-sm btn-outline-primary">
                    <i class="fa fa-rotate-left"></i> <span>{{ 'reset'|trans }}</span>
                </a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                    <span class="fa fa-xmark"></span> {{ 'close'|trans }}
                </button>
            </div>
        </div>
    </div>
    {{ form_end(<?= $filter_name ?>) }}
</div>
