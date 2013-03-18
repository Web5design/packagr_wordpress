<?php
/*
Plugin Name: Packagr Data Access API
Plugin URI: http://packa.gr/wordpress
Description: A plugin that allows Packa.gr remote access to full back catalog of content
Version:1.0
Author: XOXCO, Inc
Author URI: http://xoxco.com
License: MIT
*/
?>
<?php

// register install hook
register_activation_hook(__FILE__,'packagr_activate');


// register admin menu 
add_action('admin_menu','packagr_setup_admin');


// register actual endpoint
add_action('init','packagr_setup_endpoints');

function packagr_setup_endpoints() {
	add_rewrite_endpoint('packagr',EP_ROOT);
}



function packagr_activate() {
	
	packagr_generate_secret();
	packagr_setup_endpoints();
	flush_rewrite_rules();

}
// register handler for endpoint

function packagr_template_redirect() {
	
		global $_REQUEST;
		global $wp_query;
		

			if ( isset( $wp_query->query_vars['packagr'] ) && is_home() ) {
					$secret = get_option('packagr_shared_secret');

		
				if (!$secret || ($secret && $_REQUEST['secret']!=$secret)) {
					header("Content-type: application/json");			
					echo json_encode(array('error'=>'Access Denied: Secret key does not match'));
				} else {


					// turn off read more
					global $more;
					$more = 1;
					
					$offset = $_REQUEST['offset'] ? $_REQUEST['offset'] : 0;
					$count = $_REQUEST['count'] ? $_REQUEST['count'] : 100;
					$query = array(
						'posts_per_page'=>$count,
						'offset'=>$offset,
					);				
					
					if ($_REQUEST['q']) {
						$query['s'] = $_REQUEST['q'];						
					}
				
					$posts = new WP_Query( $query );
					$results = array();
		
					while ( $posts->have_posts() ) : $posts->the_post();
			
						$postid = get_the_ID();
						
						$image = wp_get_attachment_image_src(get_post_thumbnail_id($postid),'full');
						if ($image) {
							$image = $image[0];
						} else {
							$image = null;
						}
						
						$categories_raw = wp_get_post_categories($postid);
						$categories = array();
						if ($categories_raw) {
							foreach ($categories_raw as $c) {
								$cat = get_category( $c );
								array_push($categories,$cat->name);
							}
						}
									
						$tags_raw = get_the_tags();
						$tags = array();
						if ($tags_raw) {
							foreach ($tags_raw as $tag) {
								array_push($tags,$tag->name);
							}
						}
						
						$content = get_the_content();
						$content = apply_filters('the_content', $content);
						$content = str_replace(']]>', ']]&gt;', $content);			
						
						
						$data = array(				
							'title'		=> get_the_title(),
							'pubDate'	=> the_date('c','','',false),
							'link'=>	get_permalink(),
							'content'	=> $content,
							'author'		=> get_the_author(),
							'categories' => implode(",",$categories),
							'tags'		=> implode(",",$tags),
							'image'		=> $image
						);				
						
						array_push($results,$data);
					
						wp_reset_postdata();
					endwhile;
						
					header("Content-type: application/json");
					echo json_encode($results);
	
				} // if we have appropriate access
				exit;
		} // if this is the right endpoint 
}

// add functionality to map /packagr to the api functions
add_action( 'template_redirect', 'packagr_template_redirect' );

function packagr_generate_secret($force=false) {
	
	$secret = null;
	
	$secret = get_option('packagr_shared_secret');
	if (!$secret || $force) {
		$secret = md5(rand(0,1000));
		update_option('packagr_shared_secret',$secret);
	}
	return $secret;
		
}

function packagr_setup_admin() {

	// register admin menu 
	add_submenu_page('tools.php','Packagr Data Access API','Packagr API','activate_plugins','packagr','packagr_admin_menu');
	add_submenu_page(null,'Packagr Data Access API: Reset Secret','Packagr API','activate_plugins','packagr_reset','packagr_reset');
	
}


function packagr_reset() {
	
	$secret = packagr_generate_secret(true);
	
	echo '<h1>Packagr Shared Secret Reset!</h1>';
	echo '<p>You must now update your settings inside Packagr, following the instructions <A href="?page=packagr">here</a></p>';
	
}


function packagr_admin_menu() {
	
	
	$secret = get_option('packagr_shared_secret');
	if (!$secret) {
		$secret = packagr_generate_secret(true);
	}

	echo "<h1>Packagr Data Access API</h1>";
	echo "<p>Your Packagr Shared Secret is: " . $secret  . "</p>";
	echo '<p>To begin importing your content into Packagr:';
	echo '<ol>';
	echo '<li>Go to <a href="http://preview.packa.gr/#/app/settings/sources" target="_blank">Packagr Content Sources menu</a></li>';
	echo '<li>Click the Wordpress icon at the top of the page.</li>';
	echo '<li>Copy the URL below and paste it into the Wordpress API field:<Br />';
	echo '<input value="' . site_url() . "/packagr?secret=" . $secret . '" size="100" />';
	echo '</li>';
	echo '<li>Click save, and the content will immediately begin importing! New content will be added automatically.</li>';
	echo '</ol>';
	
	echo '<hr />';
	echo "<h2>Regenerate Shared Secret</h2>";
	echo "<p>If you need to reset access to the Packagr Data Access API, click below to generate a new shared secret. <strong>This will require you to update your content source inside Packagr.</strong></p>";
	echo '<p><a href="?page=packagr_reset">Regenerate Secret</a></p>';	
	
	
}


?>