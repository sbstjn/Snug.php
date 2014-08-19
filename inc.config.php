<?php

# HTDOC directory for your domain
define('SNUG_HTDOCS', 	dirname(__FILE__) . '/../');

# Define label for Snug installation
define('SNUG_NAME',			'snug');

# Directory Snug.class.php can be found in
define('SNUG_PATH', 		SNUG_HTDOCS . '/snug/');

# Directory Snug can look for assets
define('SNUG_ASSETS', 	SNUG_HTDOCS . '_assets/');

# Directory Snug can look for Markdown posts
define('SNUG_POSTS',		SNUG_HTDOCS . '_posts/');

# Directory Snug can look for HAML views
define('SNUG_VIEWS',  	SNUG_HTDOCS . '_views/');

# Snug version
define('SNUG_VERSION', 	'0.0.1-lory');