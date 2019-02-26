<div class="row">
     
    <div class="col-lg-2">
        <div class="pagerfanta">
            <div class="pagination">
                {{ include('<?= $template_namespace ?>_max_per_page.html.twig', {url_path: '<?= $route_name ?>_index'}) }}
            </div>
        </div>
    </div>
            
    {{ include('<?= $template_namespace ?><?= $route_name ?>/_selected_data.html.twig') }}
    
    <div class="col-lg-6">
        <div class="pagerfanta pull-right">
            {{ pagerfanta(pagers, 'twitter_bootstrap3', {'pageParameter': '[page]'}) }}
        </div>
    </div>
    
    
</div>