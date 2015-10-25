<?php
/* custom theme functions */

// import global style into wysiwyg editors
function ca_theme_add_editor_styles() {
    add_editor_style( 'editor-style.css' );
}

add_action( 'after_setup_theme', 'ca_theme_add_editor_styles' );


// add a footer menu
register_nav_menus( array(
	'footer' => __( 'Footer', 'founder' )
) );


// add connections between stories and their pages via posts to posts plugin
function my_connection_types() {
    p2p_register_connection_type( array(
        'name' => 'story-pages_to_stories',
        'from' => 'story-page',
        'to' => 'story',

        'fields' => array(
	        'count' => array(
	            'title' => 'For Page',
	            'type' => 'numeric',
	        ),
	    )

    ) );
}

add_action( 'p2p_init', 'my_connection_types' );


// add custom query variables
function add_query_vars($ca_vars) {
	$ca_vars[] = "storyid, pagecount";
	return $ca_vars;
}

add_filter('query_vars', 'add_query_vars');


// counts words in submitted pages
function word_count() {
    $content = $_POST["user_post_desc"];
    $word_count = str_word_count( strip_tags( $content ) );
    if ($word_count < 200 || $word_count > 800) {
    
    	echo "<script>alert('Your page is " . $word_count . " words. It must be between 200 and 800 words long.' )</script>";
	}
}

add_action('frontier_post_post_save', 'word_count');


// add meta to story on save and publish immediately if word count is correct
function update_story_meta() {
	if (isset($_POST['posttype']) and $_POST['posttype'] == 'story') {
		$pagelimit = $_POST['total_page_limit'];
		$storyrating = $_POST['story_rating'];
		$storyrules = $_POST['story_rules'];
		$thisid = $_POST['postid'];
		
		$thisstory = array(
			'ID' => $thisid,
			'post_status' => 'publish'
			); 

		update_post_meta($thisid, 'total_page_limit', $pagelimit);
		update_post_meta($thisid, 'story_rating', $storyrating);
		update_post_meta($thisid, 'story_rules', $storyrules);

		$content = $_POST["user_post_desc"];
	    $word_count = str_word_count( strip_tags( $content ) );
	    if ($word_count > 199 && $word_count < 801) {
			wp_update_post ($thisstory);
		}
	}
}

add_action('frontier_post_post_save', 'update_story_meta');


// add a title to story pages on save
function update_storypage_title() {
	if ($_POST['posttype'] == 'story-page') {
		$thisid = $_POST['postid'];
		$parent = get_post_meta( $thisid, 'parent_story_id', true );
		$parent_title = get_the_title($parent);
		$for_page = get_post_meta( $thisid, 'for_page', true );

		$this_page = array(
		  'ID'           => $thisid,
		  'post_title'   => $parent_title . '-' . $for_page,
		);

		// Update the post in the database
		wp_update_post( $this_page );
	}
}

add_action('frontier_post_post_save', 'update_storypage_title');


// counts published story pages to find how many pages a story has
// add 2 to get current submission page (for 1st page and next page)
function get_page_count($storyid) {
	global $wpdb;
	//$wpdb->show_errors();

	$page_count = $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(p2p_id)
	FROM wp_p2p 
	INNER JOIN wp_posts
	ON wp_p2p.p2p_from = wp_posts.ID
	WHERE wp_posts.post_status = 'publish'
	AND (wp_p2p.p2p_to = %d)",
	$storyid) 
	);

	return $page_count;
}


// gets top rated story-page submission
function get_top_submission($storyid, $forpage) {
	global $wpdb;
	//$wpdb->show_errors();

	$top_rated = $wpdb->get_var($wpdb->prepare(
	"SELECT T1.post_id
	FROM wp_postmeta T1
	JOIN wp_postmeta T2 ON T1.post_id = T2.post_id and T2.meta_key = 'parent_story_id' and T2.meta_value = %d
	JOIN wp_postmeta T3 ON T2.post_id = T3.post_id and T3.meta_key = 'for_page' and T3.meta_value = %d
	JOIN wp_postmeta T4 ON T3.post_id = T4.post_id and T4.meta_key = 'ratings_users'
	WHERE T1.meta_key = 'ratings_average' or T1.meta_key = 'ratings_users'
	ORDER BY T1.meta_value desc, T4.meta_value desc
	limit 1",
	$storyid, $forpage) 
	);

	if (!empty($top_rated)) {
		return $top_rated;
	} else {
		return 'none';
	}
}


function publish_after_countdown($storyid, $forpage) {
    // go thru connected posts and find highest rated draft,
    // then update it's status to publish
    $winnerid = get_top_submission($storyid, $forpage);

    if (! empty($winnerid) && $winnerid != 'none') {
    	wp_publish_post( $winnerid );
	 } else {
	 	// if there is no winner, restart the timer
		wp_schedule_single_event( time() + 3600, 'send_to_vote', array($storyid, $forpage) );
	}
}

add_action( 'send_to_vote', 'publish_after_countdown', 10, 2 );



// start the countdown when 3 submissions have been made
function schedule_publish_event() {
	if (isset($_POST['postid']) && isset($_POST['posttype']))
	$thisid = $_POST['postid'];
	$post_type = $_POST['posttype'];
	$parent = get_post_meta( $thisid, 'parent_story_id', true );
	$forpage = get_post_meta( $thisid, 'for_page', true );

	global $wpdb;
	//$wpdb->show_errors();

	$draftcount = $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(p2p_id)
	FROM wp_p2p T1
	JOIN wp_posts T2 ON T1.p2p_from = T2.ID
	JOIN wp_postmeta T3 ON T2.ID = T3.post_id and T3.meta_key = 'for_page' and T3.meta_value = %d
	WHERE T2.post_status = 'draft'
	AND (T1.p2p_to = %d)",
	$forpage, $parent) 
	);

	if ($post_type == 'story-page' && $draftcount > 2) {

	wp_schedule_single_event( time() + 3600, 'send_to_vote', array($parent, $forpage) );
	} // time() + 129600 = 36 hours from now.

}

add_action('frontier_post_post_save', 'schedule_publish_event');


// echo the first image from a post
function echo_first_image( $postID ) {
	$args = array(
		'numberposts' => 1,
		'order' => 'ASC',
		'post_mime_type' => 'image',
		'post_parent' => $postID,
		'post_status' => null,
		'post_type' => 'attachment',
	);

	$attachments = get_children( $args );

	if ( $attachments ) {
		foreach ( $attachments as $attachment ) {
			$image_attributes = wp_get_attachment_image_src( $attachment->ID, 'thumbnail' )  ? wp_get_attachment_image_src( $attachment->ID, 'thumbnail' ) : wp_get_attachment_image_src( $attachment->ID, 'full' );

			echo '<img src="' . wp_get_attachment_thumb_url( $attachment->ID ) . '" class="current" width="250" />';
		}
	}
}


// count how many pages an author has published
function get_total_pages($authorid) {
	global $wpdb;
	//$wpdb->show_errors();

	$totalpages = $wpdb->get_var($wpdb->prepare(
	"SELECT COUNT(ID)
	FROM wp_posts
	where post_status = 'publish'
	and post_type in ('story', 'story-page')
	and (post_author = %d)",
	$authorid) 
	);

	return $totalpages;
}


// gets average author rating for submitted pages
function get_avg_rating($authorid) {
	global $wpdb;

	$avgrating = $wpdb->get_var($wpdb->prepare(
	"SELECT TRUNCATE (AVG(meta_value), 1) 
	FROM wp_postmeta T1
	JOIN wp_posts T2
	ON T1.post_id = T2.ID
	where T1.meta_key = 'ratings_average'
	and T1.meta_value > 0
	and T2.post_author = %d
	and T2.post_status = 'publish'",
	$authorid)
	);

	return $avgrating;
}


// counts how many authors a user is following
function get_following($userid) {
	global $wpdb;

	$following = $wpdb->get_var($wpdb->prepare(
	"SELECT count(ID)
	from wp_posts a
	join wp_postmeta b
	on a.ID = b.post_id
	where a.post_type = 'wpwfollowauthor'
	and b.meta_key = '_wpw_fp_follow_status'
	and b.meta_value = 1
	and a.post_author = %d",
	$userid) 
	);

	return $following;
}


// Show posts of 'story' post type on home page
function query_stories( $query ) {
  if ( is_home() && $query->is_main_query() )
    $query->set( 'post_type', array( 'story' ) );
  return $query;
}

add_action( 'pre_get_posts', 'query_stories' );


// load jquery dependent scripts 
function ca_load_scripts() {
	wp_enqueue_script(
		'site-functions', 
		get_stylesheet_directory_uri() . '/js/site-functions.js',
		array('jquery'), 
		true );
}

add_action( 'wp_enqueue_scripts', 'ca_load_scripts' );


// register widget areas
function ca_register_widget_areas(){

    register_sidebar( array(
		'name'          => __( 'Top Stories', 'founder' ),
		'id'            => 'sidebar-1',
		'description'   => __( 'Sidebar for top stories widget.', 'founder' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
	register_sidebar( array(
		'name'          => __( 'Most Recent Stories', 'founder' ),
		'id'            => 'sidebar-2',
		'description'   => __( 'Sidebar for recent stories widget.', 'founder' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
	register_sidebar( array(
		'name'          => __( 'Top Authors', 'founder' ),
		'id'            => 'sidebar-3',
		'description'   => __( 'Sidebar for top authors widget.', 'founder' ),
		'before_widget' => '<aside id="%1$s" class="widget %2$s">',
		'after_widget'  => '</aside>',
		'before_title'  => '<h1 class="widget-title">',
		'after_title'   => '</h1>',
	) );
}

add_action('widgets_init','ca_register_widget_areas');


// include story post type in category/tag archives
function ca_add_custom_types( $query ) {
  if( is_category() || is_tag() && empty($query->query_vars['suppress_filters']) ) {
    $query->set( 'post_type', array('post', 'nav_menu_item', 'story') );
	  return $query;
	}
}

add_filter( 'pre_get_posts', 'ca_add_custom_types' );


// Define what post types to search
function searchAll( $query ) {
	if ( $query->is_search ) {
		$query->set( 'post_type', array( 'post', 'page', 'story', 'story-page'));
	}
	return $query;
}

add_filter( 'the_search_query', 'searchAll' );


// checks whether an image file is returned or error
function checkRemoteFile($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    // don't download content
    curl_setopt($ch, CURLOPT_NOBODY, 1);
    curl_setopt($ch, CURLOPT_FAILONERROR, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if(curl_exec($ch)!==FALSE)
    {
        return true;
    }
    else
    {
        return false;
    }
}