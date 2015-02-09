jQuery(document).ready(function($){

	function update_mailchimp_groups( list, eventID ){
		var mci_data = {list_id:list, event_id:eventID};
		jQuery.ajax({
						type: 'POST',
						url: ajaxurl,
						data:{
							mci_data : mci_data,
							action : 'espresso_mailchimp_update_groups'
						},
						beforeSend: function() {
							$('#espresso-mci-groups-list').fadeOut( function() {
								$('#ee-mailchimp-ajax-loading-groups').show();
							});
						},
						success: function( response ) {
							$('#ee-mailchimp-ajax-loading-groups').hide();
							$('#espresso-mci-groups-list').html(response).fadeIn();
						}
					});
	}

	function update_mailchimp_list_fields( list, eventID ){
		var mci_data = {list_id:list, event_id:eventID};
		jQuery.ajax({
						type: 'POST',
						url: ajaxurl,
						data:{
							mci_data : mci_data,
							action : 'espresso_mailchimp_update_list_fields'
						},
						beforeSend: function() {
							$('#espresso-mci-list-fields').fadeOut( function() {
								$('#ee-mailchimp-ajax-loading-fields').show();
							});
						},
						success: function( response ) {
							$('#ee-mailchimp-ajax-loading-fields').hide();
							$('#espresso-mci-list-fields').html(response).fadeIn();
						}
					});
	}

	function getUrlVars() {
		var vars = {};
		var parts = window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, function( m, key, value ) {
			vars[key] = value;
		});
		return vars;
	}



	$( '#ee-mailchimp-lists' ).change( function(){
		var list = $(this).val();
		var eventID = '';
		var UrlVars = getUrlVars();
		if ( UrlVars.post !== undefined ) {
			eventID = UrlVars.post;
		}
		update_mailchimp_groups(list, eventID);
		update_mailchimp_list_fields(list, eventID);
	} );


});

