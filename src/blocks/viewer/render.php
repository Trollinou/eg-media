<?php
/**
 * Rendu dynamique du bloc Visionneuse de Galerie.
 *
 * @package EG_MEDIA
 */

declare(strict_types=1);

if ( empty( $attributes['galleryId'] ) ) {
	return;
}

$gallery_id = (int) $attributes['galleryId'];
$sort_by    = $attributes['sortBy'] ?? 'date';
$sort_order = $attributes['sortOrder'] ?? 'DESC';
$slideshow  = ! empty( $attributes['slideshow'] );
$tempo      = (int) ( $attributes['tempo'] ?? 3000 );

// Récupérer les pièces jointes
$query_args = [
	'post_type'      => 'attachment',
	'post_status'    => 'inherit',
	'posts_per_page' => -1,
	'tax_query'      => [
		[
			'taxonomy' => 'eg_media_gallery',
			'field'    => 'term_id',
			'terms'    => $gallery_id,
		],
	],
];

$attachments = get_posts( $query_args );

if ( empty( $attachments ) ) {
	return;
}

// Logique de tri
usort( $attachments, function ( \WP_Post $a, \WP_Post $b ) use ( $sort_by, $sort_order ) : int {
	$val_a = '';
	$val_b = '';

	if ( 'name' === $sort_by ) {
		$val_a = ! empty( $a->post_title ) ? $a->post_title : basename( get_attached_file( $a->ID ) ?: '' );
		$val_b = ! empty( $b->post_title ) ? $b->post_title : basename( get_attached_file( $b->ID ) ?: '' );
		$comparison = strcasecmp( (string) $val_a, (string) $val_b );
	} else {
		$meta_a = wp_get_attachment_metadata( $a->ID );
		$meta_b = wp_get_attachment_metadata( $b->ID );

		$time_a = ! empty( $meta_a['image_meta']['created_timestamp'] ) ? (int) $meta_a['image_meta']['created_timestamp'] : strtotime( $a->post_date );
		$time_b = ! empty( $meta_b['image_meta']['created_timestamp'] ) ? (int) $meta_b['image_meta']['created_timestamp'] : strtotime( $b->post_date );

		$comparison = $time_a <=> $time_b;
	}

	return 'DESC' === $sort_order ? -$comparison : $comparison;
} );

$first_attachment = $attachments[0];
$first_image_src  = wp_get_attachment_image_url( $first_attachment->ID, 'large' ) ?: '';
$first_image_alt  = get_post_meta( $first_attachment->ID, '_wp_attachment_image_alt', true ) ?: $first_attachment->post_title;
?>
<div class="eg-viewer" 
	 data-slideshow="<?php echo $slideshow ? 'true' : 'false'; ?>" 
	 data-tempo="<?php echo esc_attr( (string) $tempo ); ?>">
	
	<div class="eg-viewer__main">
		<img class="eg-viewer__main-image" src="<?php echo esc_url( $first_image_src ); ?>" alt="<?php echo esc_attr( (string) $first_image_alt ); ?>" />
	</div>

	<div class="eg-viewer__track-container">
		<button class="eg-viewer__arrow eg-viewer__arrow--left" aria-label="<?php esc_attr_e( 'Précédent', 'eg-media' ); ?>">&lsaquo;</button>
		
		<div class="eg-viewer__thumbnails">
			<div class="eg-viewer__track">
				<?php foreach ( $attachments as $index => $attachment ) : 
					$thumb_src = wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ) ?: '';
					$full_src  = wp_get_attachment_image_url( $attachment->ID, 'large' ) ?: '';
					$alt       = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ?: $attachment->post_title;
					
					$meta = wp_get_attachment_metadata( $attachment->ID );
					$width = ! empty( $meta['width'] ) ? (int) $meta['width'] : 150;
					$height = ! empty( $meta['height'] ) ? (int) $meta['height'] : 150;
				?>
					<div class="eg-viewer__thumbnail<?php echo 0 === $index ? ' eg-viewer__thumbnail--active' : ''; ?>" 
						 data-index="<?php echo $index; ?>" 
						 data-full-src="<?php echo esc_url( $full_src ); ?>"
						 data-width="<?php echo esc_attr( (string) $width ); ?>"
						 data-height="<?php echo esc_attr( (string) $height ); ?>">
						<img src="<?php echo esc_url( $thumb_src ); ?>" alt="<?php echo esc_attr( (string) $alt ); ?>" />
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<button class="eg-viewer__arrow eg-viewer__arrow--right" aria-label="<?php esc_attr_e( 'Suivant', 'eg-media' ); ?>">&rsaquo;</button>
	</div>
</div>
