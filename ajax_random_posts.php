<?php
/*
Plugin Name: AJAX Random Posts
Plugin URI: http://www.phoenixheart.net/wp-plugins/ajax-random-posts/
Description: Displays a list of random posts using AJAX. Works with cache plugins, and highly customizable.
Version: 0.3.3
Author: Phan An
Author URI: http://www.phoenixheart.net
*/

global $wpdb, $ajaxrp_plugin_name, $ajaxrp_plugin_dir, $ajaxrp_options;

$ajaxrp_plugin_name = 'AJAX Random Posts';
$ajaxrp_plugin_dir = plugin_dir_url(__FILE__);
define('AJAXRP_CUSTOM_TAG', '<!--ajaxrp-->');

$ajaxrp_options = array(
	'ajaxrp_auto_show'			=> 1,
	'ajaxrp_before_html' 		=> '<h3>Random Posts</h3><div>',
	'ajaxrp_after_html'			=> '</div>',
	'ajaxrp_number_shown'		=> 10,
	'ajaxrp_loading_message'	=> '<p>Loading...</p>',
	'ajaxrp_post_format'		=> '{post} written by {author}',
    'ajaxrp_include_sticky'     => 0,
);

load_plugin_textdomain($ajaxrp_text_domain);

function ajaxrp_install()
{
	global $ajaxrp_options;
	foreach ($ajaxrp_options as $key=>$value)
	{
		add_option($key, $value);
	}
}

register_activation_hook(__FILE__, 'ajaxrp_install');

add_action('wp_enqueue_scripts', 'ajaxrp_enqueue_assets', PHP_MAX_INT);

function ajaxrp_enqueue_assets() {
    global $ajaxrp_plugin_dir;
    $css_file = get_template_directory_uri() . 'ajaxrp-style.css';
    if (!file_exists($css_file)) {
        $css_file = "{$ajaxrp_plugin_dir}css/ajaxrp-style.css";
    }
    
    wp_enqueue_script('ajaxrp-onload',  $ajaxrp_plugin_dir . 'js/onload.js', 'jquery', '1.0', TRUE);
    wp_enqueue_style('ajaxrp-css', $css_file, array(), '1.0');
}

function ajaxrp_prepare_post_data($post_content)
{	
	global $post;
	
	$data = array(
		'post_id'	=> $post->ID,
	);
	
	$placeholder = ajaxrp_get_placeholder($data);
	
	if (get_option('ajaxrp_auto_show'))
	{
		// remove the custom tag (if any) and add the place holder to the end of the post
		$post_content = str_replace(AJAXRP_CUSTOM_TAG, '', $post_content) . $placeholder;
		return $post_content; 
	}
	
	// replace the custom tag with the placeholder
	return str_replace(AJAXRP_CUSTOM_TAG, $placeholder, $post_content);
}

add_filter('the_content', 'ajaxrp_prepare_post_data', 5);

function ajaxrp_get_placeholder($data)
{
	// if this is not a single post, don't do anything
	if (!is_single())
	{
		return '';
	}
	
	$data = base64_encode(serialize($data));
	
	return sprintf('<!-- begin ajax random post data -->
	%s
	<div id="ajaxrp">
		%s
		<input type="hidden" name="ajaxrp_data" id="ajaxrpData" value="%s" />
	</div>
	%s
	<!-- end ajax random post data -->', 
	stripslashes(get_option('ajaxrp_before_html')), 
	stripslashes(get_option('ajaxrp_loading_message')), 
	$data,
	stripslashes(get_option('ajaxrp_after_html')));
}

function ajaxrp_request_handler()
{
	switch($_POST['ajaxrp_action'])
	{
		case 'get_posts':
			ajaxrp_get_posts();
			break;
		case 'options':
			ajaxrp_save_options();
			break;
		default:
			return false;
	}
	exit();
}

add_action('init', 'ajaxrp_request_handler', 5);

function ajaxrp_save_options()
{
	global $ajaxrp_options;
	
	foreach ($_POST as $key=>$value)
	{
		if ($key == 'ajaxrp_action' || !isset($ajaxrp_options[$key])) continue;
		if ($key == 'ajaxrp_number_shown')
		{
			$value = intval($value);
			if ($value < 1) $value = 10; // default number of posts is 10
		}
		update_option($key, $value);
	}
	header('Location: ' . get_bloginfo('wpurl') . '/wp-admin/options-general.php?page=ajax_random_posts.php&updated=true');
	die();
}

function ajaxrp_get_posts()
{
	$format = stripslashes(get_option('ajaxrp_post_format'));
	$data = unserialize(base64_decode($_POST['data']));
	$ret = '<ul>';
	
    $params = array(
        // only get the sticky posts if the user wants to
        'caller_get_posts'  => get_option('ajaxrp_include_sticky') == 1 ? 0 : 1,
        'showposts'        => get_option('ajaxrp_number_shown'),
        'orderby'        => 'rand',
        'post__not_in'    =>    array(intval($data['post_id'])),
    );
	query_posts($params);
	
	while (have_posts())
	{
		the_post(); 

		$url = get_permalink();
		$title = get_the_title();
		$author_name = get_the_author();
		$author_url = get_the_author_url();
		$category_names = array();
		$category_links = array();

		foreach((get_the_category()) as $category)
		{
			$category_names[] = $category->cat_name;
			$category_links[] = sprintf('<a href="%s">%s</a>', get_category_link($category->cat_ID), $category->cat_name);
		}

		$tags = array(
			'{url}' 			=> $url,
			'{title}'			=> $title,
			'{post}'			=> sprintf('<a href="%s">%s</a>', $url, $title),
			'{categorynames}'	=> implode(', ', $category_names),
			'{categories}'		=> implode(', ', $category_links),
			'{authorname}'	 	=> $author_name,
			'{authorurl}'		=> $author_url,
			'{author}'			=> sprintf('<a href="%s">%s</a>', $author_url, $author_name),
			'{excerpt}'			=> apply_filters('the_excerpt', get_the_excerpt()),
			'{content}'			=> apply_filters('the_content', get_the_content()),
			'{date}'			=> the_date('', '', '', false),
			'{time}'			=> get_the_time(),
			'{tags}'			=> get_the_tag_list('', ', ', ''),
		);
		
		$ret .= str_replace(array_keys($tags), array_values($tags), "<li>$format</li>" . PHP_EOL);
	}
	
	$ret .= '</ul>';
	
	die($ret);
}

function ajaxrp_options_form()
{
	global $ajaxrp_plugin_dir;
	printf('
		<link rel="stylesheet" type="text/css" href="%scss/admin.css" />
		<script type="text/javascript" src="%sjs/admin-onload.js"></script>
		<div class="wrap">
        <div id="icon-options-general" class="icon32"><br /></div>
        <h2>AJAX Random Posts Options</h2>
		<form action="index.php" method="post" autocomplete="off" id="ajrpForm">
			<p>
				<label>How many posts should the lists contain?</label><br />
				<input type="text" value="%s" name="ajaxrp_number_shown" class="required" style="width: 40px" />
			</p>
            <p>
                <label>Include the sticky posts in the random list?</label><br />
                <small>By default, WordPress returns the sticky posts when querying the post. It\'s likely you do not want them to appear
                in the random list, but just in case you have a good reason to...</small>
            </p>
            <p style="margin-top: -5px">
                <select name="ajaxrp_include_sticky">
                    <option value="0">No</option>
                    <option value="1" %s>Yes</option>
                </select>
            </p>
			<p><label>Automatically display the random posts in a single post page?</label></p>
			<p class="toggler">Get help with this</p>
			<div class="toggled" style="font-size: 11px; display: none; background: #ffffef; padding: 5px 10px">
				If you turn this to "No", there are still 2 ways to display a list of random posts:
				<ol>
					<li>Manually put a custom tag <strong>&lt;!--ajaxrp--&gt;</strong> at the end of the post you want the list to follow up, or</li>
					<li>Place this php code: <br />
						<strong>&lt;?php if (function_exists(\'ajaxrp\')) ajaxrp(); ?&gt;</strong><br />
						at a proper place in your template\'s <strong>single.php</strong> page</li>
				</ol>
			</div>
			<p style="margin-top: -5px">
				<select name="ajaxrp_auto_show">
					<option value="1">Yes</option>
					<option value="0" %s>No</option>
				</select>
			</p>
			<p>
				<label>The HTML code before the list<br />
				<textarea name="ajaxrp_before_html" style="width: 400px">%s</textarea>
			</p>
			<p>
				<label>The HTML code after the list<br />
				<textarea name="ajaxrp_after_html" style="width: 400px">%s</textarea>
			</p>
			<p>
				<label>The "loading" message<br />
				<textarea name="ajaxrp_loading_message" style="width: 400px">%s</textarea>
			</p>
			<p><label>Format of each item in the list </label></p>
			<p class="toggler">Get help with this</p>
			<div class="toggled" style="font-size: 11px; display: none; background: #ffffef; padding: 5px 10px">
				Available tags:<br />
				<ul>
					<li><strong>{url}</strong> - The permalink to the post</li>
					<li><strong>{title}</strong> - The title of the post</li>
					<li><strong>{post}</strong> - Link to the post. A combination of {url} and {title}</li>
					<li><strong>{categorynames}</strong> - Names of the categories, seperated by commas</li>
					<li><strong>{categories}</strong> - Links to the categories, seperated by commas</li>
					<li><strong>{authorname}</strong> - Name of the post\'s author</li>
					<li><strong>{authorurl}</strong> - The URL of the author</li>
					<li><strong>{author}</strong> - Link to the author. A combination of {authorurl} and {authorname}</li>
					<li><strong>{excerpt}</strong> - The post\'s excerpt (short summary)</li>
					<li><strong>{content}</strong> - The post\'s full-length content</li>
					<li><strong>{date}</strong> - The post\'s date</li>
					<li><strong>{time}</strong> - The post\'s time</li>
					<li><strong>{tags}</strong> - A comma-seperated list of this post\'s (clickable) tags</li>
				</ul>
			</div>
			<p style="margin-top: -5px">
				<textarea name="ajaxrp_post_format" class="required" style="width: 400px">%s</textarea>
				<input type="hidden" value="options" name="ajaxrp_action" />
			</p>
			<p class="submit">
                <input class="button-primary" type="submit" value="Save Options" name="submit"/>
            </p>
		</form>
        <div id="donate">
            <p>AJAX Random Posts is totally free, but if you think it worths some love, please consider a donation.</p>
<form action="https://www.paypal.com/cgi-bin/webscr" method="post">
<input type="hidden" name="cmd" value="_s-xclick">
<input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
">
<input type="image" src="https://www.paypal.com/en_US/i/btn/btn_donate_LG.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
<img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
</form>
            </div>
	</div>',
	$ajaxrp_plugin_dir,
	$ajaxrp_plugin_dir,
	get_option('ajaxrp_number_shown'),
    get_option('ajaxrp_include_sticky') == 1 ? 'selected="selected"' : '',
	get_option('ajaxrp_auto_show') == 0 ? 'selected="selected"' : '',
	stripslashes(get_option('ajaxrp_before_html')),
	stripslashes(get_option('ajaxrp_after_html')),
	stripslashes(get_option('ajaxrp_loading_message')),
	stripslashes(get_option('ajaxrp_post_format')));
}

/**
 * @desc	Adds the Options menu item
 */
function ajaxrp_menu_items()
{
	add_options_page('AJAX Random Posts', 'AJAX Random Posts', 8, basename(__FILE__), 'ajaxrp_options_form');
}

# Hook into the settings menu
add_action('admin_menu', 'ajaxrp_menu_items');

function ajaxrp()
{
	global $post;
	
	$data = array(
		'post_id'	=> $post->ID,
	);
	
	echo ajaxrp_get_placeholder($data);
}









