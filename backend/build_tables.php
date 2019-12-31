<?php
add_action('wp_ajax_backend_build_tables', 'backend_build_tables');

function backend_build_tables() {
    global $va_xxx;
    echo json_encode($va_xxx->query("CALL build_tables") === 0);
    die();
}

function frontend_build_tables() {
    ?>
<script type="text/javascript">
    function buildTables() {
        jQuery("#status").text("Build in progress...");
        jQuery("#buildTablesButton").prop("disabled", true);
        jQuery.post(ajaxurl, {
            "action" : "backend_build_tables"
        }, function(response) {
            jQuery("#buildTablesButton").prop("disabled", false);
            if(response.localeCompare("true") === 0) {
                jQuery("#status").text("Build successful");
            } else {
                jQuery("#status").text("Build failed: response was " + response);
            }
        });
    }
</script>

<h1>Build Tables</h1>

<input id="buildTablesButton" type="button" class="button button-primary" value="Build Tables" onClick="buildTables()" />
<p id="status"></p>

<?php
}
?>