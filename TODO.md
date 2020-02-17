# Todo list

## General.
- Think about running `sanitize_` functions on entity field values.
- ✅ Store "post meta" somewhere.
- Where Drupal store useful meta for article-like entities?
- Add support for WP "sticky" posts (use `_drupal_sticky` meta, either 1 or 0).

## Images.
- Maybe BP Media Extractor may help with regex?
- Is it a good idea to do image migration seperate after content migration?
- Download images from URL if they haven't been added to the media library.
- Import featured images and assign to posts.

## Users.
- Map drupal user roles to WordPress'.
- Figure out how to handle users with multiple roles.
- Find out any user meta that might be relevant.
- Handle user pictures.
- Find out what user status is being used for on drupal.

## Future tasks.
- Provide a way to map Drupal entities to WordPress CPTs.
	- Maybe with a filter, so project-specific customisations can exist in another plugin.

## The importer itself (HMCI) or Paul's fork of it.
	- Take optimisations from https://github.com/humanmade/WordPress-Importer/blob/master/class-wxr-importer.php and http://plugins.svn.wordpress.org/wordpress-importer/tags/0.6.4/wordpress-importer.php
	- Specifically, look at `wp_suspend_cache_invalidation()`, `wp_defer_term_counting()`, `wp_defer_term_counting()`.
