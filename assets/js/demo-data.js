var ph_generating_demo_data = false;
var ph_deleting_demo_data = false;
var ph_demo_data_sections = 0;
var ph_demo_data_sections_done = 0;

function ph_dd_move_progress_bar(phrase)
{
    var percentage = (ph_demo_data_sections_done / ph_demo_data_sections) * 100;

    jQuery('.ph-dd-progress-bar .bar').css('width', percentage + '%' );

    var extra_class_name = '';
    if ( ph_demo_data_sections_done % 2 == 0 )
    {
        extra_class_name = ' down';
    }
    var messageContainer = document.getElementById('ph-dd-progress-bar');
    var messageElement = document.createElement('div');
    messageElement.className = 'message' + extra_class_name;
    messageElement.textContent = phrase;
    messageElement.style.left = (percentage-10) + '%';
    messageContainer.appendChild(messageElement);

    if ( ph_demo_data_sections_done == ph_demo_data_sections )
    {
        ph_generating_demo_data = false;
        jQuery( "#generate-demo-data" ).val('Generate Demo Data');

        setTimeout(function() {
            jQuery('#ph-dd-progress-bar .message').remove();
        }, 2000); // Remove the message after animation completes
    }
}

jQuery( function($){

    $( "#delete-demo-data" ).click(function() {

        if ( ph_deleting_demo_data )
        {
            return false;
        }

        ph_deleting_demo_data = true;

        var confirm_box = confirm('Are you sure you wish to delete all demo data?');
        if (!confirm_box)
        {
            return false;
        }

        var sections_to_delete = [];
        $('input[name="sections_to_delete[]"]').each(function() {
            sections_to_delete.push($(this).val());
        });

        ph_demo_data_sections_done = 0;
        ph_demo_data_sections = sections_to_delete.length;

        $( "#delete-demo-data" ).val('Deleting...');

        $("#delete_demo_data_results").text("");

        $.each(sections_to_delete, function( key, section ) {

            $("#delete_demo_data_results").append("<span id=\"" + section + "_delete_demo_data_status\">Deleting " + section + " records...</span><br>");

            var data = {
                action:  'propertyhive_delete_demo_data',
                section: section,
            };
            jQuery.post( ph_demo_data.ajax_url, data, function(response)
            {
                $("#" + section + "_delete_demo_data_status").text("All " + response + " demo " + section + " records deleted");

                ph_demo_data_sections_done = ph_demo_data_sections_done + 1;

                if ( ph_demo_data_sections_done == ph_demo_data_sections )
                {
                    ph_deleting_demo_data = false;
                    $( "#delete-demo-data" ).val('Delete Data');
                }
            }).fail(function(jqXHR, textStatus, errorThrown) 
            {
                // Handle error
                console.error('Error when deleting data:', textStatus, errorThrown);
                alert('An error occurred when deleting data: ' + textStatus + ' - ' + errorThrown);

                ph_deleting_demo_data = false;
            });
        });

    });

    $( "#generate-demo-data" ).click(function() {

        if ( ph_generating_demo_data )
        {
            return false;
        }

        ph_generating_demo_data = true;

        ph_demo_data_sections_done = 0;
        ph_demo_data_sections = $('input[name="sub_sections[]"]').length + 2;

        jQuery('.ph-dd-progress-bar .message').remove();

        $( "#generate-demo-data" ).val('Generating...');

        //$("#demo_data_property_results").html('');
        //$("#demo_data_applicant_results").html('');
        //$("#demo_data_other_results").html('');

        var first_sections = ['applicant', 'property'];
        var sections_done = [];

        //$("#demo_data_applicant_results").append("Applicant : <span id=\"applicant_demo_data_status\">Generating data</span>");
        //$("#demo_data_property_results").append("Property : <span id=\"property_demo_data_status\">Generating data</span>");

        var data = {
            action:  'propertyhive_get_section_demo_data',
            section: first_sections,
        };
        jQuery.post( ph_demo_data.ajax_url, data, function(response)
        {
            //$("#applicant_demo_data_status").text("Creating records");
            //$("#property_demo_data_status").text("Creating records");

            var data = {
                action:     'propertyhive_create_demo_data_records',
                data_items: response,
            };
            jQuery.post( ph_demo_data.ajax_url, data, function(response)
            {
                var plural = 's';
                if ( response.contact == 1 )
                {
                    plural = '';
                }
                //$("#applicant_demo_data_status").text(response.contact + " record" + plural + " created");

                ph_demo_data_sections_done = ph_demo_data_sections_done + 1;

                ph_dd_move_progress_bar(response.contact + " applicant record" + plural + " created");

                 var plural = 's';
                if ( response.property == 1 )
                {
                    plural = '';
                }
                //$("#property_demo_data_status").text(response.property + " record" + plural + " created");

                ph_demo_data_sections_done = ph_demo_data_sections_done + 1;

                ph_dd_move_progress_bar(response.property + " property record" + plural + " created");

                sections_done.push('applicant');
                sections_done.push('property');
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) 
            {
                // Handle error
                console.error('Error when creating applicant and property data:', textStatus, errorThrown);
                alert('An error occurred when creating applicant and property data: ' + textStatus + ' - ' + errorThrown);

                ph_generating_demo_data = false;
                jQuery( "#generate-demo-data" ).val('Generate Demo Data');
            });
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) 
        {
            // Handle error
            console.error('Error when generating applicant and property data:', textStatus, errorThrown);
            alert('An error occurred when generating applicant and property data: ' + textStatus + ' - ' + errorThrown);

            ph_generating_demo_data = false;
            jQuery( "#generate-demo-data" ).val('Generate Demo Data');
        });

        waitForElement( sections_done );
    });

    function waitForElement( sections_done )
    {
        if ( sections_done.includes( 'applicant' ) && sections_done.includes( 'property' ) )
        {
            create_second_data_set();
        }
        else
        {
            setTimeout(function() {
                waitForElement(sections_done);
            }, 1000);
        }
    }

    function create_second_data_set( )
    {
        var second_sections = [];
        $('input[name="sub_sections[]"]').each(function() {
            second_sections.push($(this).val());
        });

        /*$.each(second_sections, function( key, section ) 
        {
            var capitalizedSection = section.charAt(0).toUpperCase() + section.slice(1);

            $("#demo_data_other_results").append(capitalizedSection + " : <span id=\"" + section + "_demo_data_status\">Getting data</span><br>");
        });*/

        var data = {
            action:  'propertyhive_get_section_demo_data',
            section: second_sections
        };
        jQuery.post( ph_demo_data.ajax_url, data, function(response)
        {
            // all sections returned. Now loop through the post types and fire create data for each
            
            for ( var j in second_sections )
            {
                var data_items_to_use = new Array();

                for ( var i in response )
                {
                    if ( response[i].post.post_type == second_sections[j] )
                    {
                        data_items_to_use.push(response[i]);
                    }
                }

                //$("#" + second_sections[j] + "_demo_data_status").text("Creating records");

                var data = {
                    action:     'propertyhive_create_demo_data_records',
                    data_items: data_items_to_use,
                };
                jQuery.post( ph_demo_data.ajax_url, data, function(response)
                {
                    for ( var i in response )
                    {
                        var plural = 's';
                        if ( response[i] == 1 )
                        {
                            plural = '';
                        }
                        //$("#" + i + "_demo_data_status").text(response[i] + " record" + plural + " created");

                        ph_demo_data_sections_done = ph_demo_data_sections_done + 1;

                        ph_dd_move_progress_bar(response[i] + " " + i + " record" + plural + " created");
                    }
                }, 'json').fail(function(jqXHR, textStatus, errorThrown) 
                {
                    // Handle error
                    console.error('Error when creating data:', textStatus, errorThrown);
                    alert('An error occurred when creating data: ' + textStatus + ' - ' + errorThrown);

                    ph_generating_demo_data = false;
                    jQuery( "#generate-demo-data" ).val('Generate Demo Data');
                });
            };
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) 
        {
            // Handle error
            console.error('Error when generating data:', textStatus, errorThrown);
            alert('An error occurred when generating data: ' + textStatus + ' - ' + errorThrown);

            ph_generating_demo_data = false;
            jQuery( "#generate-demo-data" ).val('Generate Demo Data');
        });;
    }
});