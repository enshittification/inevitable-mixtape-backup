<?php
/**
 * The Abstract Mixtape_Interfaces_Model Base Class
 *
 * @package Mixtape/Model
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MT_Model
 */
class MT_Model implements
	MT_Interfaces_Model,
	MT_Interfaces_Permissions_Provider {

	/**
	 * Fields By Class Name
	 *
	 * @var array
	 */
	private static $fields_by_class_name = array();

	/**
	 * Data Stores By Class Name
	 *
	 * @var array
	 */
	private static $data_stores_by_class_name = array();

	/**
	 * The Single Environment instance all children models access
	 *
	 * @var MT_Environment
	 */
	private static $environment = null;

	/**
	 * Our data
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Our raw data
	 *
	 * @var array
	 */
	private $raw_data;

	/**
	 * Mixtape_Model constructor.
	 *
	 * @param array $data The data array.
	 * @param array $args Args.
	 *
	 * @throws MT_Exception Throws when data is not an array.
	 */
	function __construct( $data = array(), $args = array() ) {
		MT_Expect::that( is_array( $data ), '$data should be an array' );
		$this->data = array();

		if ( isset( $args['deserialize'] ) && true === $args['deserialize'] ) {
			unset( $args['deserialize'] );
			$data = $this->deserialize( $data );
		}
		$this->raw_data = $data;
		$data_keys = array_keys( $data );

		foreach ( $data_keys as $key ) {
			$this->set( $key, $this->raw_data[ $key ] );
		}
	}

	/**
	 * Gets the value of a previously defined field.
	 *
	 * @param string $field_name The field name.
	 * @param array  $args Any args.
	 *
	 * @return mixed
	 * @throws MT_Exception Fails when field is unknown.
	 */
	public function get( $field_name, $args = array() ) {
		MT_Expect::that( $this->has( $field_name ), 'Field ' . $field_name . 'is not defined' );
		$fields = self::get_fields();
		$field_declaration = $fields[ $field_name ];
		$this->set_field_if_unset( $field_declaration );

		return $this->prepare_value( $field_declaration );
	}

	/**
	 * Sets a field value.
	 *
	 * @param string $field The field name.
	 * @param mixed  $value The new field value.
	 *
	 * @return $this
	 * @throws MT_Exception Throws when trying to set an unknown field.
	 */
	public function set( $field, $value ) {
		MT_Expect::that( $this->has( $field ), 'Field ' . $field . 'is not defined' );
		$fields = self::get_fields();
		/**
		 * The declaration.
		 *
		 * @var MT_Field_Declaration $field_declaration The declaration.
		 */
		$field_declaration = $fields[ $field ];
		if ( isset( $args['deserializing'] ) && $args['deserializing'] ) {
			$value = $this->deserialize_field( $field_declaration, $value );
		}
		if ( null !== $field_declaration->before_set() ) {
			$val = $this->call( $field_declaration->before_set(), array( $value, $field_declaration->get_name() ) );
		} else {
			$val = $field_declaration->cast_value( $value );
		}
		$this->data[ $field_declaration->get_name() ] = $val;
		return $this;
	}

	/**
	 * Check if this model has a field
	 *
	 * @param string $field The field name to check.
	 * @return bool
	 */
	public function has( $field ) {
		$fields = self::get_fields();
		return isset( $fields[ $field ] );
	}

	/**
	 * Validate this Model's current state.
	 *
	 * @return bool|WP_Error Either true or WP_Error on failure.
	 */
	public function validate() {
		$validation_errors = array();
		$fields = self::get_fields();
		foreach ( $fields as $key => $field_declaration ) {
			$is_valid = $this->run_field_validations( $field_declaration );
			if ( is_wp_error( $is_valid ) ) {
				$validation_errors[] = $is_valid->get_error_data();
			}
		}
		if ( count( $validation_errors ) > 0 ) {
			return $this->validation_error( $validation_errors );
		}
		return true;
	}

	/**
	 * Sanitize this Model's current data.
	 *
	 * @return MT_Interfaces_Model $this
	 */
	public function sanitize() {
		$fields = self::get_fields();
		foreach ( $fields as $key => $field_declaration ) {
			/**
			 * Field Declaration.
			 *
			 * @var MT_Field_Declaration $field_declaration
			 */
			$field_name = $field_declaration->get_name();
			$value = $this->get( $field_name );
			$custom_sanitization = $field_declaration->get_sanitizer();
			if ( ! empty( $custom_sanitization ) ) {
				$value = $this->call( $custom_sanitization, array( $this, $value ) );
			} else {
				$value = $field_declaration->get_type()->sanitize( $value );
			}
			$this->set( $field_name, $value );
		}
		return $this;
	}

	/**
	 * We got a Validation Error
	 *
	 * @param array $error_data The details.
	 * @return WP_Error
	 */
	protected function validation_error( $error_data ) {
		return new WP_Error( 'validation-error', 'validation-error', $error_data );
	}

	/**
	 * Run Validations for this field.
	 *
	 * @param MT_Field_Declaration $field_declaration The field.
	 *
	 * @return bool|WP_Error
	 */
	protected function run_field_validations( $field_declaration ) {
		if ( $field_declaration->is_kind( MT_Field_Declaration::DERIVED ) ) {
			return true;
		}
		$value = $this->get( $field_declaration->get_name() );
		if ( $field_declaration->is_required() && empty( $value ) ) {
			// translators: %s is usually a field name.
			$message = sprintf( __( '%s cannot be empty', 'mixtape' ), $field_declaration->get_name() );
			return new WP_Error( 'required-field-empty', $message );
		} elseif ( ! $field_declaration->is_required() && ! empty( $value ) ) {
			foreach ( $field_declaration->get_validations() as $validation ) {
				$result = $this->call( $validation, array( $value ) );
				if ( is_wp_error( $result ) ) {
					$result->add_data(array(
						'reason' => $result->get_error_messages(),
						'field' => $field_declaration->get_data_transfer_name(),
						'value' => $value,
					) );
					return $result;
				}
			}
		}
		return true;
	}

	/**
	 * Prepare the value associated with this declaraton for output.
	 *
	 * @param MT_Field_Declaration $field_declaration The declaration to use.
	 * @return mixed
	 */
	private function prepare_value( $field_declaration ) {
		$key = $field_declaration->get_name();
		$value = $this->data[ $key ];
		$before_return = $field_declaration->before_get();
		if ( isset( $before_return ) && ! empty( $before_return ) ) {
			$value = $this->call( $before_return, array( $value, $key ) );
		}

		return $value;
	}

	/**
	 * Sets this field's value. Used for derived fields.
	 *
	 * @param MT_Field_Declaration $field_declaration The field declaration.
	 */
	private function set_field_if_unset( $field_declaration ) {
		$field_name = $field_declaration->get_name();
		if ( ! isset( $this->data[ $field_name ] ) ) {
			if ( $field_declaration->is_kind( MT_Field_Declaration::DERIVED ) ) {
				$map_from = $field_declaration->get_map_from();
				$value    = $this->call( $map_from );
				$this->set( $field_name, $value );
			} else {
				$this->set( $field_name, $field_declaration->get_default_value() );
			}
		}
	}

	/**
	 * Get this model class fields
	 *
	 * @param null|string $filter_by_type Filter.
	 * @return array
	 */
	public static function get_fields( $filter_by_type = null ) {
		$class_name = get_called_class();
		if ( ! isset( self::$fields_by_class_name[ $class_name ] ) ) {
			$fields = static::declare_fields();
			self::$fields_by_class_name[ $class_name ] = self::initialize_field_map( $fields );
		}

		if ( null === $filter_by_type ) {
			return self::$fields_by_class_name[ $class_name ];
		}

		$filtered = array();

		foreach ( self::$fields_by_class_name[ $class_name ] as $field_declaration ) {
			/**
			 * The field declaration.
			 *
			 * @var MT_Field_Declaration $field_declaration
			 */
			if ( $field_declaration->get_kind() === $filter_by_type ) {
				$filtered[] = $field_declaration;
			}
		}
		return $filtered;
	}

	/**
	 * Initialize_field_map
	 *
	 * @param array $declared_field_builders Array<Mixtape_Model_Field_Declaration_Builder>.
	 *
	 * @return array
	 */
	private static function initialize_field_map( $declared_field_builders ) {
		$fields = array();
		foreach ( $declared_field_builders as $field_builder ) {
			/**
			 * Builder
			 *
			 * @var MT_Field_Declaration $field Field Builder.
			 */
			$field = $field_builder->build();
			$fields[ $field->get_name() ] = $field;
		}
		return $fields;
	}

	/**
	 * Get this model's data store
	 *
	 * @return MT_Interfaces_Data_Store
	 */
	public static function get_data_store() {
		$class_name = get_called_class();
		if ( ! isset( self::$data_stores_by_class_name[ $class_name ] ) ) {
			self::$data_stores_by_class_name[ $class_name ] = new MT_Data_Store_Nil();
		}
		return self::$data_stores_by_class_name[ $class_name ];
	}

	/**
	 * Set this model's data store
	 *
	 * @param MT_Interfaces_Data_Store $data_store A builder or a Data store.
	 * @throws MT_Exception Throws when Data Store Invalid.
	 */
	public static function with_data_store( $data_store ) {
		$class_name = get_called_class();
		// at this point we should have a data store.
		MT_Expect::is_a( $data_store, 'MT_Interfaces_Data_Store' );
		self::$data_stores_by_class_name[ $class_name ] = $data_store;
	}

	/**
	 * Get this model's data store
	 *
	 * @return MT_Environment
	 */
	public static function get_environment() {
		return self::$environment;
	}

	/**
	 * Set the model base class environment (change effective in all subclasses)
	 *
	 * @param MT_Environment $environment The Environment.
	 *
	 * @throws MT_Exception If an MT_Environment is not provided.
	 */
	public static function with_environment( $environment ) {
		MT_Expect::is_a( $environment, 'MT_Environment' );
		self::$environment = $environment;
	}

	/**
	 * Create a new Model Instance
	 *
	 * @param array $data The data.
	 * @param array $args Args.
	 *
	 * @return MT_Interfaces_Model
	 * @throws MT_Exception Throws if data not an array.
	 */
	public static function create( $data, $args = array() ) {
		MT_Expect::that( is_array( $data ), '$data should be an array' );
		MT_Expect::that( is_array( $args ), '$args should be an array' );
		$class_name = get_called_class();
		return new $class_name( $data, $args );
	}

	/**
	 * Merge values from array with current values.
	 * Note: Values change in place.
	 *
	 * @param array $data The data (key-value assumed).
	 * @param bool  $updating Is this an update?.
	 *
	 * @return MT_Interfaces_Model|WP_Error
	 * @throws MT_Exception Throws.
	 */
	function update_from_array( $data, $updating = false ) {
		$mapped_data = self::map_data( $data, $updating );
		foreach ( $mapped_data as $name => $value ) {
			$this->set( $name, $value );
		}
		return $this->sanitize();
	}

	/**
	 * Creates a new Model From a Data Array
	 *
	 * @param array $data The Data.
	 *
	 * @return MT_Model|WP_Error
	 */
	public static function new_from_array( $data ) {
		$field_data = self::map_data( $data, false );
		return self::create( $field_data )->sanitize();
	}

	/**
	 * Get field DTO Mappings
	 *
	 * @return array
	 */
	public function get_dto_field_mappings() {
		$mappings = array();
		foreach ( self::get_fields() as $field_declaration ) {
			/**
			 * Declaration
			 *
			 * @var MT_Field_Declaration $field_declaration
			 */
			if ( ! $field_declaration->supports_output_type( 'json' ) ) {
				continue;
			}
			$mappings[ $field_declaration->get_data_transfer_name() ] = $field_declaration->get_name();
		}
		return $mappings;
	}

	/**
	 * Prepare the Model for Data Transfer
	 *
	 * @return array
	 */
	function to_dto() {
		$result = array();
		foreach ( self::get_dto_field_mappings() as $mapping_name => $field_name ) {
			$value = $this->get( $field_name );
			$result[ $mapping_name ] = $value;
		}

		return $result;
	}

	/**
	 * Map data names
	 *
	 * @param array $data The data to map.
	 * @param bool  $updating Are we Updating.
	 * @return array
	 */
	private static function map_data( $data, $updating = false ) {
		$request_data = array();
		$fields = self::get_fields();
		foreach ( $fields as $field ) {
			/**
			 * Field
			 *
			 * @var MT_Field_Declaration $field Field.
			 */
			if ( $field->is_kind( MT_Field_Declaration::DERIVED ) ) {
				continue;
			}
			$dto_name = $field->get_data_transfer_name();
			$field_name = $field->get_name();
			if ( isset( $data[ $dto_name ] ) && ! ( $updating && $field->is_primary() ) ) {
				$value = $data[ $dto_name ];
				$request_data[ $field_name ] = $value;
			}
		}
		return $request_data;
	}

	/**
	 * Call a method.
	 *
	 * @param string $method The method.
	 * @param array  $args The args.
	 *
	 * @return mixed
	 * @throws MT_Exception Throw if method nonexistent.
	 */
	private function call( $method, $args = array() ) {
		if ( is_callable( $method ) ) {
			return call_user_func_array( $method, $args );
		}
		MT_Expect::that( method_exists( $this, $method ), $method . ' does not exist' );
		return call_user_func_array( array( $this, $method ), $args );
	}

	/**
	 * Get name
	 *
	 * @return string
	 */
	public function get_name() {
		return strtolower( get_class( $this ) );
	}

	/**
	 * Declare fields.
	 *
	 * @return array
	 */
	public static function declare_fields() {
		MT_Expect::should_override( __METHOD__ );
		return array();
	}

	/**
	 * Get the id
	 *
	 * @return mixed|null
	 */
	function get_id() {
		return $this->get( 'id' );
	}

	/**
	 * Set the id
	 *
	 * @param mixed $new_id The new id.
	 *
	 * @return mixed|null
	 */
	function set_id( $new_id ) {
		return $this->set( 'id', $new_id );
	}

	/**
	 * Handle Permissions for a REST Controller Action
	 *
	 * @param WP_REST_Request $request The request.
	 * @param string          $action The action (e.g. index, create update etc).
	 * @return bool
	 */
	public static function permissions_check( $request, $action ) {
		return true;
	}

	/**
	 * Create from Post.
	 *
	 * @param WP_Post $post Post.
	 * @return MT_Model
	 * @throws MT_Exception If something goes wrong.
	 */
	public static function from_raw_data( $post ) {
		$raw_post_data = $post->to_array();
		$raw_meta_data = get_post_meta( $post->ID ); // assumes we are only ever adding one postmeta per key.

		$flattened_meta = array();
		foreach ( $raw_meta_data as $key => $value_arr ) {
			$flattened_meta[ $key ] = $value_arr[0];
		}
		$merged_data = array_merge( $raw_post_data, $flattened_meta );

		return self::create( $merged_data, array(
			'deserialize' => true,
		) );
	}

	/**
	 * Transform raw data to model data
	 *
	 * @param array $data Data.
	 * @return array
	 */
	public function deserialize( $data ) {
		$field_declarations = self::get_fields();
		$raw_data = array();
		$post_array_keys = array_keys( $data );
		foreach ( $field_declarations as $declaration ) {
			/**
			 * Declaration
			 *
			 * @var MT_Field_Declaration $declaration
			 */
			$key = $declaration->get_name();
			$mapping = $declaration->get_map_from();
			$value = null;
			if ( in_array( $key, $post_array_keys, true ) ) {
				// simplest case: we got a $key for this, so just map it.
				$value = $this->deserialize_field( $declaration, $data[ $key ] );
			} elseif ( in_array( $mapping, $post_array_keys, true ) ) {
				$value = $this->deserialize_field( $declaration, $data[ $mapping ] );
			} else {
				$value = $declaration->get_default_value();
			}
			$raw_data[ $key ] = $declaration->cast_value( $value );
		}
		return $raw_data;
	}

	/**
	 * Transform Model to raw data array
	 *
	 * @param null|string $field_type Type.
	 *
	 * @return array
	 */
	function serialize( $field_type = null ) {
		$field_values_to_insert = array();
		foreach ( self::get_fields( $field_type ) as $field_declaration ) {
			/**
			 * Declaration
			 *
			 * @var MT_Field_Declaration $field_declaration
			 */
			$what_to_map_to = $field_declaration->get_map_from();
			$value = $this->get( $field_declaration->get_name() );
			$field_values_to_insert[ $what_to_map_to ] = $this->serialize_field( $field_declaration, $value );
		}

		return $field_values_to_insert;
	}

	/**
	 * Deserialize
	 *
	 * @param MT_Field_Declaration $field_declaration Declaration.
	 * @param mixed                $value Value.
	 * @return mixed the deserialized value
	 */
	private function deserialize_field( $field_declaration, $value ) {
		$deserializer = $field_declaration->get_deserializer();
		if ( isset( $deserializer ) && ! empty( $deserializer ) ) {
			return $this->call( $deserializer, array( $value ) );
		}
		return $value;
	}

	/**
	 * Serialize
	 *
	 * @param  MT_Field_Declaration $field_declaration Declaration.
	 * @param mixed                $value Value.
	 * @return mixed
	 * @throws MT_Exception If call fails.
	 */
	private function serialize_field( $field_declaration, $value ) {
		$serializer = $field_declaration->get_serializer();
		if ( isset( $serializer ) && ! empty( $serializer ) ) {
			return $this->call( $serializer, array( $value ) );
		}
		return $value;
	}
}
