<?php
/**
 * Free vs Pro limits.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\License;

use CT\Storage\TagRepository;
use CT\Storage\WorkflowRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tier gating for workflows and tags.
 */
class Limits {

	public const FREE_WORKFLOW_LIMIT = 2;
	public const FREE_TAG_LIMIT      = 100;

	public static function is_pro(): bool {
		return (bool) apply_filters( 'ct_is_pro', (bool) get_option( 'ct_pro_license_active', false ) );
	}

	public static function can_activate_workflow(): bool {
		if ( self::is_pro() ) {
			return true;
		}
		return WorkflowRepository::count_active() < self::FREE_WORKFLOW_LIMIT;
	}

	public static function can_add_tags( int $count = 1 ): bool {
		if ( self::is_pro() ) {
			return true;
		}
		return TagRepository::count_tags() + $count <= self::FREE_TAG_LIMIT;
	}

	public static function workflow_may_run( int $workflow_id ): bool {
		unset( $workflow_id );
		return true;
	}

	public static function can_backfill(): bool {
		return self::is_pro() || apply_filters( 'ct_allow_free_backfill', false );
	}
}
