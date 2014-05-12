<?php

class NP_Webmention extends NucleusPlugin
{
	/**
	 * The log table name for received webmentions
	 * @var string
	 * @access private
	 */
	private $table_received_log;


	/**
	 * The log table name for sent webmentions
	 * @var string
	 * @access private
	 */
	private $table_sent_log;


	/**
	 * The domain whitelist table name
	 * @var string
	 * @access private
	 */
	private $table_whitelist;


	/**
	 * The domain blacklist table name
	 * @var string
	 * @access private
	 */
	private $table_blacklist;


	/**
	 * The table name for processed webmentions
	 * @var string
	 * @access private
	 */
	private $table;


	/**
	 * An array of whitelisted domains when processing webmentions
	 * @var array
	 * @access private
	 */
	private $whitelisted_domains = array();


	/**
	 * An array of blacklisted domains when processing webmentions
	 * @var array
	 * @access private
	 */
	private $blacklisted_domains = array();


	/**
	 * HTTP response codes
	 * @var array
	 * @access protected
	 */
	protected $responses = array(
		'200'	=> 'HTTP/1.1 200 OK',
		'202'	=> 'HTTP/1.1 202 Accepted',
		'400'	=> 'HTTP/1.1 400 Bad Request',
		'404'	=> 'HTTP/1.1 404 Not Found',
		'500'	=> 'HTTP/1.1 500 Internal Server Error',
	);


	/**
	 * This method returns the plugin name
	 * @access public
	 * @return string
	 */
	public function getName()
	{
		return 'Webmention';
	} # end method getName()


	/**
	 * This method returns the author name
	 * @access public
	 * @return string
	 */
	public function getAuthor()
	{
		return 'gRegor Morrill';
	} # end method getAuthor()


	/**
	 * This method returns the plugin author URL
	 * @access public
	 * @return string
	 */
	public function getURL()
	{
		return 'http://gregorlove.com';
	} # end method getURL()


	/**
	 * This method returns the plugin version
	 * @access public
	 * @return string
	 */
	public function getVersion()
	{
		return '0.5';
	} # end method getVersion()


	/**
	 * This method returns the plugin description
	 * @return string
	 */
	public function getDescription()
	{
		return 'Webmention is a simple way to automatically notify any URL when you link to it on your site. From the receiver\'s perpective, it is a way to request notification when other sites link to it. http://webmention.org';
	} # end method getDescription()


	/**
	 * This method returns the plugin table list
	 * @access public
	 * @return array
	 */
	public function getTableList()
	{
		return array(
			sql_table('plugin_webmention_received_logs'), 
			sql_table('plugin_webmention_sent_logs'), 
			sql_table('plugin_webmentions')
		);
	} # end method getTableList()


	/**
	 * This method returns the events this plugin subscribes to
	 * @access public
	 * @return array
	 */
	public function getEventList()
	{
		return array(
			'PreSendContentType', 
			'PostAddItem', 
			'PostUpdateItem',
			'PostDeleteItem'
		);
	} # end method getEventList()


	/**
	 * This method indicates the plugin has an admin area
	 * @access public
	 * @return int
	 */
	public function hasAdminArea()
	{
		return 1;
	} # end method hasAdminArea()


	/**
	 * This method handles initializing the plugin
	 * @param array 
	 * @access public
	 * @return 
	 */
	public function init()
	{
		# Set the table names
		$this->table_received_log = sql_table('plugin_webmention_received_logs');
		$this->table_sent_log = sql_table('plugin_webmention_sent_logs');
		$this->table_whitelist = sql_table('plugin_webmention_whitelist');
		$this->table_blacklist = sql_table('plugin_webmention_blacklist');
		$this->table = sql_table('plugin_webmentions');
	} # end method init()


	/**
	 * This method installs the plugin
	 * @access public
	 */
	public function install()
	{
		$query = <<< END
CREATE TABLE IF NOT EXISTS `{$this->table_received_log}` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`post_id` int(10) unsigned NOT NULL DEFAULT '0',
	`key` varchar(32) NOT NULL DEFAULT '',
	`source` varchar(255) NOT NULL DEFAULT '',
	`target` varchar(255) NOT NULL DEFAULT '',
	`response` text,
	`parsed_content` text,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`processed` timestamp NULL DEFAULT NULL,
	`deleted` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `MD5KEY` (`key`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
END;
		sql_query($query);

		$query = <<< END
CREATE TABLE IF NOT EXISTS `{$this->table_sent_log}` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`post_id` int(10) unsigned NOT NULL DEFAULT '0',
	`endpoint` varchar(255) NOT NULL DEFAULT '',
	`body` text,
	`response` text,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`deleted` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
END;
		sql_query($query);

		$query = <<< END
CREATE TABLE `{$this->table_whitelist}` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`hostname` varchar(255) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`deleted` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
END;
		sql_query($query);

		$query = <<< END
CREATE TABLE `{$this->table_blacklist}` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`hostname` varchar(255) NOT NULL DEFAULT '',
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`deleted` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
END;
		sql_query($query);

		$query = <<< END
CREATE TABLE IF NOT EXISTS `{$this->table}` (
	`id` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`post_id` int(10) unsigned NOT NULL DEFAULT '0',
	`log_id` int(10) unsigned NOT NULL DEFAULT '0',
	`type` enum('reply','mention') NOT NULL DEFAULT 'mention',
	`is_like` tinyint(3) unsigned NOT NULL DEFAULT '0',
	`is_repost` tinyint(3) unsigned NOT NULL DEFAULT '0',
	`is_rsvp` tinyint(3) unsigned NOT NULL DEFAULT '0',
	`content` text,
	`url` varchar(255) NOT NULL DEFAULT '',
	`author_name` varchar(255) NOT NULL DEFAULT '',
	`author_photo` varchar(255) NOT NULL DEFAULT '',
	`author_logo` varchar(255) NOT NULL DEFAULT '',
	`author_url` varchar(255) NOT NULL DEFAULT '',
	`published` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`updated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`published_offset` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`updated_offset` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`is_displayed` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`is_blacklisted` tinyint(1) unsigned NOT NULL DEFAULT '0',
	`deleted` timestamp NULL DEFAULT NULL,
	PRIMARY KEY (`id`),
	UNIQUE KEY `POSTWEBMENTION` (`post_id`,`log_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8
END;
		sql_query($query);

		$this->createOption('timezone', 'Timezone to use when displaying webmentions. Refer to PHP list of supported timezones.', 'text', 'UTC');
		$this->createOption('cron_key', 'Secret key for running the cron', 'text', 'nuMuzup3sW');
		$this->createOption('custom_endpoint', 'Custom webmention endpoint', 'text');
		$this->createOption('deletetables', 'Delete this plugin\'s tables and data when uninstalling?', 'yesno', 'no');
	} # end method install()


	/**
	 * This method uninstalls the plugin
	 * @access public
	 */
	public function unInstall()
	{
		# if: plugin set to delete tables on uninstall
		if ( $this->getOption('deletetables') == 'yes' )
		{
			sql_query('DROP TABLE IF EXISTS ' . $this->table_received_log);
			sql_query('DROP TABLE IF EXISTS ' . $this->table_sent_log);
			sql_query('DROP TABLE IF EXISTS ' . $this->table);
		} # end if

	} # end method unInstall()


	/**
	 * This method handles sending the webmention endpoint as a Link: header
	 * @param string $contentType
	 * @param string $charset
	 * @param string $pageType 
	 * @access public
	 */
	public function event_PreSendContentType($contentType, $charset, $pageType)
	{
		$endpoint = $this->getOption('custom_endpoint');

		# if: no custom endpoint; build the action.php link
		if ( empty($endpoint) )
		{
			global $CONF;
			$endpoint = sprintf('%saction.php?action=plugin&name=Webmention&type=endpoint', $CONF['IndexURL']);
		} # end if

		$header_link = sprintf('Link: <%s>; rel="webmention"', $endpoint);

		header($header_link);
	} # end method event_PreSendContentType()


	/**
	 * This event hook fires after a post is added.
	 * The post is handed off to processPost() to send webmentions to any URLs 
	 * @param array $data
	 * @access public
	 */
	public function event_PostAddItem($data)
	{
		$this->processPost($data['itemid']);
	} # end method event_PostAddItem()


	/**
	 * This event hook fires after a post is updated.
	 * The post is handed off to processPost() to send webmentions to any URLs 
	 * @param array $data
	 * @access public
	 */
	public function event_PostUpdateItem($data)
	{
		$this->processPost($data['itemid']);
	} # end method event_PreSendContentType()


	/**
	 * This method handles the plugin's public-facing actions
	 * @param string $type 
	 * @access public
	 */
	public function doAction($type)
	{
		global $CONF;

		switch ( $type )
		{
			case 'endpoint':
				$this->receiveWebmention();
			break;

			default:
				$this->httpResponse(404, 'text/html', 'No page found at that URL.');
			break;
		}

	} # end method doAction()


	/**
	 * This method handles the skin/template variables
	 * @param object &$item
	 * @param string $field
	 * @access public
	 **/
	public function doSkinVar($skin_type, $type = '')
	{
		/*
		# For debugging
		error_reporting(E_ALL);
		ini_set('display_errors', 1);
		*/

		global $CONF, $itemid, $member;

		# if: display all approved webmentions
		if ( $type == 'mentions' )
		{
			$log_table = $this->table_received_log;
			$item_table = sql_table('item');

			sql_query("SET @@session.time_zone = 'UTC'");

			$query = <<< END
SELECT 
	`w`.`author_photo`,
	`w`.`author_url`,
	`w`.`author_name`,
	`w`.`type`,
	IF(`w`.`type` = 'reply', 'replied to', 'mentioned') AS `verb`,
	`i`.`ititle` AS `post_title`,
	`l`.`target`,
	`w`.`url`,
	`w`.`content`,
	`w`.`updated`
FROM 
	`{$this->table}` AS `w`
	LEFT OUTER JOIN `{$item_table}` AS `i` ON `w`.`post_id` = `i`.`inumber`
	LEFT OUTER JOIN `{$log_table}` AS `l` ON `w`.`log_id` = `l`.`id`
WHERE 
	`is_displayed` = 1
	AND `is_blacklisted` = 0
	AND `deleted` IS NULL
ORDER BY 
	`updated` DESC
LIMIT 20
END;
			$result = sql_query($query);

			# if: one or more results
			if ( sql_num_rows($result) > 0 )
			{
				$hostname = parse_url($CONF['IndexURL'], PHP_URL_HOST);

				print <<< END
	<div class="h-feed">

		<h1 class="p-name"> {$hostname} mentions </h1>
		<a class="u-url" href=""></a>

END;

				# loop: each row
				while ( $row = sql_fetch_assoc($result) )
				{
					$h_card = sprintf('<a href="%s" class="p-author h-card"><img src="%s" alt="%s" title="%3$s" class="u-photo" /></a> ',
						$row['author_url'],
						$row['author_photo'],
						$row['author_name']
					);

					$webmention_context = sprintf('<p class="reply-context"> <strong>%s</strong> %s “<a href="%s" class="u-in-reply-to">%s</a>” </p>',
						$row['author_name'],
						$row['verb'],
						$row['target'],
						$row['post_title']
					);

					switch ( $row['type'] )
					{
						case 'reply':
							$content = sprintf('<p class="p-content p-name"> %s </p>', 
								$row['content']
							);
						break;

						default:
							$content = '';
						break;
					}

					$published_timezone = new DateTimeZone('UTC');
					$published = new DateTime($row['updated'], $published_timezone);

					# get the timezone plugin option
					$timezone = $this->getOption('timezone');

					# if: timezone option is not UTC; convert the date-time
					if ( $timezone != 'UTC' )
					{
						$convert_timezone = new DateTimeZone($timezone);
						$published->setTimezone($convert_timezone);
					} # end if

					$via = parse_url($row['url'], PHP_URL_HOST);

					$published = sprintf('<p class="reply-date"> <time class="dt-published" datetime="%s" title="via %s"><a href="%s" class="u-url">%s</a></time> </p>',
						$published->format('c'),
						$via,
						$row['url'],
						$published->format('F j, Y g:ia T')
					);

					echo '<div class="mention h-entry">';

					echo sprintf('<div class="avatar"> %s </div>',
						$h_card
					);

					echo sprintf('<div class="note"> %s %s %s </div>',
						$webmention_context,
						$content,
						$published
					);

					echo '</div> <!-- /.h-entry -->';

					echo '<hr />';
				} # end loop

				echo '</div> <!--/.h-feed -->';
			} # end if

		}
		# else if: responses
		else if ( $type == 'responses' )
		{
			sql_query("SET @@session.time_zone = 'UTC'");

			$comment_table = sql_table('comment');
			$member_table = sql_table('member');

			$query = <<< END
SELECT
	`w`.`id` AS `webmention_id`,
	0 AS `comment_id`,
	`w`.`type`,
	`w`.`is_like`,
	`w`.`is_repost`,
	`w`.`is_rsvp`,
	`w`.`content`,
	`w`.`url`,
	`w`.`author_name`,
	`w`.`author_photo`,
	`w`.`author_url`,
	`w`.`updated`
FROM
	`{$this->table}` AS `w`
WHERE
	`is_displayed` = 1
	AND `post_id` = {$itemid}
	AND `deleted` IS NULL

UNION ALL

SELECT
	0 AS `webmention_id`,
	`c`.`cnumber` AS `comment_id`,
	'local' AS `type`,
	0 AS `is_like`,
	0 AS `is_repost`,
	0 AS `is_rsvp`,
	`cbody` AS `content`,
	'' AS `url`,
	IF (`cuser` = '', `mname`, `cuser`) AS `author_name`,
	'' AS `author_photo`,
	IF(`cmail` = '', `murl`, `cmail`) AS `author_url`,
	`ctime` AS `updated`
FROM
	`{$comment_table}` AS `c`
	LEFT OUTER JOIN `{$member_table}` ON `cmember` = `mnumber`
WHERE
	`c`.`citem` = $itemid

ORDER 
	BY `updated` ASC
END;
			$result = sql_query($query);

			# if: one or more results
			if ( sql_num_rows($result) > 0 )
			{
				$hostname = parse_url($CONF['IndexURL'], PHP_URL_HOST);

				print <<< END
	<style type="text/css">
		.responses .note p {
			font-size: 0.8em;
			line-height: 1.6;
		}
	</style>

	<div class="responses">
END;

				# loop: each row
				while ( $row = sql_fetch_assoc($result) )
				{

					# if: local comment
					if ( $row['type'] == 'local' )
					{
						$element_id = sprintf('c%d', $row['comment_id']);

						$h_card = '&nbsp;';

						if ( $row['author_url'] )
						{
							$author_link = sprintf('<a href="%s" class="p-author h-card">%s</a>',
								$row['author_url'],
								$row['author_name']
							);
						}
						else
						{
							$author_link = sprintf('<span class="p-author h-card">%s</span>',
								$row['author_name']
							);
						}

						$webmention_context = sprintf('<p class="reply-context"> <strong>%s</strong>: </p>',
							$author_link
						);

						$content = sprintf('<div class="p-content p-name"> %s </div>',
							$row['content']
						);

						# get the timezone plugin option
						$timezone = $this->getOption('timezone');
						$convert_timezone = new DateTimeZone($timezone);
						$published = new DateTime($row['updated'], $convert_timezone);

						$published = sprintf('<p class="reply-date"> <time class="dt-published" datetime="%s"><a href="%s" class="u-url">%s</a></time> </p>',
							$published->format('c'),
							'#c' . $row['comment_id'],
							$published->format('F j, Y g:ia T')
						);
					}
					# else: webmention
					else
					{
						$element_id = sprintf('w%d', $row['webmention_id']);

						$h_card = sprintf('<a href="%s" class="p-author h-card"><img src="%s" alt="%s" title="%3$s" class="u-photo" /></a> ',
							$row['author_url'],
							$row['author_photo'],
							$row['author_name']
						);

						# if: mentioned
						if ( $row['type'] == 'mention' )
						{
							$verbs = array();

							# if: liked
							if ( $row['is_like'] )
							{
								$verbs[] = 'liked';
							}
							else
							{
								$verbs[] = 'mentioned';
							}

							$verb_phrase = sprintf(' %s this', implode(', ', $verbs));
						}
						# else: replied
						else
						{
							$verb_phrase = ':';
						} # end if

						$webmention_context = sprintf('<p class="reply-context"> <strong><a href="%s">%s</a></strong>%s </p>',
							$row['author_url'],
							$row['author_name'],
							$verb_phrase
						);

						switch ( $row['type'] )
						{
							case 'reply':
								$content = sprintf('<p class="p-content p-name"> %s </p>', 
									$row['content']
								);
							break;

							default:
								$content = '';
							break;
						}

						$published_timezone = new DateTimeZone('UTC');
						$published = new DateTime($row['updated'], $published_timezone);

						# get the timezone plugin option
						$timezone = $this->getOption('timezone');

						# if: timezone option is not UTC; convert the date-time
						if ( $timezone != 'UTC' )
						{
							$convert_timezone = new DateTimeZone($timezone);
							$published->setTimezone($convert_timezone);
						} # end if

						$via = parse_url($row['url'], PHP_URL_HOST);

						$published = sprintf('<p class="reply-date"> <time class="dt-published" datetime="%s" title="via %s"><a href="%s" class="u-url">%s</a></time> </p>',
							$published->format('c'),
							$via,
							$row['url'],
							$published->format('F j, Y g:ia T')
						);
					} # end if

					echo sprintf('<div id="%s" class="mention p-comment h-cite">', $element_id);

					echo sprintf('<div class="avatar"> %s </div>',
						$h_card
					);

					echo sprintf('<div class="note"> %s %s %s </div>',
						$webmention_context,
						$content,
						$published
					);

					echo '</div> <!-- /.h-cite -->';

					echo '<hr />';
				} # end loop: each row

				echo '</div> <!--/.h-feed -->';
			} # end if

		}
		# else if: webmention form
		else if ( $type == 'form' )
		{
			$endpoint = $this->getOption('custom_endpoint');

			# if: no custom endpoint; build the action.php link
			if ( empty($endpoint) )
			{
				$endpoint = sprintf('%saction.php?action=plugin&name=Webmention&type=endpoint', $CONF['IndexURL']);
			} # end if

			print <<< END
	<form method="post" action="{$endpoint}" id="webmention_form">
	<h2> Send a Response </h2>
	<p> <label for="i_webmention_source">Have you written a response to this? Let me know the link:</label> </p>
	<p> <input type="text" name="source" id="i_webmention_source" required /> </p>
	<div id="webmention_message"></div>
	<p> <input type="submit" value="Send" /> </p>
	<input type="hidden" name="target" value="{$_SERVER['SCRIPT_URI']}" />
	</form>

	<script>
		$(document).ready(function() {
			$('#webmention_form').on('submit', function(e) {
				e.preventDefault();

				var element = $(this);

				$.ajax({
					url: '{$endpoint}',
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
	</script>
END;
		} # end if

	} # end method doSkinVar()


	/**
	 * This method handles processing a post and sending webmentions to the URLs in it
	 * @param int $item_id
	 * @param string $text 
	 * @access private
	 */
	private function processPost($item_id)
	{
		/*		
		# For debugging
		header('Content-Type: text/plain; charset=utf8');
		ini_set('display_errors',1);
		error_reporting(E_ALL);
		*/

		global $CONF;

		$item = Item::getitem($item_id, 0, 0);

		# if: pathinfo URL mode
		if ( $CONF['URLMode'] == 'pathinfo' )
		{
			$permalink = sprintf('%s/%s/%s', $CONF['ItemURL'], $CONF['ItemKey'], $item_id);
		}
		# else: normal mode
		else
		{
			# attempt to build custom permalink
			$permalink = $this->buildPermalink($item);

			# if: no custom permalink; use default
			if ( empty($permalink) )
			{
				$permalink = sprintf('%s?itemid=%d', $CONF['ItemURL'], $item_id);
			} # end if

		} # end if

		# array of URLs already webmentioned; avoid sending duplicates
		$mentioned = array();

		# default the URL matches array
		$matches = array();

		# match all URLs in the body text
		$pattern_href = '#href\s*=\s*[\'\"]?([^\'" >]+)#i';
		preg_match_all($pattern_href, $item['body'], $matches, PREG_SET_ORDER);

		# loop: each match set
		foreach ( $matches as $match_set )
		{
			$url = array_pop($match_set);

			# if: URL starts with http:// or https:// and has not already been mentioned
			if ( (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0) && !in_array($url, $mentioned) )
			{
				$mentioned[] = $url;

				$endpoint_data = $this->discovery($url);

				# if: endpoint found for URL
				if ( $endpoint_data )
				{
					$this->sendWebmention($endpoint_data, $permalink, $url);
				} # end if

			} # end if

		} # end loop

	} # end method processPost()


	/**
	 * This method handles discovery of webmention endpoint
	 * @param string $url 
	 * @access private
	 * @return string
	 */
	private function discovery($url)
	{
		$headers = $this->fetchHead($url);

		## 1. Look for Link: header with webmention rel-value

		# if: link headers
		if ( $headers['link'] )
		{

			# loop: each header link
			foreach ( $headers['link'] as $header )
			{
				# match rel=webmention link header
				preg_match('#\<(.*?)\>\s*\;\s*rel\=\"?(?:.*?)webmention(?:.*?)\"?#', $header, $matches);

				# if: rel=webmention link header found
				if ( count($matches) == 2 )
				{
					return array(
						'type'		=> 'webmention',
						'endpoint'	=> array_pop($matches)
					);
				} # end if

			} # end loop

		} # end if: link headers

		## 2. Look for <link> elements with webmention rel-value

		$links = $this->fetchLinkElements($url);

		# if: webmention <link> element found
		if ( !empty($links['webmention']) )
		{
			return array(
				'type'		=> 'webmention',
				'endpoint'	=> reset($links['webmention'])
			);
		} # end if

		## 3. Look for X-Pingback: header 

		# if: X-Pingback: header found
		if ( $headers['pingback'] )
		{
			return array(
				'type'		=> 'pingback',
				'endpoint'	=> reset($headers['pingback'])
			);
		} # end if: link headers

		## 4. Look for <link> elements with pingback rel-value

		# if: webmention <link> element found
		if ( !empty($links['pingback']) )
		{
			return array(
				'type'		=> 'pingback',
				'endpoint'	=> reset($links['pingback'])
			);
		} # end if

		return NULL;
	} # end method discovery()


	/**
	 * This method handles sending a webmention
	 * @param array $endpoint_data 
	 * @param string $source
	 * @param string $target
	 * @access private
	 */
	private function sendWebmention($endpoint_data, $source, $target)
	{
		/*
		# For debugging
		header('Content-Type: text/plain; charset=utf8');
		ini_set('display_errors',1);
		error_reporting(E_ALL);
		*/

		$endpoint = $endpoint_data['endpoint'];

		# if: 
		if ( $endpoint_data['type'] == 'pingback' )
		{
			$data = xmlrpc_encode_request('pingback.ping', array($source, $target), array('verbosity' => 'no_white_space', 'encoding' => 'utf-8'));

			$headers = array(
				'Content-Type: application/xml'
			);
		}
		# else: 
		else
		{
			$data = sprintf('source=%s&target=%s', $source, $target);

			$headers = array(
				'Accept: application/json'
			);
		}
		# print_r($data); print_r($endpoint); exit;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $endpoint);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		$response = @curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		curl_close($ch);

		# if: curl error
		if ( $errno )
		{
			global $CONF;
			$to = $CONF['AdminEmail'];
			$from = $CONF['AdminEmail'];
			$sendmail = '-f' . $from;

			$headers = <<< END
From: {$from}
MIME-Version: 1.0
Content-Type: text/plain; charset=utf-8
END;

			$message = sprintf('%s \n\n%s \n\n%s \n\n%s', $endpoint, $data, $errno, $error);

			@mail($to, 'Webmention error', $message, $headers, $sendmail);
			return;
		} # end if

		$sql_endpoint = sql_real_escape_string($endpoint);
		$sql_body = sql_real_escape_string($data);
		$sql_response = sql_real_escape_string($response);

		$sql = <<< END
INSERT INTO `{$this->table_sent_log}` SET
	`post_id` = 0, 
	`endpoint` = '$sql_endpoint',
	`body` = '$sql_body',
	`response` = '$sql_response',
	`created` = NULL, 
	`modified` = NULL
END;
		sql_query($sql);
	} # end method sendWebmention()


	/**
	 * This method handles incoming webmentions.
	 * Basic validations are performed, but the source URL is not parsed/verified.
	 * @access private
	 */
	private function receiveWebmention()
	{
		global $CONF;

		# TODO: Take into account the q= quality factor of Accept: headers.
		$http_accept = serverVar('HTTP_ACCEPT');

		if ( $http_accept_override = requestVar('HTTP_ACCEPT') )
		{
			$http_accept = $http_accept_override;
		}

		if ( strpos($http_accept, 'application/json') !== FALSE )
		{
			$content_type = 'application/json';
		}
		else if ( strpos($http_accept, 'text/plain') !== FALSE )
		{
			$content_type = 'text/plain';
		}
		else
		{
			$content_type = 'text/html';
		}

		$is_www_form_urlencoded = strpos(serverVar('CONTENT_TYPE'), 'application/x-www-form-urlencoded');

		# if: request is the correct content-type
		if ( (serverVar('REQUEST_METHOD') === 'POST') && ($is_www_form_urlencoded !== FALSE) )
		{
			$source = requestVar('source');
			$target = requestVar('target');

			# if: source is missing
			if ( empty($source) )
			{
				$this->httpResponse(400, $content_type, 'Webmention "source" parameter is missing.');
			} # end if

			# if: target is missing
			if ( empty($target) )
			{
				$this->httpResponse(400, $content_type, 'Webmention "target" parameter is missing.');
			} # end if

			$id = 0;
			$url_parts = parse_url($CONF['IndexURL']);
			$target_url_parts = parse_url($target);

			# if: invalid host name
			if ( strpos($target_url_parts['host'], $url_parts['host']) === FALSE )
			{
				$this->httpResponse(400, $content_type, 'Webmention "target" is not a valid URL at this domain.');
			} # end if

			# if: pathinfo URL mode
			if ( $CONF['URLMode'] == 'pathinfo' )
			{
				$regex_pattern = sprintf('#\/%s\/(\d+)\/?#', $CONF['ItemURL']);

				preg_match($regex_pattern, $target_url_parts['path'], $matches);

				# if: id found in path
				if ( count($matches) === 2 )
				{
					$id = array_pop($matches);
				} # end if
			}
			# else: normal URL mode
			else
			{

				# if: query string
				if ( !empty($target_url_parts['query']) )
				{
					preg_match('#(?:^|&)id=(\d+)#', $target_url_parts['query'], $matches);

					# if: 'id=X' found
					if ( count($matches) === 2 )
					{
						$id = array_pop($matches);
					} # end if

				}
				# else: parse custom path
				else
				{
					preg_match('#\/(?:\d+)\/(?:\d+)\/(\d+)\/?#', $target_url_parts['path'], $matches);

					# if: id found in path
					if ( count($matches) === 2 )
					{
						$id = array_pop($matches);
					} # end if

				} # end if

			} # end if

			# if: could not extract post ID
			if ( empty($id) )
			{
				$this->httpResponse(400, $content_type, 'Webmention "target" is not a valid URL at this domain.');
			} # end if

			global $manager;

			# if: no post with that ID
			if ( !$manager->existsItem($id, 0, 0) )
			{
				$this->httpResponse(400, $content_type, 'Webmention "target" is not a valid URL at this domain.');
			} # end if

			$post_id = intval($id);
			$sql_md5 = md5($source . $target);
			$sql_source = sql_real_escape_string($source);
			$sql_target = sql_real_escape_string($target);

			$query = <<< END
INSERT INTO `{$this->table_received_log}` SET
	`post_id` = {$post_id}, 
	`key` = '{$sql_md5}',
	`source` = '{$sql_source}', 
	`target` = '{$sql_target}', 
	`created` = NULL, 
	`modified` = NULL
ON DUPLICATE KEY UPDATE 
	`modified` = NULL
END;
			$result = sql_query($query);

			# if: query error
			if ( $result === FALSE )
			{
				$this->httpResponse(500, $content_type, 'There was an error processing the webmention.');
			} # end if

			$this->httpResponse(202, $content_type, 'Webmention queued for processing.');
		}
		# else: incorect content-type
		else
		{
			$this->httpResponse(400, $content_type, 'Webmention must be posted with Content-Type: application/x-www-url-form-encoded.');
		} # end if

	} # end method receiveWebmention()


	/**
	 * This method handles processing the received webmentions.
	 * It is verified that the target URL appears in the source, and
	 * h-entry/h-card are parsed from the source if possible.
	 * @access public
	 */
	public function processWebmentions()
	{
		include('webmention/Mf2/Parser.php');
		include('webmention/Mf2/Functions.php');

		$default_fields = array(
			'post_id'			=> '',
			'type'				=> 'mention',
			'is_like'			=> 0,
			'is_repost'			=> 0,
			'is_rsvp'			=> 0,
			'content'			=> '',
			'url'				=> '',
			'author_name'		=> '',
			'author_photo'		=> '',
			'author_logo'		=> '',
			'author_url'		=> '',
			'published'			=> '',
			'updated'			=> '',
			'published_offset'	=> 0,
			'updated_offset'	=> 0,
		);

		sql_query("SET @@session.time_zone = 'UTC'");

		$sql = <<< END
SELECT *
FROM
	`{$this->table_received_log}`
WHERE
	(
		`processed` IS NULL
		OR `modified` > `processed`
	)
	AND `deleted` IS NULL
END;
		$result = sql_query($sql);

		# if: one or more results
		if ( sql_num_rows($result) > 0 )
		{

			# loop: each result row
			while ( $row = sql_fetch_assoc($result) )
			{
				$sql_log_id = $sql_id = intval($row['id']);
				$sql_post_id = intval($row['post_id']);

				$webmention = array(
					'url'	=> $row['source']
				);

				$parsed_content = '';

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
				curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
				curl_setopt($ch, CURLOPT_URL, $row['source']);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				$html = curl_exec($ch);
				$info = curl_getinfo($ch);

				# if: webmention source is HTTP 410, mark it as deleted
				if ( $info['http_code'] == 410 )
				{
					$sql = <<< END
UPDATE `{$this->table}` SET
	`deleted` = NOW()
WHERE 
	`log_id` = {$sql_log_id}
END;
					$secondary_result = sql_query($sql);

					$sql = <<< END
UPDATE `{$this->table_received_log}` SET
	`processed` = NOW()
WHERE
	`id` = {$sql_id}
LIMIT 1
END;
					$secondary_result = sql_query($sql);
				}
				# else if: target URL not found in source URL
				else if ( strpos($html, $row['target']) === FALSE )
				{
					$content = sprintf('%s not found in %s', $row['target'], $row['source']);
					$sql_parsed_content = sql_real_escape_string($content);
					$sql = <<< END
UPDATE `{$this->table_received_log}` SET
	`parsed_content` = '{$sql_parsed_content}',
	`processed` = NOW()
WHERE
	`id` = {$sql_id}
LIMIT 1
END;
					$secondary_result = sql_query($sql);
				}
				# else: target URL found in source URL
				else
				{

					# if: microformat(s) found at URL
					if ( $parsed_content = $this->fetchMicroformats($html) )
					{

						# if: parsed the first h-entry successfully
						if ( $entry = $this->parseFirstEntry($parsed_content, $webmention) )
						{

							# if: author h-card not found
							if ( !$this->parseAuthor($entry, $webmention) )
							{

								# if: rel-author with h-card not found either, use defaults
								if ( !$this->parseRelAuthor($parsed_content, $webmention) )
								{
									$this->parseDefaultAuthor($row['source'], $webmention);
								} # end if

							} # end if: author h-card not found...

						} # end if: parsed the first...

					}
					# else: no microformats found
					else
					{
						$this->parseDefaultAuthor($row['source'], $webmention);
					} # end if: microformat(s) found...		

					# if: if like-of matches target, set the flag
					if ( $webmention['like-of'] == $row['target'] )
					{
						$webmention['is_like'] = 1;
					}

					$webmention = array_merge($default_fields, $webmention);

					$parsed_content = ( empty($parsed_content) ) ? '' : json_encode($parsed_content);
					$sql_parsed_content = sql_real_escape_string($parsed_content);

					$sql = <<< END
UPDATE `{$this->table_received_log}` SET
	`parsed_content` = '{$sql_parsed_content}',
	`processed` = NOW()
WHERE
	`id` = {$sql_id}
LIMIT 1
END;
					$secondary_result = sql_query($sql);

					# if: query error
					if ( $secondary_result === FALSE )
					{
						$this->httpResponse(500, 'text/html', 'There was an error parsing the webmention.');
					} # end if

					$is_whitelisted = $this->is_hostname_listed($row['source'], 'whitelist');
					$is_blacklisted = $this->is_hostname_listed($row['source'], 'blacklist');

					$sql_post_id = intval($row['post_id']);
					$sql_type = sql_real_escape_string($webmention['type']);
					$sql_is_like = intval($webmention['is_like']);
					$sql_is_repost = intval($webmention['is_repost']);
					$sql_is_rsvp = intval($webmention['is_rsvp']);
					$sql_content = sql_real_escape_string($webmention['content']);
					$sql_url = sql_real_escape_string($webmention['url']);
					$sql_author_name = sql_real_escape_string($webmention['author_name']);
					$sql_author_photo = sql_real_escape_string($webmention['author_photo']);
					$sql_author_logo = sql_real_escape_string($webmention['author_logo']);
					$sql_author_url = sql_real_escape_string($webmention['author_url']);
					$sql_published = sql_real_escape_string($webmention['published']);
					$sql_updated = sql_real_escape_string($webmention['updated']);
					$sql_published_offset = sql_real_escape_string($webmention['published_offset']);
					$sql_updated_offset = sql_real_escape_string($webmention['updated_offset']);
					$sql_is_displayed = ( $is_whitelisted ) ? 1 : 0;
					$sql_is_blacklisted = ( $is_blacklisted ) ? 1 : 0;

					$sql = <<< END
INSERT INTO `{$this->table}` SET
	`post_id` = $sql_post_id,
	`log_id` = $sql_log_id,
	`type` = '$sql_type',
	`is_like` = $sql_is_like,
	`is_repost` = $sql_is_repost,
	`is_rsvp` = $sql_is_rsvp,
	`content` = '$sql_content',
	`url` = '$sql_url',
	`author_name` = '$sql_author_name',
	`author_photo` = '$sql_author_photo',
	`author_logo` = '$sql_author_logo',
	`author_url` = '$sql_author_url',
	`published` = '$sql_published',
	`updated` = '$sql_updated',
	`published_offset` = '$sql_published_offset',
	`updated_offset` = '$sql_updated_offset',
	`is_displayed` = $sql_is_displayed,
	`is_blacklisted` = $sql_is_blacklisted
ON DUPLICATE KEY UPDATE
	`is_like` = $sql_is_like,
	`is_repost` = $sql_is_repost,
	`is_rsvp` = $sql_is_rsvp,
	`content` = '$sql_content',
	`url` = '$sql_url',
	`author_name` = '$sql_author_name',
	`author_photo` = '$sql_author_photo',
	`author_logo` = '$sql_author_logo',
	`author_url` = '$sql_author_url',
	`published` = '$sql_published',
	`updated` = '$sql_updated',
	`published_offset` = '$sql_published_offset',
	`updated_offset` = '$sql_updated_offset',
	`is_displayed` = $sql_is_displayed,
	`is_blacklisted` = $sql_is_blacklisted
END;
					$secondary_result = sql_query($sql);
					// echo '<pre>', print_r($sql), '</pre>';

					# if: query error
					if ( $secondary_result === FALSE )
					{
						$this->httpResponse(500, 'text/html', 'There was an error parsing the webmention.');
					} # end if

				} # end if: target URL found...

			} # end loop: each result row

		} # end if: one or more results

		$this->httpResponse(200, 'text/html', 'Webmentions processed successfully');
	} # end method processWebmentions()


	/**
	 * This method handles building a custom permalink, when sending webmentions
	 * @param array $item
	 * @access private
	 * @return string
	 */
	private function buildPermalink($item)
	{
		/**
		 * You can use this method to build a custom permalink.
		 * For example, my permalinks are in the format /YYYY/MM/[Item ID]
		 *
		 * So I use:
		 *
		 * return sprintf('%s/%s/%s/', $CONF['ItemURL'], date('Y/m', $item['timestamp']), $item['itemid']);
		 *
		 * Return an empty string if there is no custom permalink and you
		 * are using the '?itemid=X' URLs
		 *
		 * @TODO: Make this easier so it doesn't require modifying this method
		 */

		global $CONF;

		return '';
	} # end method buildPermalink()

	/**
	 * This method performs a HEAD request and returns an array of headers
	 * @param string $url 
	 * @access private
	 */
	private function fetchHead($url)
	{
		$headers = array(
			'link' => array(),
			'pingback' => array()
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		curl_setopt($ch, CURLOPT_NOBODY, TRUE);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$response = curl_exec($ch);
		curl_close($ch);

		$header_lines = explode("\n", $response);

		# loop: each header line
		foreach ( $header_lines as $header_line )
		{
			$header_line = trim($header_line);

			# if: 
			if ( strpos($header_line, ':') !== FALSE )
			{
				list($type, $value) = explode(':', $header_line, 2);
				$type = trim($type);
				$value = trim($value);

				# if: link header
				if ( strtolower($type) == 'link' )
				{
					$headers['link'][] = $value;
				}
				# else if: pingback header
				else if ( strtolower($type) == 'x-pingback' )
				{
					$headers['pingback'][] = $value;
				} # end if

			} # end if

		} # end loop

		return $headers;
	} # end method fetchHead()


	/**
	 * This method parses the <link> elements from HTML
	 * @param string $url 
	 * @access private
	 */
	private function fetchLinkElements($url)
	{
		$links = array();

		$html = $this->fetchBody($url);

		libxml_use_internal_errors(TRUE);
		$dom = new DOMDocument('1.0', 'UTF-8');
		$dom->loadHTML($html);
		$dom->preserveWhiteSpace = FALSE;

		# get <link> DOMElements
		$link_elements = $dom->getElementsByTagName('link');

		# loop: each <link> DOMElement
		foreach ( $link_elements as $link )
		{
			$rel = $link->getAttribute('rel');
			$href = $link->getAttribute('href');

			if ( empty($links[$rel]) )
			{
				$links[$rel] = array($href);
			}
			else
			{
				$links[$rel][] = $href;
			}

		} # end loop

		return $links;
	} # end method fetchLinkElements()


	/**
	 * This method fetches the body of a URL
	 * @param string $url 
	 * @access private
	 */
	private function fetchBody($url)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		$response = curl_exec($ch);
		curl_close($ch);

		return $response;
	} # end method fetchBody()


	/**
	 * This method handles sending an HTTP response
	 * @param int $http_status
	 * @param string $content_type
	 * @param string $message 
	 * @access private
	 */
	private function httpResponse($http_status, $content_type, $message)
	{

		switch ( $content_type )
		{
			case 'application/json':
				$body = json_encode(array('response' => $message));
			break;

			case 'text/plain':
				$body = $message;
			break;

			default:
			case 'text/html':
				$body = <<< END
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8"/>
	<title>Webmention Response: {$http_status}</title>
</head>

<body>
	<p> {$message} </p>
</body>

</html>
END;
			break;
		}

		$content_header = sprintf('Content-Type: %s; charset=utf8', $content_type);

		header($content_header);
		header($this->responses[$http_status]);
		echo $body;
		exit;
	} # end method httpResponse()


	/**
	 * This method retrieves microformat content from a URL
	 * Returns FALSE if no microformats found at the URL
	 * @param string $source 
	 * @access private
	 * @return mixed
	 */
	private function fetchMicroformats($source)
	{
		# parse the content for microformats
		$parsed = Mf2\parse($source);

		# if: parsed content is microformat collection
		if ( Mf2\isMicroformatCollection($parsed) )
		{
			return $parsed;
		}
		# else: 
		else
		{
			return FALSE;
		} # end if

	} # end method fetchMicroformats()


	/**
	 * This method handles parsing the first h-entry in a microformat collection
	 * Returns FALSE if no h-entry found in the collection
	 * @param array $collection
	 * @param array $webmention
	 * @access private
	 * @return mixed
	 */
	private function parseFirstEntry($collection, &$webmention)
	{
		# find h-entry in collection
		$entries = Mf2\findMicroformatsByType($collection, 'h-entry');

		# if: one or more h-entry found
		if ( $entries )
		{
			$entry = reset($entries);
			// echo '<pre>', print_r($entry); exit;

			$in_reply_to = Mf2\getPlaintext($entry, 'in-reply-to');
			$like_of = Mf2\getPlaintext($entry, 'like-of');
			$repost_of = Mf2\getPlaintext($entry, 'repost-of');
			$rsvp = Mf2\getPlaintext($entry, 'rsvp');

			$webmention['type'] = ( $in_reply_to ) ? 'reply' : 'mention';

			$webmention['in-reply-to'] = $in_reply_to;
			$webmention['like-of'] = $like_of;

			# separate flags for webmentions of multiple types
			$webmention['is_like'] = ( $like_of ) ? 1 : 0;
			$webmention['is_repost'] = ( $repost_of ) ? 1 : 0;
			$webmention['is_rsvp'] = ( $rsvp ) ? 1 : 0;

			$webmention['content'] = Mf2\getPlaintext($entry, 'content');
			$url = Mf2\getPlaintext($entry, 'url');
			$webmention['url'] = ( empty($url) ) ? $webmention['url'] : $url;

			$published = Mf2\getDateTimeProperty('published', $entry, TRUE);
			$array_date = $this->parseDateTime($published);
			$webmention['published_offset'] = $array_date['has_timezone'];
			$webmention['published'] = $array_date['date'];

			$updated = Mf2\getDateTimeProperty('updated', $entry, TRUE);
			$array_date = $this->parseDateTime($published);
			$webmention['updated_offset'] = $array_date['has_timezone'];
			$webmention['updated'] = $array_date['date'];

			return $entry;
		}
		# else: no h-entry found
		else
		{
			return FALSE;
		} # end if

	} # end method parseFirstEntry()


	/**
	 * This method handles parsing the first h-card in a microformat collection
	 * Returns FALSE if no h-card found in the collection
	 * @param array $collection
	 * @param array $results
	 * @access private
	 * @return mixed
	 */
	private function parseFirstCard($collection, &$results)
	{
		# find h-card in collection
		$cards = Mf2\findMicroformatsByType($collection, 'h-card');

		# if: one or more h-card found
		if ( $cards )
		{
			$card = reset($cards);

			$results['author_name'] = Mf2\getPlaintext($card, 'name');
			$results['author_logo'] = Mf2\getPlaintext($card, 'logo');
			$results['author_photo'] = Mf2\getPlaintext($card, 'photo');
			$results['author_url'] = Mf2\getPlaintext($card, 'url');

			return TRUE;
		}
		# else: no h-card found
		else
		{
			return FALSE;
		} # end if

	} # end method parseFirstCard()


	/**
	 * This method handles parsing the author in an h-entry
	 * @param array $entry
	 * @param array $results
	 * @access private
	 * @return mixed
	 */
	private function parseAuthor($entry, &$results)
	{
		$author = Mf2\getAuthor($entry);

		# if: author h-card found
		if ( Mf2\isMicroformat($author) )
		{
			$results['author_name'] = Mf2\getPlaintext($author, 'name');
			$results['author_photo'] = Mf2\getPlaintext($author, 'photo');
			$results['author_logo'] = Mf2\getPlaintext($author, 'logo');
			$results['author_url'] = Mf2\getPlaintext($author, 'url');

			return TRUE;
		}
		# else: no author h-card found
		else
		{
			return FALSE;
		} # end if

	} # end method parseAuthor()


	/**
	 * This method handles parsing the first rel-author on a page
	 * @param array $context
	 * @param array $results
	 * @access private
	 * @return mixed
	 */
	private function parseRelAuthor($context, &$results)
	{

		# if: rel-author in parsed content
		if ( !empty($context['rels']['author']) )
		{
			$url = reset($context['rels']['author']);

			# if: microformat(s) found at rel-author URL
			if ( $parsed_content = $this->fetchMicroformats($url) )
			{
				# parse the first h-card, if any
				return $this->parseFirstCard($parsed_content, &$results);
			} # end if

		}
		# else: no rel-author
		else
		{
			return FALSE;
		} # end if

	} # end method parseRelAuthor()


	/**
	 * This method handles parsing the default author information using the source URL
	 * @param array $context
	 * @param array $results
	 * @access private
	 */
	private function parseDefaultAuthor($url, &$results)
	{
		$url_parts = parse_url($url);

		$results['author_name'] = $url_parts['host'];
		$results['author_url'] = sprintf('%s://%s', $url_parts['scheme'], $url_parts['host']);
	} # end method parseDefaultAuthor()


	/**
	 * This method handles parsing a date string into Y-m-d H:i:s format, UTC timezone
	 * @param string $string_date
	 * @param string $fallback 
	 * @access private
	 * @return mixed
	 */
	private function parseDateTime($string_date, $fallback = NULL)
	{
		$parsed = date_parse($string_date);
		$has_timezone = ( isset($parsed['zone']) ) ? TRUE : FALSE;

		try
		{

			# if: datetime string has timezone
			if ( $has_timezone )
			{
				$date = new DateTime($string_date);
				$timezone = $date->getTimezone()->getName();

				# if: date is not in UTC; convert to UTC
				if ( !in_array($timezone, array('+00:00', 'UTC')) )
				{
					$timezone = new DateTimeZone('UTC');
					$date->setTimezone($timezone);
				} # end if

			}
			# else: 
			else
			{
				$timezone = new DateTimeZone('UTC');
				$date = new DateTime($string_date, $timezone);
			}

		}
		# catch: silent error; use the current UTC datetime
		catch ( Exception $e )
		{
			$timezone = new DateTimeZone('UTC');
			$date = new DateTime(NULL, $timezone);
		}

		return array(
			'has_timezone'	=> $has_timezone,
			'date'			=> $date->format('Y-m-d H:i:s')
		);
	} # end method parseDateTime()


	/**
	 * This method determines if a hostname is in the whitelist or blacklist
	 * @param string $domain 
	 * @param string $type 'blacklist' or 'whitelist'
	 * @access private
	 * @return bool
	 */
	private function is_hostname_listed($domain, $type = 'whitelist')
	{
		$hostname = parse_url($domain, PHP_URL_HOST);

		# if: checking whitelist
		if ( $type == 'whitelist' )
		{
			$the_list = $this->whitelisted_domains;
			$the_table = $this->table_whitelist;
		}
		# else: checking blacklist
		else
		{
			$the_list = $this->blacklisted_domains;
			$the_table = $this->table_blacklist;
		} # end if

		# if: domain has already been looked up and is in the list
		if ( in_array($hostname, $the_list) )
		{
			return TRUE;
		}
		# else: look up from db
		else
		{
			$sql_hostname = sql_real_escape_string($hostname);

			$sql = sprintf('SELECT * FROM `%s` WHERE `hostname` = "%s" AND `deleted` IS NULL',
				$the_table,
				$sql_hostname
			);

			$result = sql_query($sql);

			# if: hostname found in whitelist
			if ( sql_num_rows($result) > 0 )
			{
				$row = sql_fetch_assoc($result);
				$this->whitelisted_domains[] = $row['hostname'];

				return TRUE;
			} # end if

		} # end if

		return FALSE;
	} # end method is_hostname_listed()

}
