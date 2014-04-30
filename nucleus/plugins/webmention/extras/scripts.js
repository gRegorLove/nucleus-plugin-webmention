
$(document).ready(function() {
	$('#webmention_form').on('submit', function(e) {
		e.preventDefault();

		var element = $(this);

		$.ajax({
			// REPLACE URL WITH YOUR WEBMENTION ENDPOINT
			url: 'http://example.com/webmention/',
			type: 'POST',
			dataType: 'json',
			accepts: {
				json: 'application/json'
			},
			data: {
				source: element.find('input[name="source"]').val(),
				target: element.find('input[name="target"]').val()
			},
			statusCode: {
				202: function(data) {
					message = 'Thanks! ' + data.response;
					webmention_message(message, 'success');
				},
				400: function(e) {
					message = 'Uh-oh, an error occured. ' + e.responseJSON.response;
					webmention_message(message, 'attention');
				},
				500: function(e) {
					message = 'Uh-oh, an error occured. ' + e.responseJSON.response;
					webmention_message(message, 'attention');
				}

			}
		});

	});

	function webmention_message(message, css_class)
	{

		if ( $('#webmention_message').length )
		{
			$('#webmention_message').slideUp(400, function() {
				$(this).empty();
				$('</p>').text(message).addClass(css_class).appendTo($(this));
				$(this).slideDown(400);
			});
		}
		else
		{
			$('</p>').text(message).addClass(css_class).appendTo($('#webmention_message'));

			$('#webmention_message').slideDown(400);
		}

	}
});
