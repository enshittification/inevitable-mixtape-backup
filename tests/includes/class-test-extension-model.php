<?php

class Test_Extension_Model extends MT_Model {
	/**
	 * Field name.
	 *
	 * @var string
	 */
	private static $field_name = '_custom_field_on_posts';

	/**
	 * Declare Fields
	 *
	 * @param MT_Environment $env Env.
	 * @return array
	 */
	public static function declare_fields() {
		$env = self::get_environment();
		return array(
			$env->field( 'id', 'the post id' )
				->with_map_from( 'ID' ),
			$env->field( self::$field_name, 'A Custom Field on Posts' )
				->with_type( $env->type( 'uint' ) )
				->derived()
				->with_reader( array( __CLASS__, 'reader' ) )
				->with_updater( array( __CLASS__, 'updater' ) ),
		);
	}

	static function reader( $object, $field_name, $request, $object_type ) {
		if ( $field_name !== self::$field_name ) {
			return null;
		}

		$data = get_post_meta( $object['id'], self::$field_name, true );
		return ( false === $data ) ? 0 : absint( $data );
	}

	static function updater( $val ) {
		global $post;
		return update_post_meta( $post->ID, '_custom_field_on_posts', $val );
	}
}