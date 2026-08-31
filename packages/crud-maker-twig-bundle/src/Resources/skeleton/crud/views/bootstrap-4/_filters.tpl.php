<div class="collapse" id="filter">
    {{ form_start(<?= $filter_name ?>) }}
    <div class="card mb-3">
        <div class="card-body">
            {{ form_widget(<?= $filter_name ?>) }}
        </div>
        <div class="card-footer bg-white">
            <div class="d-flex justify-content-end">
                <button type="submit" name="submit" class="btn btn-sm btn-success mr-2">
                    <i class="fa fa-search"></i> <span>{{ 'filter'|trans }}</span>
                </button>
                <a href="{{ path('<?= $route_name ?>_index', {'_reset': true}) }}" class="btn btn-sm btn-outline-primary mr-2">
                    <i class="fa fa-recycle"></i> <span>{{ 'reset'|trans }}</span>
                </a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-toggle="collapse" data-target="#filter" aria-expanded="false" aria-controls="filter">
                    <i class="fa fa-times"></i> {{ 'close'|trans }}
                </button>
            </div>
        </div>
    </div>
    {{ form_end(<?= $filter_name ?>) }}
</div>
