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

$gallery_id      = (int) $attributes['galleryId'];
$sort_by         = $attributes['sortBy'] ?? 'date';
$sort_order      = $attributes['sortOrder'] ?? 'DESC';
$slideshow       = ! empty( $attributes['slideshow'] );
$tempo           = (int) ( $attributes['tempo'] ?? 3000 );
$resolution      = $attributes['resolution'] ?? 'full';
$layout          = $attributes['layout'] ?? 'viewer';
$images_per_page = (int) ( $attributes['imagesPerPage'] ?? 30 );

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

// Construire le tableau de données d'images pour le JS
$images_data = [];
foreach ( $attachments as $index => $attachment ) {
	$thumb_src = wp_get_attachment_image_url( $attachment->ID, 'medium_large' ) ?: wp_get_attachment_image_url( $attachment->ID, 'medium' ) ?: wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ) ?: '';
	$full_src  = wp_get_attachment_image_url( $attachment->ID, $resolution ) ?: '';
	$alt       = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) ?: $attachment->post_title;
	
	$meta = wp_get_attachment_metadata( $attachment->ID );
	$width = ! empty( $meta['width'] ) ? (int) $meta['width'] : 150;
	$height = ! empty( $meta['height'] ) ? (int) $meta['height'] : 150;

	$images_data[] = [
		'index'    => $index,
		'thumbSrc' => $thumb_src,
		'fullSrc'  => $full_src,
		'alt'      => $alt,
		'width'    => $width,
		'height'   => $height,
	];
}

$first_attachment = $attachments[0];
$first_image_src  = wp_get_attachment_image_url( $first_attachment->ID, $resolution ) ?: '';
$first_image_alt  = get_post_meta( $first_attachment->ID, '_wp_attachment_image_alt', true ) ?: $first_attachment->post_title;

$wrapper_classes = 'eg-viewer';
if ( 'justified' === $layout ) {
	$wrapper_classes .= ' eg-viewer--layout-justified';
}

$wrapper_attributes = get_block_wrapper_attributes( [
	'class'            => $wrapper_classes,
	'data-slideshow'   => $slideshow ? 'true' : 'false',
	'data-tempo'       => esc_attr( (string) $tempo ),
	'data-layout'      => esc_attr( $layout ),
	'data-limit'       => esc_attr( (string) $images_per_page ),
	'data-images-json' => esc_attr( wp_json_encode( $images_data ) ),
] );
?>
<div <?php echo $wrapper_attributes; ?>>
	
	<button class="eg-viewer__close" aria-label="<?php esc_attr_e( 'Fermer le plein écran', 'eg-media' ); ?>">&times;</button>

	<?php if ( 'justified' === $layout ) : ?>
		<div class="eg-viewer__justified-grid">
			<?php 
			$initial_count = min( count( $images_data ), $images_per_page );
			for ( $i = 0; $i < $initial_count; $i++ ) :
				$img_data = $images_data[ $i ];
				$aspect_ratio = $img_data['width'] / $img_data['height'];
				$flex_basis = $aspect_ratio * 150;
			?>
				<div class="eg-viewer__justified-item" 
					 data-index="<?php echo $i; ?>" 
					 style="flex-grow: <?php echo $aspect_ratio; ?>; flex-basis: <?php echo $flex_basis; ?>px;">
					<img src="<?php echo esc_url( $img_data['thumbSrc'] ); ?>" 
						 alt="<?php echo esc_attr( $img_data['alt'] ); ?>" 
						 loading="lazy" />
				</div>
			<?php endfor; ?>
		</div>
		<?php if ( count( $images_data ) > $images_per_page ) : ?>
			<div class="eg-viewer__load-more-container">
				<button class="eg-viewer__load-more-btn">
					<?php esc_html_e( 'Charger plus d\'images', 'eg-media' ); ?>
				</button>
			</div>
		<?php endif; ?>
	<?php endif; ?>

	<div class="eg-viewer__main">
		<img class="eg-viewer__main-image" src="<?php echo esc_url( $first_image_src ); ?>" alt="<?php echo esc_attr( (string) $first_image_alt ); ?>" />
	</div>

	<div class="eg-viewer__track-container">
		<button class="eg-viewer__arrow eg-viewer__arrow--left" aria-label="<?php esc_attr_e( 'Précédent', 'eg-media' ); ?>">&lsaquo;</button>
		
		<div class="eg-viewer__thumbnails">
			<div class="eg-viewer__track">
				<?php foreach ( $attachments as $index => $attachment ) : 
					$thumb_src = wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ) ?: '';
					$full_src  = wp_get_attachment_image_url( $attachment->ID, $resolution ) ?: '';
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
						<img src="<?php echo esc_url( $thumb_src ); ?>" alt="<?php echo esc_attr( (string) $alt ); ?>" loading="lazy" />
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<button class="eg-viewer__arrow eg-viewer__arrow--right" aria-label="<?php esc_attr_e( 'Suivant', 'eg-media' ); ?>">&rsaquo;</button>
	</div>
</div>
