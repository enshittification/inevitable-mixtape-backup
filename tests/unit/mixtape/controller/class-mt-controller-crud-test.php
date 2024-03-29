<?php

class MT_Controller_CRUDTest extends MT_Testing_TestCase {
    /**
     * @var array
     */
    private $casettes;

    function setUp() {
        parent::setUp();
        $this->mixtape->environment()->define_model( 'Casette' );
    }

    function test_exists() {
        $this->assertClassExists( 'MT_Controller_CRUD' );
    }

    function test_get_items_return_all_items() {
        $this->add_casette_rest_api_endpoint();
        $response = $this->get( '/casette-crud-test/v1/casettes' );

        $this->assertNotNull( $response );
        $this->assertResponseStatus( $response, 200 );
        $data = $response->get_data();
        $this->assertEquals( 2, count( $data ) );
        $this->assertEquals( 1, $data[0]['id'] );
        $this->assertEquals( 2, $data[1]['id'] );
    }

    function test_get_item_not_found_if_entity_null() {
        $failing_mock_data_store = $this->getMockBuilder( 'MT_Interfaces_Data_Store' )
            ->setMethods( get_class_methods( 'MT_Interfaces_Data_Store' ) )
            ->getMock();

        $failing_mock_data_store->expects($this->any())
            ->method('get_entity')
            ->willReturn( null );
        $this->add_casette_rest_api_endpoint( $failing_mock_data_store );
        $response = $this->get( '/casette-crud-test/v1/casettes/1' );

        $this->assertNotNull( $response );
        $this->assertResponseStatus( $response, 404 );
    }

    function test_get_item_return_item() {
        $this->add_casette_rest_api_endpoint();
        $response = $this->get( '/casette-crud-test/v1/casettes/1' );

        $this->assertNotNull( $response );
        $this->assertResponseStatus( $response, 200 );
        $data = $response->get_data();
        $this->assertTrue( isset($data['id'] ) );
        $this->assertEquals( 1, $data['id'] );
    }

    function test_create_item_succeeds_when_data_store_returns_id() {
        $this->add_casette_rest_api_endpoint();
        $data = array(
			'title' => 'Awesome Mixtape 3',
			'songs' => array( 1, 2, 3, 4 )
		);

        $response = $this->post( '/casette-crud-test/v1/casettes', $data );

        $this->assertNotNull( $response );
        $this->assertResponseStatus( $response, 201 );
        $data = $response->get_data();
        $this->assertArrayHasKey( 'id', $data );
        $this->assertEquals( 3, $data['id'] );
    }

	function test_create_item_400_when_validation_error() {
		$this->add_casette_rest_api_endpoint();
		$data = array(
			'songs' => array( 1, 2, 3, 4 )
		);

		$response = $this->post( '/casette-crud-test/v1/casettes', $data );

		$this->assertNotNull( $response );
		$this->assertResponseStatus( $response, 400 );
	}

    function test_update_item_succeeds_when_data_store_returns_id() {
        $this->add_casette_rest_api_endpoint();
        $request = new WP_REST_Request( 'PUT', '/casette-crud-test/v1/casettes/1' );
        $request->set_param( 'title', 'Awesome Mixtape 666');

        $response = $this->rest_server->dispatch( $request );

        $this->assertNotNull( $response );
        // used to be 201 but turns out the correct thing on update is to HTTP 200
        $this->assert_http_response_status_success( $response );
        $data = $response->get_data();
        $this->assertTrue( isset($data['id'] ) );
        $this->assertEquals( 3, $data['id'] );
    }

    function test_delete_item_succeeds() {
        $this->add_casette_rest_api_endpoint();
        $request = new WP_REST_Request( 'DELETE', '/casette-crud-test/v1/casettes/1' );
        $request->set_param( 'title', 'Awesome Mixtape 666');

        $response = $this->rest_server->dispatch( $request );

        $this->assertNotNull( $response );
        // used to be 201 but turns out the correct thing on update is to HTTP 200
        $this->assert_http_response_status_success( $response );
    }

    private function add_casette_rest_api_endpoint( $data_store = null ) {
        $env = $this->mixtape->environment();

        $mock_data_store = !empty( $data_store ) ? $data_store : $this->build_mock_casette_data_store();
		$env->define_model( 'Casette' )->with_data_store( $mock_data_store );

        $bundle = $env->rest_api( 'casette-crud-test/v1' );

        $bundle->add_endpoint( new MT_Controller_CRUD( '/casettes', 'Casette' ) );
        $env->auto_start();

        do_action( 'rest_api_init' );
    }

    function build_mock_casette_data_store() {
		$env = $this->mixtape->environment();
		$env->define_model( 'Casette' );
        $this->casettes = array();
        $this->casettes[] = $env->model( 'Casette' )->create( array(
            'id' => 1,
            'title' => 'Awesome Mix Vol ' . 1,
            'songs' => array( 1, 2, 3 )
        ) );
        $this->casettes[] = $env->model( 'Casette' )->create( array(
            'id' => 2,
            'title' => 'Awesome Mix Vol ' . 2,
            'songs' => array( 1, 2, 3, 4 )
        ) );
        $mock = $this->getMockBuilder( 'MT_Interfaces_Data_Store' )
            ->setMethods( get_class_methods( 'MT_Interfaces_Data_Store' ) )
            ->getMock();
        $mock->expects($this->any())
            ->method('get_entities')
            ->willReturn( new MT_Model_Collection( $this->casettes ) );
        $mock->expects($this->any())
            ->method('get_entity')
            ->willReturn( $this->casettes[0] );
        $mock->expects($this->any())
            ->method('upsert')
            ->willReturn( 3 );
        $mock->expects($this->any())
            ->method('delete')
            ->willReturn( true );
        return $mock;
    }
}