jQuery( function($){

    $( "#generate-demo-data" ).click(function() {

        var sections = [];
        $('.demo_data_section:checkbox:checked').each(function () {
            sections.push(this.id);
        });

        if ( sections.length > 0 )
        {
            $.each(sections, function( key, section ) {

                $("#" + section + "_status_span").text("Generating " + section + " data");

                var data = {
                    action:  'propertyhive_get_section_demo_data',
                    section: section,
                };
                jQuery.post( ph_demo_data.ajax_url, data, function(response)
                {
                    $("#" + section + "_status_span").text("Got " + section + " data. Creating records");

                    var data = {
                        action:     'propertyhive_create_demo_data_records',
                        data_items: response,
                    };
                    jQuery.post( ph_demo_data.ajax_url, data, function(response)
                    {
                        var plural = 's';
                        if ( response == 1 )
                        {
                            plural = '';
                        }
                        $("#" + section + "_status_span").text(response + " " + section + " record" + plural + " successfully created");
                    });
                }, 'json');
            });
        }
    });

});