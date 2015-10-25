<div <?php post_class(); ?>>
	<?php hybrid_do_atomic( 'post_before' ); ?>
	
	<?php 
	$next_page = get_post_meta($post->ID, 'for_page', true);
	$ca_parent_id = get_post_meta( $post->ID, 'parent_story_id', true ); 
	?>

	<h2 class="headline">Submission Page</h2>

	<article>
		
		<div class="post-header ca-story-header">

			<div class="pagenumber-box">
				<span>page</span><br />
				<div class="largepage">
					<?php echo $next_page; ?>
				</div>
			</div>

			<div class="story-avatar">
				<?php $authorid = get_the_author_meta( 'ID' );
				echo '<a href="' . get_author_posts_url($authorid) . '">' . get_avatar( get_the_author_meta( 'ID' ), 64 ) . '</a>'; ?>
			</div>
			
			<h1 class="post-title"><?php echo get_the_title($ca_parent_id); ?></h1>

			<div class="story-author">
				Submission by: <?php the_author(); ?>
			</div>
			<br />

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

	        <?php the_content(); ?>

	    </div>

		<?php 
			// show star ratings voting submission
			if(function_exists('the_ratings')) { 
				the_ratings(); 
			}
		?>

		<?php // count submitted drafts of this page
		global $wpdb;
		//$wpdb->show_errors();

		$draftcount = $wpdb->get_var($wpdb->prepare(
		"SELECT COUNT(p2p_id)
		FROM wp_p2p T1
		JOIN wp_posts T2 ON T1.p2p_from = T2.ID
		JOIN wp_postmeta T3 ON T2.ID = T3.post_id and T3.meta_key = 'for_page' and T3.meta_value = %d
		WHERE T2.post_status = 'draft'
		AND (T1.p2p_to = %d)",
		$next_page, $ca_parent_id) 
		);

		// check if countdown has already begun
		$timestamp = wp_next_scheduled( 'send_to_vote', array($ca_parent_id, $next_page) );


		/* begin countdown if 3 or more drafts and no countdown yet.
		 this is needed to restart timer if no more submissions have been made and no votes have been cast. */	
		if ($draftcount > 2 && $timestamp == false)  {
		wp_schedule_single_event( time() + 900, 'send_to_vote', array($ca_parent_id, $next_page) );
		} // time() + 129600 = 36 hours from now.
		?>

		<div class="bottom-info">

			<div class="text-left">
				<strong>Click on a star to <br />submit your vote.</strong>
			</div>

			<div class="timer">

				<?php // show timer if there is already a countdown
				if ($timestamp != false) { 
					$datetime1 = new DateTime();
					$datetime2 = new DateTime("@" . $timestamp);
					$interval = $datetime1->diff($datetime2);
					$elapsed = $interval->format('%h hours %i minutes');
					$empties = array('0 hours', '0 minutes');
					echo $elapsed . '<br /> until the next page.';
				} ?>

			</div>

		</div>

	</article>
	
</div>