<div <?php post_class(); ?>>

	<?php $ca_story_id = $post->ID;

	// add 1 to include original page!
	$page_count = get_page_count($ca_story_id) + 1;

	// add 2 for original page and advance to next page
	$submission_page = $page_count + 1;

	$max_pages = get_post_meta( $ca_story_id, 'total_page_limit', true );
	$pages_remaining = $max_pages - $page_count; ?>

	<!-- ***** content-story ******* -->
	<?php hybrid_do_atomic( 'page_before' ); ?>

	
	<article>

		<div class="post-header ca-story-header">
			
			<div class="pagenumber-box">
				
				<div class="largepage">
					<?php echo $pages_remaining; ?>
				</div>
				<br /><span>pages left</span>
			</div>
			
			<div class="story-avatar">
				<?php $authorid = get_the_author_meta( 'ID' );
				echo '<a href="' . get_author_posts_url($authorid) . '" rel="author">' . get_avatar( get_the_author_meta( 'ID' ), 64 ) . '</a>'; ?>
			</div>

			<h1 class="post-title"><?php the_title(); ?></h1>
			
			<div class="story-meta">
				<?php echo 'By ' . get_the_author_meta('display_name', $post->post_author) . '<br />';
				echo the_category( ' | ' ) . ' - ';
				echo get_page_count($post->ID) + 1 . ' pages';
				?>
			</div>
			<br />
			
			<div class="story-social">
				<?php if ( function_exists( 'ADDTOANY_SHARE_SAVE_KIT' ) ) { 
    				ADDTOANY_SHARE_SAVE_KIT( array( 'use_current_page' => true ) );
				} ?>
				<?php if (function_exists('wpfp_link')) { wpfp_link(); } ?>
			</div>
		
		</div>

		<div class="post-content scrollview">
			<?php 
				/* display thumbnail if there is one, display default image if error is returned */
				if (has_post_thumbnail()) {
					$thumburl = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );

					$status = checkRemoteFile($thumburl);
					if ($status == true) {
						ct_founder_featured_image();

					} else {
					echo '<div class="featured-image" style="background-image: url(\''
						. get_bloginfo( "stylesheet_directory" ) 
						. '/images/default-image.jpg\''
						. ')"></div>';
					} 

				} ?>
			

		<?php // show the original page (page 1)
				the_content(); ?>

				<div class="page-numbering">
					Page 1 by <?php the_author(); ?>.
				</div>

		<?php 
		/* 
		get the connected pages of this story and display them in order
		*/
			$connected = new WP_Query( array(
			  'connected_type' => 'story-pages_to_stories',
			  'connected_items' => get_queried_object(),
			  'connected_order' => 'asc',
			  'nopaging' => true,
			  'order' => 'asc',
			  'orderby' => 'post_date'
			) );

			// Display connected pages
			if ( $connected->have_posts() ) :
			?>
			
				<?php // remaining pages connected to parent story
				while ( $connected->have_posts() ) : $connected->the_post();
				/* display thumbnail if there is one, display default image if error is returned */
				if (has_post_thumbnail()) {
					$thumburl = wp_get_attachment_url( get_post_thumbnail_id($post->ID) );

					$status = checkRemoteFile($thumburl);
					if ($status == true) {
						ct_founder_featured_image();

					} else {
					echo '<div class="featured-image" style="background-image: url(\''
						. get_bloginfo( "stylesheet_directory" ) 
						. '/images/default-image.jpg\''
						. ')"></div>';
					} 

				} ?>

					<?php the_content(); ?>

			    	<div class="page-numbering">
			    		Page <?php echo get_post_meta( $post->ID, 'for_page', true ); ?> by <?php the_author(); ?>.
			    	</div>
			    	

				<?php endwhile;

			endif;

			// Prevent weirdness
			wp_reset_postdata(); ?>


		</div>

	</article>

		<?php hybrid_do_atomic( 'page_after' ); ?>

	<?php
	// Find connected pages to show draft submission thumbnails
	$connected = new WP_Query( array(
	  'connected_type' => 'story-pages_to_stories',
	  'connected_items' => get_queried_object(),
	  'connected_order' => 'asc',
	  'nopaging' => true,
	  'order' => 'asc',
	  'orderby' => 'post_date',
	  'post_status' => array('draft'),
	  'connected_meta' => array( 'forpage' => $submission_page )
	) );


	// don't allow submissions if there are already 5
	$draftcount = $connected->post_count; ?>

	<div class="page-status">
		<?php if ($pages_remaining < 1) {
			echo 'No more page submissions.  Story is finished!';
			} else {
			echo $draftcount . ' of 5 potential next pages submitted - Vote'; 
			} ?>
	</div>

	<?php // Display story pages connected to this story, but not published
	if ( $connected->have_posts() ) : ?>
		<div class="cat-grid page-thumbs-container">

			<?php while ( $connected->have_posts() ) : $connected->the_post(); ?>

				<a href="<?php echo the_permalink(); ?>">
					<div class="cat-card">
						by <?php the_author(); ?>
						<div class="thumb-stars">
							<?php echo do_shortcode('[ratings results="true"]'); ?>
						</div>
					</div>
				</a>
					
			<?php endwhile; ?>

		</div>

	<?php endif;		
		
	// Prevent weirdness
	wp_reset_postdata();


	$parent = strval($ca_story_id);
	$forpage = strval($submission_page);

	// find out if there is already a vote scheduled
	$timestamp = wp_next_scheduled( 'send_to_vote', array($parent, $forpage) ); ?>

	<?php // calculate how long until the voting event
		if ($timestamp != false) { 
			$datetime1 = new DateTime();
			$datetime2 = new DateTime("@" . $timestamp);
			$interval = $datetime1->diff($datetime2);
			$elapsed = $interval->format('%h hours %i minutes');
			$empties = array('0 hours', '0 minutes');
			
		} ?>

	<div class="page-info">

		<form method="post" action="<?php echo get_site_url() . '/add-new-page?task=new'; ?>">

			<input type="hidden" name="storyid" id="storyid" value="<?php echo $ca_story_id; ?>" />
			<input type="hidden" name="pagecount" id="pagecount" value="<?php echo get_page_count($ca_story_id); ?>" />


			<?php // display write next page button if applicable 
			if ($draftcount < 5 && $pages_remaining > 0)  {
				if ( ! is_user_logged_in() ) {
						echo '<a href="' . get_site_url() . '/login">
							<div class="story-button ca-btn write-page">Write Next Page</div>
						</a>';
				
				} else {
						echo '<input class="story-button ca-btn write-page" type="submit" value="Write Next Page" />';
				}
			}

			if ($draftcount > 4 && $pages_remaining > 0) {
				echo '<div><p>No more submissions for this page!<br /></p></div>';
			} ?>

		</form>

		<?php if ($timestamp != false) {
			echo'<div class="timer">';
				echo $elapsed . '<br /> until the next page.';
			echo'</div>';
		} ?>

	</div>
				 
	<?php comments_template(); ?>

</div>