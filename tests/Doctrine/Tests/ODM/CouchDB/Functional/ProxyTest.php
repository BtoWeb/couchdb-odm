<?php

namespace Doctrine\Tests\ODM\CouchDB\Functional;

class ProxyTest extends \Doctrine\Tests\ODM\CouchDB\CouchDBFunctionalTestCase
{
    /**
     * @var DocumentManager
     */
    private $dm;

    public function setUp()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);
        $this->assertEquals(201, $resp->status);

        $data = json_encode(array(
            '_id' => "1",
            'title' => 'foo',
            'body' => 'bar',
            'doctrine_metadata' => array('type' => 'Doctrine\Tests\ODM\CouchDB\Functional\Article'
        )));
        $resp = $httpClient->request('PUT', '/' . $database . '/1', $data);
        $this->assertEquals(201, $resp->status);

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setHttpClient($httpClient);
        $config->setDatabaseName($database);
        $config->setProxyDir(\sys_get_temp_dir());

        $this->dm = $config->newDocumentManager();

        $cmf = $this->dm->getClassMetadataFactory();
        $metadata = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\ODM\CouchDB\Functional\Article');
        $metadata->mapProperty(array('name' => 'id', 'type' => 'string', 'id' => true, 'resultkey' => '_id'));
        $metadata->mapProperty(array('name' => 'title', 'type' => 'string'));
        $metadata->mapProperty(array('name' => 'body', 'type' => 'string'));
        $metadata->idGenerator = \Doctrine\ODM\CouchDB\Mapping\ClassMetadata::IDGENERATOR_UUID;
        $cmf->setMetadataFor($metadata);
    }

    public function testGetReference()
    {
        $proxy = $this->dm->getReference('Doctrine\Tests\ODM\CouchDB\Functional\Article', 1);

        $this->assertType('Doctrine\ODM\CouchDB\Proxy\Proxy', $proxy);
        $this->assertFalse($proxy->__isInitialized__);

        $this->assertEquals('foo', $proxy->getTitle());
        $this->assertTrue($proxy->__isInitialized__);
        $this->assertEquals('bar', $proxy->getBody());
    }
}

class Article
{
    private $id;
    private $title;
    private $body;

    public function __construct($title, $body)
    {
        $this->title = $title;
        $this->body = $body;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function getBody()
    {
        return $this->body;
    }
}
