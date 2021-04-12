jQuery( function($){

    $( "#generate-demo-data" ).click(function() {

        var first_sections = ['applicant', 'property'];
        var sections_done = [];

        $.each(first_sections, function( key, section ) {

            var capitalizedSection = section.charAt(0).toUpperCase() + section.slice(1);

            $("#demo_data_" + section + "_results").append(capitalizedSection + " : <span id=\"" + section + "_demo_data_status\">Getting data</span>");

            var data = {
                action:  'propertyhive_get_section_demo_data',
                section: section,
            };
            jQuery.post( ph_demo_data.ajax_url, data, function(response)
            {
                $("#" + section + "_demo_data_status").text("Creating records");

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
                    $("#" + section + "_demo_data_status").text("Done!");
                    sections_done.push(section);
                });
            }, 'json');
        });

        waitForElement( sections_done );
    });

    function waitForElement( sections_done )
    {
        if ( sections_done.includes( 'applicant' ) && sections_done.includes( 'property' ) )
        {
            console.log("apps and props done");
            create_second_data_set();
        }
        else
        {
            console.log("apps and props not done yet");
            setTimeout(function() {
                waitForElement(sections_done);
            }, 1000);
        }
    }

    function create_second_data_set( )
    {
        var second_sections = ['appraisal', 'viewing', 'offer', 'sale', 'tenancy', 'enquiry'];

        $.each(second_sections, function( key, section ) {

            var capitalizedSection = section.charAt(0).toUpperCase() + section.slice(1);

            $("#demo_data_other_results").append(capitalizedSection + " : <span id=\"" + section + "_demo_data_status\">Getting data</span><br>");

            var data = {
                action:  'propertyhive_get_section_demo_data',
                section: section,
            };
            jQuery.post( ph_demo_data.ajax_url, data, function(response)
            {
                $("#" + section + "_demo_data_status").text("Creating records");

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
                    $("#" + section + "_demo_data_status").text("Done!");
                });
            }, 'json');
        });
    }
});

