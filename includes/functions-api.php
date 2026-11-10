<?php
/**
 * Public template API.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether a user has a customer tag slug.
 *
 * @param int    $user_id User ID.
 * @param string $slug    Tag slug.
 */
function ct_user_has_tag( int $user_id, string $slug ): bool {
	return CT\Storage\TagRepository::user_has_tag( $user_id, $slug );
}

/**
 * Tag slugs for a user.
 *
 * @param int $user_id User ID.
 * @return string[]
 */
function ct_get_user_tags( int $user_id ): array {
	return CT\Storage\TagRepository::get_user_tag_slugs( $user_id );
}
