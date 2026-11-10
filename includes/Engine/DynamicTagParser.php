<?php
/**
 * Dynamic tag templates.
 *
 * @package Customer_Tagger
 */

declare( strict_types=1 );

namespace CT\Engine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parses {field.path} placeholders into tag slugs.
 */
class DynamicTagParser {

	public static function parse( string $template, Context $ctx ): string {
		$out = preg_replace_callback(
			'/\{([a-z0-9_.]+)\}/i',
			static function ( array $m ) use ( $ctx ): string {
				$value = Evaluator::resolve_field_value( $m[1], $ctx );
				if ( is_bool( $value ) ) {
					return $value ? 'yes' : 'no';
				}
				if ( is_array( $value ) ) {
					return sanitize_title( (string) ( $value[0] ?? '' ) );
				}
				return sanitize_title( (string) $value );
			},
			$template
		);

		return \CT\Storage\TagRepository::sanitize_slug( (string) $out );
	}
}
