# Webmention Nucleus Plugin

[Nucleus CMS](http://nucleuscms.org) plugin to send and receive webmentions.

[Webmention](http://webmention.org) is a simple way to automatically notify any URL when you link to it on your site. From the receiver's perpective, it is a way to request notification when other sites link to it.

Version 0.5 is a stable beta that covers the basic webmention sending and receiving. It does not handle displaying webmentions on the public site yet. That and other features will be coming in subsequent versions.

## Features
* Webmention endpoint discovery
* Automatically send webmentions
* Automatically receive webmentions
* Process webmentions to extract microformats from webmentions
* Whitelist or blacklist hostnames

## Customizations
1. By default, the webmention endpoint is the rather long and messy URL <code>/action.php?action=plugin&name=Webmention&type=endpoint</code>. You can use mod_rewrite (RewriteRule) to simplify this to, say, <code>/webmention</code>.

If you do so, under the plugin options enter the full path to the webmention endpoint.

2. If you use a non-standard permalink structure (as I do), then you may edit the buildPermalink() method to return your permalink structure. This method is used when sending webmentions from your site.

The Nucleus default <code>?itemid=X</code> URLs should always work, though. It's just a matter of whether the recipient of the webmention displays the source URL anywhere.

## Processing Webmentions
When a webmention is received, some basic checks are performed to verify the source and target URLs are supplied. It also verifies that the target URL is a valid Nucleus post. If the webmention fails these checks, an appropriate response is sent to the sender.

Webmentions are not currently automatically processed. You will need to visit the plugin's admin area periodically to see if there are pending webmentions and process them.

In the future, this plugin will have a cron option that can run periodically to handle the processing.

When processing webmentions, the returned content is marked as "do not display" by default. You can change this by whitelisting hostnames (see below).

## Whitelist
If you trust webmentions from a specific hostname, you can add it to the whitelist in the admin area. When pending webmentions are processed, those hostnames will automatically be marked as "do display."

## Blacklist
If you receive spam webmentions from a specific hostname, you can add it to the blacklist in the admin area. When pending webmentions are processed, these will automatically be marked as "do not display" and flagged as blacklisted. It will not appear in the admin area. In the future, functionality to review these may be added.
