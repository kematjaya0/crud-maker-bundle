{% extends '<?= $template_namespace ?>base.html.twig' %}

{% block title %}{{title}}{% endblock %}

{% block body %}
    <div class="page-title">
        <h3 class="breadcrumb-header">{{title}}</h3>
    </div>
    <div id="main-wrapper">
        <div class="row">
            <div class="col-lg-12 col-md-12">
                <div class="panel panel-white">
                    <div class="panel-heading clearfix">
                        <?php if($use_credential):?>
                        {{ link_to('<?= $route_name ?>_create', {}, {title: 'create'|trans, class: 'btn btn-default btn-sm pull-right', icon: 'fa fa-plus'}) }}
                        <?php else:?>
                        <a href="{{path('<?= $route_name ?>_create')}}" class="btn btn-default btn-sm pull-right"><span class="fa fa-plus"></span> {{'create'|trans}}</a>
                        <?php endif;?>
                        <h4 class="panel-title">{{ 'list'|trans }} {{title}}</h4>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive invoice-table">
                            {% include '<?= $template_namespace ?>_flashes.html.twig' %}
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                    <?php foreach ($entity_fields as $field): ?>
                                        <?php if(strtolower($field['fieldName']) === strtolower($entity_identifier)):?>
                                        <th></th>
                                        <?php else:?>
                                        <th>{{ '<?= strtolower($field['fieldName']) ?>'|trans}} </th>
                                        <?php endif;?>
                                    <?php endforeach; ?>
                                        <th>actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{include('<?= $template_namespace ?><?= $route_name ?>/_filters.html.twig')}}
                                    {% for <?= $entity_twig_var_singular ?> in pagers.currentPageResults %}
                                    <tr>
                                        <?php foreach ($entity_fields as $field): ?>
                                        <?php if(strtolower($field['fieldName']) === strtolower($entity_identifier)):?>
                                            <?php if($use_credential):?>
                                            <td>
                                            {% if is_allow_to_access('<?= $route_name ?>_add_selected') %}
                                            <?php endif;?>
                                            <input type="checkbox" class="select_<?= $route_name ?>" name="select[{{ <?= $entity_twig_var_singular ?>.id }}]" value="{{ <?= $entity_twig_var_singular ?>.id }}" onclick="selectObj(this)" {{(<?= $entity_twig_var_singular ?>.id in selected_data)?'checked':'' }}/>
                                            <?php if($use_credential):?>
                                            {% endif %}
                                            </td>
                                            <?php endif;?>
                                        <?php else:?>
                                        <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
                                        <?php endif;?>
                                        <?php endforeach; ?>
                                        <td>
                                            {% include("<?= $template_namespace ?><?= $route_name ?>/_list_actions.html.twig") %}
                                        </td>
                                    </tr>
                                    {% else %}
                                        <tr>
                                            <td colspan="<?= (count($entity_fields) + 1) ?>">no records found</td>
                                        </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                            
                            {{ include('<?= $template_namespace ?><?= $route_name ?>/_list_footer.html.twig') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
{% endblock %}
