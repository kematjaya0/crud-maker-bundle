<div class="collapse" id="filter">
    {{ form_start(<?= $filter_name ?>) }}
    <div class="card">
        <div class="card-body">
            {{ form_widget(<?= $filter_name ?>) }}
        </div>
        <div class="card-footer pb-4 pt-1">
            <div class="pull-right" style="float: right">
                <button type="submit" name="submit" class="btn btn-outline-success btn-sm">
                    <i class="fa fa-filter"></i>
                    <span>{{ 'filter'|trans }}</span>
                </button>
                <a href="{{ path('<?= $route_name ?>_index', {'_reset' : true }) }}" class="btn btn-outline-primary btn-sm">
                    <i class="fa fa-recycle"></i> <span>{{ 'reset'|trans }}</span>
                </a>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#filter" aria-expanded="false" aria-controls="filter">
                    <span class="fa fa-close"></span> {{ "close"|trans }}
                </button>
            </div>
        </div>
    </div>
    {{ form_end(<?= $filter_name ?>) }}
</div>