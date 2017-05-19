<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Mixtape_Data_Store_Builder {
    private $post_type = 'post';
    private $store_class = 'Mixtape_Data_Store_CustomPostType';
    /**
     * @var Mixtape_Model_Definition
     */
    private $model_definition;
    private $store_classes = array(
        'custom_post_type' => 'Mixtape_Data_Store_CustomPostType',
        'option'           => 'Mixtape_Data_Store_Option',
        'nil'              => 'Mixtape_Data_Store_Nil',
        'in_memory'        => 'Mixtape_Data_Store_Nil',
    );

    public function custom_post_type() {
        return $this->set_class( __FUNCTION__ );
    }

    public function option() {
        return $this->set_class( __FUNCTION__ );
    }

    public function nil() {
        return $this->set_class( __FUNCTION__ );
    }

    private function set_class( $func ) {
        $this->store_class = $this->store_classes[$func];
        return $this;
    }

    function with_post_type( $post_type ) {
        $this->post_type = $post_type;
        return $this;
    }

    function with_model_definition( $model_definition ) {
        $this->model_definition = $model_definition;
        return $this;
    }

    function build() {
        $store_class = $this->store_class;
        return new $store_class( $this->model_definition, $this->post_type );
    }
}