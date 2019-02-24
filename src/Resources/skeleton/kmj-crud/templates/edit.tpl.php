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
                        <a href="{{path('<?= $route_name ?>_index')}}" class="btn btn-primary btn-sm pull-right"><span class="fa fa-list"></span> {{ 'back'|trans }}</a>
                        <h4 class="panel-title">{{ 'edit'|trans }} {{title}}</h4>
                        
                    </div>
                    <div class="panel-body">
                        <div class="table-responsive invoice-table">
                            
                            {{ include('_flashes.html.twig') }}
                            
                            {{ include('<?= $route_name ?>/_form.html.twig') }}
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

{% endblock %}
