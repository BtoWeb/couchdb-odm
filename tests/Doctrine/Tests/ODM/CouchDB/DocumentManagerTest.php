<?php

namespace Doctrine\Tests\ODM\CouchDB;

class DocumentManagerTest extends CouchDBTestCase
{
    public function testNewInstanceFromConfiguration()
    {
        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();
        $config->setHttpClient($httpClient);
        
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($config);

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\DocumentManager', $dm);
        $this->assertSame($config, $dm->getConfiguration());
        $this->assertSame($httpClient, $dm->getConfiguration()->getHttpClient());
    }

    public function testGetClassMetadataFactory()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create();

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory', $dm->getClassMetadataFactory());
    }

    public function testGetClassMetadataFor()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create();

        $cmf = $dm->getClassMetadataFactory();
        $cmf->setMetadataFor('stdClass', new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('stdClass'));

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\Mapping\ClassMetadata', $dm->getClassMetadata('stdClass'));
    }

    public function testCreateNewDocumentManagerWithoutHttpClientUsingSocketDefault()
    {
        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create();

        $this->assertInstanceOf('Doctrine\ODM\CouchDB\HTTP\SocketClient', $dm->getConfiguration()->getHttpClient());
    }
}