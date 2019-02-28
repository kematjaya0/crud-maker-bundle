<?php if($use_credential):?>
<form class="form-inline" method="post" action="{{ path('<?= $route_name ?>_delete', {'id': <?= $entity_twig_var_singular ?>.id}) }}" onsubmit="return confirm('{{'confirm.delete'|trans}}');">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ <?= $entity_twig_var_singular ?>.id) }}">
    {{ link_to('<?= $route_name ?>_show', {'id': <?= $entity_twig_var_singular ?>.id}, {title: 'show'|trans, class: 'btn btn-xs btn-warning', icon: 'fa fa-desktop'}) }}
    {{ link_to('<?= $route_name ?>_edit', {'id': <?= $entity_twig_var_singular ?>.id}, {title: 'edit'|trans, class: 'btn btn-xs btn-primary', icon: 'fa fa-edit'}) }}
    {{ submit_tag('<?= $route_name ?>_delete', {'id': <?= $entity_twig_var_singular ?>.id}, {title: 'delete'|trans, class: 'btn btn-danger btn-xs', icon: 'fa fa-trash'}) }}
    
</form>
<?php else:?>
<form class="form-inline" method="post" action="{{ path('<?= $route_name ?>_delete', {'id': <?= $entity_twig_var_singular ?>.id}) }}" onsubmit="return confirm('{{'confirm.delete'|trans}}');">
    <input type="hidden" name="_method" value="DELETE">
    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ <?= $entity_twig_var_singular ?>.id) }}">
    <a href="{{path('<?= $route_name ?>_edit', {id: <?= $entity_twig_var_singular ?>.id})}}" class="btn btn-xs btn-primary"><span class="fa fa-edit"></span> {{'edit'|trans}}</a>
    <button type="submit" class="btn btn-danger btn-xs"><span class="fa fa-trash"></span> {{'delete'|trans}}</button>
</form>
<?php endif;?>