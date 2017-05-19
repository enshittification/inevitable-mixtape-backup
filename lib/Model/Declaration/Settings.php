<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class Mixtape_Model_Declaration_Settings
 * Represents a single setting field
 */
class Mixtape_Model_Declaration_Settings extends Mixtape_Model_Declaration {

    /**
     * @return array
     * @throws Mixtape_Exception
     */
    function get_settings() {
        Mixtape_Expect::that( false, 'Override this' );
    }

    protected function dto_name_for_field( $field_data ) {
        return $field_data['name'];
    }

    protected function default_for_attribute( $field_data, $attribute ) {
        return null;
    }

    function declare_fields( $def ) {
        $settings_per_group = $this->get_settings();
        $fields = array();

        foreach ( $settings_per_group as $group_name => $group_data ) {
            $group_description = $group_data[0];
            $group_fields = $group_data[1];

            foreach ( $group_fields as $field_data ) {

                $field_name = $field_data['name'];
                $field_builder = $def->field( $field_name );
                $field_dto_name = $this->dto_name_for_field( $field_data );
                $default_value = isset( $field_data['std'] ) ? $field_data['std'] : $this->default_for_attribute( $field_data, 'std' );
                $label         = isset( $field_data['label'] ) ? $field_data['label'] : $field_name;
                $description   = isset( $field_data['desc'] ) ? $field_data['desc'] : $label;
                $setting_type  = isset( $field_data['type'] ) ? $field_data['type'] : null;
                $choices       = isset( $field_data['options'] ) ? $field_data['options'] : null;
                $field_type = 'string';



                if ( 'checkbox' === $setting_type ) {
                    $field_type = 'boolean';
                    if ( $default_value ) {
                        // convert our default value as well
                        $default_value = $this->bit_to_bool( $default_value );
                    }
                    $field_builder
                        ->with_serializer( 'bool_to_bit' )
                        ->with_deserializer( 'bit_to_bool' );

                } else if ( 'select' === $setting_type ) {
                    $field_type = 'string';
                } else {
                    // try to guess numeric fields, although this is not perfect
                    if ( is_numeric( $default_value ) ) {
                        $field_type = is_float( $default_value ) ? 'float' : 'integer';
                    }
                }

                if ( $default_value ) {
                    $field_builder->with_default( $default_value );
                }
                $field_builder
                    ->description( $label )
                    ->dto_name( $field_dto_name )
                    ->typed( $def->type( $field_type ) );
                if ( $choices ) {
                    $field_builder->choices( $choices );
                }

                $fields[] = $field_builder;
            }
        }
        return $fields;
    }

    function bool_to_bit( $value ) {
        return ( ! empty( $value ) && 'false' !== $value ) ? '1' : '';
    }

    function bit_to_bool( $value ) {
        return ( ! empty( $value ) && '0' !== $value ) ? true : false;
    }

    function get_id($model) {
        return strtolower( get_class( $this ) );
    }

    function set_id($model, $new_id) {
        return $this;
    }
}