<div class="col-lg-2">
    <div class="pagerfanta">
        <div class="pagination" id="data_selected"><strong>{{selected_data|length}} Data</strong> Selected.</div>
    </div>
</div>
<div class="col-lg-2">
    <div class="pagerfanta">
        <div class="pagination">
            <?php if($use_credential):?>
            {% if is_allow_to_access('<?= $route_name ?>_action_selected') %}
            <?php endif;?>
            <form class="form-inline" method="post" action="{{ path('<?= $route_name ?>_action_selected') }}" onsubmit="return confirm('{{'confirm.action'|trans}}');">
                <input type="hidden" name="_token" value="{{ csrf_token('<?= $route_name ?>_action_selected') }}">
                <select class="form-control" name="_action">
                    <option></option>
                    <option value="delete">Delete</option>
                </select>
                <button type="submit" class="btn btn-sm btn-danger">Submit</button>
            </form>
            <?php if($use_credential):?>
            {% endif %}
            <?php endif;?>
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
    
    var action = "remove";
    if(obj.checked) {
        action = "add";
    }
        
    if(use_jquery) {
        $.ajax({
            type:"GET", url: "{{path('<?= $route_name ?>_add_selected')}}",
            data: {id: obj.value, action: action},
            success: function (e) {
                $("#data_selected").html("<strong>"+e.count+" Data</strong> Selected.");
            }
        });
    } else {
        var xmlhttp = new XMLHttpRequest();

        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == XMLHttpRequest.DONE) {   // XMLHttpRequest.DONE == 4
               if (xmlhttp.status == 200) {
                   var e = JSON.parse(xmlhttp.responseText);
                   document.getElementById("data_selected").innerHTML = "<strong>"+e.count+" Data</strong> Selected.";
               }
               else if (xmlhttp.status == 400) {
                    alert('There was an error 400');
               }
               else {
                   alert('something else other than 200 was returned');
               }
            }
        };

        xmlhttp.open("GET", "{{path('<?= $route_name ?>_add_selected')}}?id="+obj.value+"&action="+action, true);
        xmlhttp.send();
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
        var select = document.getElementsByClassName("select_<?= $route_name ?>");
        for(var i = 0; i < select.length; i++)
        {
            if(obj.checked) {
                select.item(i).checked = true;
            } else {
                select.item(i).checked = false;
            }
            selectObj(select.item(i));
        }
    }

}
</script>