<?php
/**
 * Settings Model
 *
 * @package MT/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MT_Model_Settings
 * Represents a single setting set
 */
class MT_Model_Settings extends MT_Model {

	/**
	 * Get Settings
	 *
	 * @throws MT_Exception Override this.
	 * @return array
	 */
	public static function get_settings() {
		MT_Expect::should_override( __METHOD__ );
		return array();
	}

	/**
	 * Default for Attribute. Override to change this behavior
	 *
	 * @param array  $field_data Data.
	 * @param string $attribute Attr.
	 * @return mixed
	 */
	protected static function default_for_attribute( $field_data, $attribute ) {
		return null;
	}

	/**
	 * On Field Setup
	 *
	 * @param string                       $field_name Name.
	 * @param MT_Field_Declaration_Builder $field_builder Builder.
	 * @param array                        $field_data Data.
	 * @param MT_Environment               $env Env.
	 * @return void
	 */
	protected static function on_field_setup( $field_name, $field_builder, $field_data, $env ) {
	}

	/**
	 * Declare Fields
	 *
	 * @return array
	 */
	public static function declare_fields() {
		$env = self::get_environment();
		$settings_per_group = static::get_settings();
		$fields = array();

		foreach ( $settings_per_group as $group_name => $group_data ) {
			$group_fields = $group_data[1];

			foreach ( $group_fields as $field_data ) {
				$field_builder = self::field_declaration_builder_from_data( $env, $field_data );
				$fields[] = $field_builder;
			}
		}
		return $fields;
	}

	/**
	 * Convert bool to bit
	 *
	 * @param mixed $value Val.
	 * @return string
	 */
	static function bool_to_bit( $value ) {
		return ( ! empty( $value ) && 'false' !== $value ) ? '1' : '';
	}

	/**
	 * Covert bit to bool
	 *
	 * @param mixed $value Val.
	 * @return bool
	 */
	static function bit_to_bool( $value ) {
		return ( ! empty( $value ) && '0' !== $value ) ? true : false;
	}

	/**
	 * Get ID
	 *
	 * @return string
	 */
	function get_id() {
		return strtolower( get_class( $this ) );
	}

	/**
	 * Set ID
	 *
	 * @param mixed $new_id New ID.
	 * @return MT_Interfaces_Model $this
	 */
	function set_id( $new_id ) {
		return $this;
	}

	/**
	 * Build declarations from array
	 *
	 * @param MT_Environment $env Environment.
	 * @param array          $field_data Data.
	 * @return MT_Field_Declaration_Builder
	 */
	private static function field_declaration_builder_from_data( $env, $field_data ) {
		$field_name = $field_data['name'];
		$field_builder = $env->field( $field_name );
		$default_value = isset( $field_data['std'] ) ? $field_data['std'] : static::default_for_attribute( $field_data, 'std' );
		$label = isset( $field_data['label'] ) ? $field_data['label'] : $field_name;
		$description = isset( $field_data['desc'] ) ? $field_data['desc'] : $label;
		$setting_type = isset( $field_data['type'] ) ? $field_data['type'] : null;
		$choices = isset( $field_data['options'] ) ? array_keys( $field_data['options'] ) : null;
		$field_type = 'string';

		if ( 'checkbox' === $setting_type ) {
			$field_type = 'boolean';
			if ( $default_value ) {
				// convert our default value as well.
				$default_value = static::bit_to_bool( $default_value );
			}
			$field_builder
				->with_serializer( array( __CLASS__, 'bool_to_bit' ) )
				->with_deserializer( array( __CLASS__, 'bit_to_bool' ) );

		} elseif ( 'select' === $setting_type ) {
			$field_type = 'string';
		} else {
			// try to guess numeric fields, although this is not perfect.
			if ( is_numeric( $default_value ) ) {
				$field_type = is_float( $default_value ) ? 'float' : 'integer';
			}
		}

		if ( $default_value ) {
			$field_builder->with_default( $default_value );
		}
		$field_builder
			->with_description( $description )
			->with_dto_name( $field_name )
			->with_type( $env->type( $field_type ) );
		if ( $choices ) {
			$field_builder->with_choices( $choices );
		}

		static::on_field_setup( $field_name, $field_builder, $field_data, $env );

		return $field_builder;
	}
}
