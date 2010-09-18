<?php

namespace Doctrine\Tests\ODM\CouchDB;

use Doctrine\ODM\CouchDB\HTTP\SocketClient;
use Doctrine\ODM\CouchDB\View\Relations;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class RelationsTest extends CouchDBTestCase
{
    private $dm;
    private $uow;

    public function setUp()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setHttpClient( new SocketClient() );
        $this->dm = $config->newDocumentManager();
    }

    protected function addTestData()
    {
        $db = $this->dm->getConfiguration()->getHttpClient();

        // Force empty test database
        try {
            $db->request( 'DELETE', '/doctrine_odm_test' );
        } catch ( \Exception $e ) { /* Irrelevant exception */ }
        $db->request( 'PUT', '/doctrine_odm_test' );

        // Create some "interesting" documents
        $response = $db->request( 'PUT', '/doctrine_odm_test/doc_a', json_encode( array(
            "_id" => "doc_a",
            "doctrine_metadata" => array(
                "type" => "type_a",
                "relations" => array(
                    "type_b" => array( "doc_b" ),
                    "type_c" => array( "doc_d" ),
                ),
            ),
        ) ) );
        $response = $db->request( 'PUT', '/doctrine_odm_test/doc_b', json_encode( array(
            "_id" => "doc_b",
            "doctrine_metadata" => array(
                "type" => "type_b",
                "relations" => array(
                    "type_c" => array( "doc_c", "doc_d" ),
                ),
            ),
        ) ) );
        $response = $db->request( 'PUT', '/doctrine_odm_test/doc_c', json_encode( array(
            "_id" => "doc_c",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "relations" => array(),
            ),
        ) ) );
        $response = $db->request( 'PUT', '/doctrine_odm_test/doc_d', json_encode( array(
            "_id" => "doc_d",
            "doctrine_metadata" => array(
                "type" => "type_c",
                "relations" => array(),
            ),
        ) ) );
    }

    public function testCreateView()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_b"
                ),
            ),
            $view->getRelatedObjects( "doc_a", "type_b" )
        );
    }

    public function testRefetchView()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_c"
                ),
                array(
                    "_id" => "doc_d"
                ),
            ),
            $view->getRelatedObjects( "doc_b", "type_c" )
        );
    }

    public function testFetchReverseRelations()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(
                array(
                    "_id" => "doc_a"
                ),
            ),
            $view->getReverseRelatedObjects( "doc_d", "type_a" )
        );
    }

    public function testFetchNoReverseRelations()
    {
        $this->addTestData();

        $view = new Relations( $this->dm, 'doctrine' );
        $this->assertEquals(
            array(),
            $view->getReverseRelatedObjects( "doc_c", "type_a" )
        );
    }
}

