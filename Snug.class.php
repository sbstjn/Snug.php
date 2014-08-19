<?php

// Configuration
require_once dirname(__FILE__) . '/inc.config.php';

// Helpers
require_once dirname(__FILE__) . '/inc.functions.php';

// Frontmatter Parser
require_once dirname(__FILE__) . '/vendor/frontmatter/Frontmatter.php';

// HAML Parser
require_once dirname(__FILE__) . '/vendor/haml/HamlPHP.php';
require_once dirname(__FILE__) . '/vendor/haml/Storage/FileStorage.php';

// LESS Parser
require_once dirname(__FILE__) . '/vendor/less/lessc.inc.php';

// Markdown Parser
require_once dirname(__FILE__) . '/vendor/parsedown/Parsedown.php';

/**
 * PHP Snug Framework
 * Generate static HTML files based on HAML and Markdown content files
 * 
 * @author Sebastian Müller
 * @license http://opensource.org/licenses/MIT
 * @link https://github.com/sbstjn/Snug.php
 */
class Snug {

	private $formatAssets	= array('css');
	private $formatPages	= array('html');
	private $layout				= '_layout.haml';

	# Not used in v0.0.1-lory
	public function __construct() { }
		
	/**
	 * Build blog post page
	 *
	 * @param array $post Post data
	 * @return string
	 */
	private function buildPost($post) {
		return $this->getInLayout(SNUG_VIEWS . '_post.haml', $post);
	}
	
	/**
	 * Build URL for post ID and language
	 *
	 * @param $id integer Post ID
	 * @param $lang string Post language
	 * @return string
	 */
	private function buildPostURL($id, $lang) {
		$meta = $this->getMeta($id);
		
		# Build post URL schema like /en/31415-lorem-ipsum.html
		return '/' . $lang . '/' . str_replace('-' . strtoupper($lang) . '-', '-', substr($meta->$lang, 0, -2)) . 'html';
	}

	/**
	 * Check if post is available in language
	 *
	 * @param $lang string Post language
	 * @param $name string Post headline
	 * @return string
	 */
	private function checkPostAvailable($lang, $name) {
		list($id, ) = explode('-', $name);
		
		return file_exists(SNUG_POSTS . $id . '.json') ? $this->getMeta($id)->$lang : null;
	}
	
	/**
	 * Check if post needs redirection to correct URL
	 *
	 * @param $file string Public URI
	 * @return string
	 */
	private function doesPostNeedsRedirect($file) {
		list(, $lang, $name) = explode('/', $file);
		list($id, ) = explode('-', $name);
		
		if (!$post = $this->checkPostAvailable($lang, $name)) {
			# Post not found
			throw new Exception('Not Found', 404);
		} else {
			# Check if requested URL differs from needed one
			return $file === ($needed = $this->buildPostURL($id, $lang)) ? false : $needed;
		}
	}
	
	/**
	 * Display HTTP error
	 *
	 * @param $msg string Error message
	 * @param $code integer HTTP error code
	 */
	public function error($msg, $code) {
		header('HTTP/1.0 ' . $code . ' ' . $msg);
		header('X-Powered-By: ' . SNUG_NAME);
		
		die($msg);
	}
	
	/**
	 * Parse content HAML file with data and wrap layout around
	 *
	 * @param string $layout HAML layout template
	 * @param string $content HAML content template
	 * @param array $data Data passed to content and layout
	 */
	private function getInLayout($content, $data = array()) {
		$matr = new FrontMatter($content);
		
		# Set page title if set in template
		if ($title = $matr->fetch('title')) {
			$data = array_add_key($data, 'title', $title);
		}
		
		# Change layout file if overwritten in template
		if ($matr->fetch('layout') && $this->layoutExists($matr->fetch('layout'))) {
			$layout = $this->getLayoutFile($matr->fetch('layout'));
		} else {
			$layout = SNUG_VIEWS . $this->layout;
		}
		
		# Parse body and wrap in layout
		$body = $this->parseHAMLString($matr->fetch('content'), $data);
		$html = $this->parseHAML($layout, array_add_key($data, 'body', $body));
		
		# Return almighty HTML code
		return $html;
	}
	
	/**
	 * Get absolute layout file path 
	 *
	 * @param $file string Layout name
	 * @return string
	 */
	private function getLayoutFile($file) {
		return SNUG_VIEWS . '_layout_' . $file . '.haml';
	}
	
	/**
	 * Get basic meta information for post
	 *
	 * @param $num integer Post ID
	 * @return array
	 */
	private function getMeta($num) {
		return json_decode(file_get_contents(SNUG_POSTS . $num . '.json') . "");
	}
	
	/**
	 * Get blog post
	 *
	 * @param $file Markdown file name
	 * @return array
	 */
	private function getPost($file) {
		$md =	new Parsedown();
	
		$data = file_get_contents(SNUG_POSTS . '/' . $file);
		
		list($header, $data) = explode(')-->', substr($data, 5));
		$header = trim($header);

		$data = trim($data);
		$post = json_decode($header, TRUE);
		$post['title'] = trim(substr($data, 1, strpos($data, "\n")));
		$post['content'] = $md->text(trim(substr(trim($data), strpos($data, "\n"))));
		
		return $post;
	}
	
	/**
	 * Build template file paht for page URI
	 *
	 * @param $url string Public page URI
	 * @return string
	 */
	private function getTemplateForPage($url) {
		return SNUG_VIEWS . substr($url, 0, -4) . 'haml';
	}

		
	/**
	 * Handle request for asset file
	 *
	 * @param $file string Public file URI
	 * @return string
	 */
	public function handleAsset($file) {
		# Just support LESS to CSS compilation at the moment
		if (!$this->isAsset($file)) {
			throw new Exception('Bad Request', 400);
			return;
		}
		
		$url = $file;
		$lss = substr($url, 0, -3) . 'less';
		
		# Check if LESS file can be found
		if (!file_exists(SNUG_ASSETS . $lss)) {
			throw new Exception('Not Found', 404);
		} else {
			# Parse LESS
			$comp = new lessc;
			$data = $comp->compile(file_get_contents(SNUG_ASSETS . $lss));
			
			# Write cache
			$this->writeFile(SNUG_HTDOCS . $url, $data);
			
			# Return CSS
			return $data;
		}
	}
	
	/**
	 * Handle request for page file
	 *
	 * @param $file string Public page URI
	 * @return string
	 */
	public function handlePage($file) {
		# Just support HAML to HTML compilation at the moment
		if (!$this->isPage($file)) {
			throw new Exception('Bad Request', 400);
		} else {
			# Check if page is a static HAML template or a Markdown blog post
			return $this->isStaticPage($file) ? $this->handleStaticPage($file) : $this->handlePost($file);			
		}
	}

	/**
	 * Handle request to parse blog post
	 *
	 * @param $file string Public post URI
	 * @return string
	 */
	public function handlePost($file) {
		# Check if URL is correct
		if ($url = $this->doesPostNeedsRedirect($file)) {
			# Redirect to correct URL
			$this->redirect_to($url);
		} else {
			list(, $lang, $name) = explode('/', $file);
			
			if (!$post = $this->checkPostAvailable($lang, $name)) {
				throw new Exception('Not Found', 404);
			} else {
				# Build post HTML
				$data = $this->getPost($post);
				$html = $this->buildPost($data);
				
				# Write cache
				$this->writeFile(SNUG_HTDOCS . $file, $html);
				
				# Return compiled HTML
				return $html;
			}

		}
	}
		
	/**
	 * Handle request for static HAML to HTML file
	 *
	 * @param $file string Public page URI
	 * @return string
	 */
	public function handleStaticPage($file) {
		# Build page HTML
		$html = $this->getInLayout($this->getTemplateForPage($file));

		# Write cache		
		$this->writeFile(SNUG_HTDOCS . $file, $html);
		
		# Return compiled HTML
		return $html;
	}
		
	/**
	 * Check if requested file is supported asset 
	 *
	 * @param $format string Asset file
	 * @return boolean
	 */
	private function isAsset($file) {
		$tmp = explode('.', $file);
		
		return in_array(strtolower(end($tmp)), $this->formatAssets);
	}
	
	/**
	 * Check if requested file is supported page 
	 *
	 * @param $format string Asset file
	 * @return boolean
	 */
	private function isPage($file) {
		$tmp = explode('.', $file);
		
		return in_array(strtolower(end($tmp)), $this->formatPages);
	}
	
	/**
	 * Check if URI is static page
	 *
	 * @param $url string Public page URI
	 * @return
	 */
	private function isStaticPage($url) {
		return substr($url, 0, 1) !== '_' && file_exists($this->getTemplateForPage($url));
	}
	
	/**
	 * Check if layout file exists
	 *
	 * @param $file string Layout name
	 * @return boolean
	 */
	private function layoutExists($file) {
		return file_exists($this->getLayoutFile($file));
	}
	
	/**
	 * Parse HAML template with data
	 *
	 * @param $tpl string HAML template file path
	 * @param $data array Passed data
	 * @return string
	 */
	private function parseHAML($tpl, $data = array()) {
		$HAML = new HamlPHP(new FileStorage(dirname(__FILE__) . '/tmp/'));
		
		$content = $HAML->parseFile($tpl);
		return $HAML->evaluate($content, $data);
	}
	
	/**
	 * Parse HAML string with data
	 *
	 * @param $tpl string HAML template string
	 * @param $data array Passed data
	 * @return string
	 */
	private function parseHAMLString($string, $data = array()) {
		$HAML = new HamlPHP(new FileStorage(dirname(__FILE__) . '/tmp/'));
		
		$content = $HAML->parseString($string);
		return $HAML->evaluate($content, $data);
	}
	
		/**
	 * Display HTTP error
	 *
	 * @param $msg string Error message
	 * @param $code integer HTTP error code
	 */
	public function redirect_to($url, $permanent = true) {
		header('HTTP/1.0 301 Moved Permanently');
		header('Location: ' . $url);
		header('X-Powered-By: ' . SNUG_NAME);
		
		die();
	}

	/**
	 * Write data to file. Used for caching HTML and CSS
	 *
	 * @param $file string File path
	 * @param $content string File content
	 */
	private function writeFile($file, $content) {
		$handle = fopen($file, 'w+');
		fwrite($handle, $content);
		fclose($handle);
	}
}
