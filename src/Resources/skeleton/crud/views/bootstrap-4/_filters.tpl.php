{{ form_start(<?= $filter_name ?>) }}
<tr>
    <?php foreach ($fields as $field): ?>
    <?php if (in_array($field, $fields_skip)):?>
        <?php continue; ?>
    <?php endif ?>
    <td>{{ form_widget(<?= $filter_name ?>.<?= $field ?>) }}</td>
    <?php endforeach; ?>
    <td>
        <button type="submit" name="submit" class="btn btn-outline-success btn-sm">
            <i class="fa fa-search"></i> <span>{{ 'filter'|trans }}</span>
        </button>
        <a href="{{path('<?= $route_name ?>_index', {'_reset' : true })}}" class="btn btn-sm btn-outline-secondary">
            <i class="fa fa-recycle"></i> <span>{{ 'reset'|trans }}</span>
        </a>
    </td>
</tr>
{{ form_end(<?= $filter_name ?>) }}