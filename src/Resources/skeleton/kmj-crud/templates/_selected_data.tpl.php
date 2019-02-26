<div class="col-lg-2">
    <div class="pagerfanta">
        <div class="pagination" id="data_selected"><strong>{{selected_data|length}} Data</strong> Selected.</div>
    </div>
</div>
<div class="col-lg-2">
    <div class="pagerfanta">
        <div class="pagination">
            <form class="form-inline" method="post" action="{{ path('<?= $route_name ?>_action_selected') }}" onsubmit="return confirm('{{'confirm.action'|trans}}');">
                <input type="hidden" name="_token" value="{{ csrf_token('<?= $route_name ?>_action_selected') }}">
                <select class="form-control" name="_action">
                    <option></option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-sm btn-danger">Submit</button>
            </form>
        </div>
    </div>
</div>
<script>
        
var use_jquery = false;
window.onload = function() {
    if (window.jQuery) {  
        use_jquery = true;
    }
}

function selectObj(obj) {
    if(use_jquery) {
        var action = "remove";
        if(obj.checked) {
            action = "add";
        }

        $.ajax({
            type:"GET", url: "{{path('<?= $route_name ?>_add_selected')}}",
            data: {id: obj.value, action: action},
            success: function (e) {
                $("#data_selected").html("<strong>"+e.count+" Data</strong> Selected.");
            }
        });
    } else {

    }
}

function selectAll(obj)
{
    if(use_jquery) {
        $( ".select_<?= $route_name ?>").each(function( index ) {
            if(obj.checked) {
                $( this ).prop( "checked", true );
            } else {
                $( this ).prop( "checked", false );
            }
            selectObj(this);
        });
        $.uniform.update();
    } else{

    }

}
</script>