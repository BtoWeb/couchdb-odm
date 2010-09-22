<?php

namespace Doctrine\Tests\ODM\CouchDB;

abstract class CouchDBFunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private $httpClient = null;

    private $useModelSet = null;

    public function useModelSet($name)
    {
        $this->useModelSet = $name;
    }

    /**
     * @return \Doctrine\ODM\CouchDB\HTTP\Client
     */
    public function getHttpClient()
    {
        if ($this->httpClient === null) {
            $this->httpClient = new \Doctrine\ODM\CouchDB\HTTP\SocketClient();
        }
        return $this->httpClient;
    }

    public function getTestDatabase()
    {
        return TestUtil::getTestDatabase();
    }

    public function createDocumentManager()
    {
        $database = $this->getTestDatabase();
        $httpClient = $this->getHttpClient();

        $httpClient->request('DELETE', '/' . $database);
        $resp = $httpClient->request('PUT', '/' . $database);

        $config = new \Doctrine\ODM\CouchDB\Configuration();
        $config->setDefaultDB($database);
        $config->setProxyDir(\sys_get_temp_dir());

        $dm = \Doctrine\ODM\CouchDB\DocumentManager::create($httpClient, $config);

        $cmf = $dm->getClassMetadataFactory();
        if ($this->useModelSet == 'cms') {
            $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\Models\CMS\CmsUser');
            $cm->mapField(array('name' => 'id', 'id' => true));
            $cm->mapField(array('name' => 'username'));
            $cm->mapField(array('name' => 'name'));
            $cm->mapField(array('name' => 'status'));
            $cmf->setMetadataFor('Doctrine\Tests\Models\CMS\CmsUser', $cm);

            $cm = new \Doctrine\ODM\CouchDB\Mapping\ClassMetadata('Doctrine\Tests\Models\CMS\CmsArticle');
            $cm->mapField(array('name' => 'id', 'id' => true));
            $cm->mapField(array('name' => 'topic'));
            $cm->mapField(array('name' => 'text'));
            $cm->mapManyToOne(array('name' => 'user', 'targetDocument' => 'Doctrine\Tests\Models\CMS\CmsUser'));
            $cmf->setMetadataFor('Doctrine\Tests\Models\CMS\CmsArticle', $cm);
        }

        return $dm;
    }
}