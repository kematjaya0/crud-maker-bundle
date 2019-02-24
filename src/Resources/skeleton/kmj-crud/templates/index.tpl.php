{% extends 'base.html.twig' %}

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
                        <a href="{{path('<?= $route_name ?>_create')}}" class="btn btn-default btn-sm pull-right"><span class="fa fa-plus"></span> {{'create'|trans}}</a>
                        <h4 class="panel-title">{{ 'list'|trans }} {{title}}</h4>
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive invoice-table">
                            {% include '_flashes.html.twig' %}
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                        <?php foreach ($entity_fields as $field): ?>
                                        <th>{{ '<?= strtolower($field['fieldName']) ?>'|trans}} </th>
                        <?php endforeach; ?>
                                        <th>actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {{include('<?= $route_name ?>/_filters.html.twig')}}
                                    {% for <?= $entity_twig_var_singular ?> in pagers.currentPageResults %}
                                    <tr>
                                        <?php foreach ($entity_fields as $field): ?>
                                        <td>{{ <?= $helper->getEntityFieldPrintCode($entity_twig_var_singular, $field) ?> }}</td>
                                        <?php endforeach; ?>
                                        <td>
                                            {% include("<?= $route_name ?>/_list_actions.html.twig") %}
                                        </td>
                                    </tr>
                                    {% else %}
                                        <tr>
                                            <td colspan="<?= (count($entity_fields) + 1) ?>">no records found</td>
                                        </tr>
                                    {% endfor %}
                                </tbody>
                            </table>
                            
                            {{ include('<?= $route_name ?>/_list_footer.html.twig') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
{% endblock %}
