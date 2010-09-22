<?php

namespace Doctrine\ODM\CouchDB;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadataFactory;
use Doctrine\ODM\CouchDB\HTTP\Client;

class DocumentManager
{
    /**
     * @var Configuration
     */
    private $config;

    /**
     * @var Mapping\ClassMetadataFactory
     */
    private $metadataFactory;

    /**
     * @var UnitOfWork
     */
    private $unitOfWork = null;

    private $proxyFactory = null;

    public function __construct(Client $httpClient = null, Configuration $config = null)
    {
        $this->config = $config ? $config : new Configuration();
        if ($httpClient) {
            $this->config->setHttpClient($httpClient);
        }
        $this->metadataFactory = new ClassMetadataFactory($this);
        $this->unitOfWork = new UnitOfWork($this);
        // TODO: Add configuration!
        $this->proxyFactory = new Proxy\ProxyFactory($this, $this->config->getProxyDir(), 'MyProxies', true);
    }

    /**
     * Creates a new Document that operates on the given Mongo connection
     * and uses the given Configuration.
     *
     * @param Doctrine\ODM\CouchDB\HTTP\Client
     * @param Doctrine\ODM\CouchDB\Configuration $config
     */
    public static function create(Client $httpClient = null, Configuration $config = null)
    {
        return new DocumentManager($httpClient, $config);
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @return Configuration
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * @return ClassMetadataFactory
     */
    public function getClassMetadataFactory()
    {
        return $this->metadataFactory;
    }

    /**
     * @param  string $class
     * @return ClassMetadata
     */
    public function getClassMetadata($class)
    {
        return $this->metadataFactory->getMetadataFor($class);
    }

    public function find($type, $id)
    {
        return $this->unitOfWork->getDocumentPersister()->load(array('documentName' => $type, 'id' => $id));
    }

    public function persist($object)
    {
        $this->unitOfWork->scheduleInsert($object);
    }

    public function remove($object)
    {
        $this->unitOfWork->scheduleRemove($object);
    }

    /**
     * Gets a reference to the entity identified by the given type and identifier
     * without actually loading it, if the entity is not yet loaded.
     *
     * @param string $documentName The name of the entity type.
     * @param mixed $identifier The entity identifier.
     * @return object The entity reference.
     */
    public function getReference($documentName, $identifier)
    {
        $class = $this->metadataFactory->getMetadataFor(ltrim($documentName, '\\'));

        // Check identity map first, if its already in there just return it.
        if ($document = $this->unitOfWork->tryGetById($identifier)) {
            return $document;
        }
        $document = $this->proxyFactory->getProxy($class->name, $identifier);
        $this->unitOfWork->registerManaged($document, $identifier, null);

        return $document;
    }

    public function flush()
    {
        $this->unitOfWork->flush(); // todo: rename commit
    }

    /**
     * @return UnitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    public function clear()
    {
        // Todo: Do a real delegated clear?
        $this->unitOfWork = new UnitOfWork($this);
    }
}
