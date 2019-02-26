{{form_start(filter)}}
<tr>
    <td><input type="checkbox" name="select_all" onclick="selectAll(this)"/></td>
    <?php foreach ($filter_fields as $k => $field): ?>
    <td>{{ form_widget(filter.<?= $k ?>) }}</td>
    <?php endforeach; ?>
    <td>
        <button type="submit" name="submit" class="btn btn-success btn-sm"><span class="fa fa-filter"></span> {{ 'filter'|trans }}</button>
        <a href="{{path('<?= $route_name ?>_index', {'_reset' : true })}}" class="btn btn-default btn-sm"><span class="fa fa-history"></span> {{ 'reset'|trans }}</a>
    </td>
</tr> 
{{form_end(filter)}}