<form class="form-inline" method="post" action="{{ path('<?= $route_name ?>_delete', {'id': <?= $entity_twig_var_singular ?>.id}) }}" onsubmit="return confirm('{{'confirm.delete'|trans}}');">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ <?= $entity_twig_var_singular ?>.id) }}">
    <a href="{{path('<?= $route_name ?>_edit', {id: <?= $entity_twig_var_singular ?>.id})}}" class="btn btn-xs btn-primary"><span class="fa fa-edit"></span> {{'edit'|trans}}</a>
    <button type="submit" class="btn btn-danger btn-xs"><span class="fa fa-trash"></span> {{'delete'|trans}}</button>
</form>
