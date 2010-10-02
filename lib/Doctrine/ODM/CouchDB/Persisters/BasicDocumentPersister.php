<?php

namespace Doctrine\ODM\CouchDB\Persisters;

use Doctrine\ODM\CouchDB\DocumentManager;
use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class BasicDocumentPersister
{
    /**
     * Name of the CouchDB database
     *
     * @string
     */
    private $databaseName;

    /**
     * The underlying HTTP Connection of the used DocumentManager.
     *
     * @var Doctrine\ODM\CouchDB\HTTP\Client
     */
    private $httpClient;

    /**
     * The documentManager instance.
     *
     * @var Doctrine\ODM\CouchDB\DocumentManager
     */
    private $dm = null;

    /**
     * Queued inserts.
     *
     * @var array
     */
    protected $queuedInserts = array();

    public function __construct(DocumentManager $dm)
    {
        $this->dm = $dm;
        // TODO how to handle the case when the database name changes?
        $this->databaseName = $dm->getConfiguration()->getDatabase();
        $this->httpClient = $dm->getConfiguration()->getHttpClient();
    }

    public function getUuids($count = 1)
    {
        $count = (int)$count;
        $response = $this->httpClient->request('GET', '/_uuids?count=' . $count);

        if ($response->status != 200) {
            throw new \Doctrine\ODM\CouchDB\CouchDBException("Could not retrieve UUIDs from CouchDB.");
        }

        return $response->body['uuids'];
    }

    /**
     * @param  string $id
     * @return Response
     */
    public function findDocument($id)
    {
        $documentPath = '/' . $this->databaseName . '/' . urlencode($id);
        $response =  $this->httpClient->request( 'GET', $documentPath );

        if ($response->status == 404) {
            throw new \Doctrine\ODM\CouchDB\DocumentNotFoundException($id);
        }
        return $response;
    }

    /**
     * @param array $data
     * @return Response
     */
    public function postDocument(array $data)
    {
        return $this->httpClient->request('POST', '/' . $this->databaseName , json_encode($data));
    }

    public function putDocument(array $data, $rev = null)
    {
        if ($rev) {
            $data['_rev'] = $rev;
        }
        return $this->httpClient->request('PUT', '/' . $this->databaseName . '/' . $data['_id'] , json_encode($data));
    }

    public function deleteDocument($id, $rev)
    {
        return $this->httpClient->request('DELETE', '/' . $this->databaseName . '/' . $id. '?rev=' . $rev);
    }

    /**
     * Adds an document to the queued insertions.
     * The document remains queued until {@link executeInserts} is invoked.
     *
     * @param object $document The document to queue for insertion.
     */
    public function addInsert($document)
    {
        $this->queuedInserts[spl_object_hash($document)] = $document;
    }

    /**
     * Executes all queued document insertions and returns any generated post-insert
     * identifiers that were created as a result of the insertions.
     *
     * If no inserts are queued, invoking this method is a NOOP.
     *
     * @return array An array of any generated post-insert IDs. This will be an empty array
     *               if the document class does not use the IDdocument generation strategy.
     */
    public function executeInserts()
    {
        if ( ! $this->queuedInserts) {
            return;
        }

        $uow = $this->dm->getUnitOfWork();

        $errors = array();
        foreach ($this->queuedInserts as $oid => $document) {
            $data = array();
            $class = $this->dm->getClassMetadata(get_class($document));

            // Convert field values to json values.
            foreach ($uow->getDocumentChangeSet($document) AS $fieldName => $fieldValue) {
                if (isset($class->fieldMappings[$fieldName])) {
                    $data[$class->fieldMappings[$fieldName]['jsonName']] = $fieldValue;
                } else if (isset($class->associationsMappings[$fieldName])) {
                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if (\is_object($fieldValue)) {
                            $data[$fieldName] = $uow->getDocumentIdentifier($fieldValue);
                        } else {
                            $data[$fieldName] = null;
                        }
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // TODO: Optimize when not initialized yet!
                            $ids = array();
                            foreach ($fieldValue AS $relatedObject) {
                                $ids[] = $uow->getDocumentIdentifier($relatedObject);
                            }

                            $data[$fieldName] = $ids;
                        }
                    }
                }
            }
            // TODO add metadata writing disabled support
            $data['doctrine_metadata'] = array('type' => get_class($document));

            $rev = $uow->getDocumentRevision($document);
            if ($rev) {
                $response = $this->putDocument($data, $rev);
            } else {
                $response = $this->postDocument($data);
            }

            if ( ($response->status === 200 || $response->status == 201) && $response->body['ok'] == true) {
                $uow->setDocumentRevision($oid, $response->body['rev']);
                if ($class->isVersioned) {
                    $class->reflFields[$class->versionField]->setValue($document, $response->body['rev']);
                }
            } else {
                $errors[] = $response->body;
            }
        }

        $this->queuedInserts = array();

        return $errors;
    }

    /**
     * Deletes a managed document.
     *
     * The document to delete must be managed and have a persistent identifier.
     * The deletion happens instantaneously.
     *
     * Subclasses may override this method to customize the semantics of document deletion.
     *
     * @param object $document The document to delete.
     */
    public function delete($document)
    {
        $uow = $this->dm->getUnitOfWork();
        $this->deleteDocument($uow->getDocumentIdentifier($document), $uow->getDocumentRevision($document));
    }

    /**
     * Loads an document by a list of field criteria.
     *
     * @param array $query The criteria by which to load the document.
     * @param object $document The document to load the data into. If not specified,
     *        a new document is created.
     * @param $assoc The association that connects the document to load to another document, if any.
     * @param array $hints Hints for document creation.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     * @todo Check iddocument map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load(array $query, $document = null, $assoc = null, array $hints = array())
    {
        try {
            // TODO define a proper query array structure
            // view support with view parameters and couchdb parameters (include_docs, limit, sort direction)
            $response = $this->findDocument($query['id']);
            if ($response->status > 400) {
                return null;
            }

            return $this->createDocument($query['documentName'], $response->body, $document, $hints);
        } catch(\Doctrine\ODM\CouchDB\DocumentNotFoundException $e) {
            return null;
        }
    }

    /**
     * Load many documents of a type by mass fetching them from the _all_docs document.
     *
     * @param  string $documentName
     * @param  array $ids
     * @return array
     */
    public function loadMany($documentName, $ids)
    {
        $allDocsPath = '/' . $this->databaseName . '/_all_docs?include_docs=true';
        $response = $this->httpClient->request('POST', $allDocsPath, json_encode(array('keys' => $ids)));

        if ($response->status != 200) {
            throw new \Exception("loadMany error code " . $response->status);
        }

        $docs = array();
        if ($response->body['total_rows'] > 0) {
            foreach ($response->body['rows'] AS $responseData) {
                $docs[] = $this->createDocument($documentName, $responseData['doc']);
            }
        }
        return $docs;
    }

    /**
     * Creates or fills a single document object from a result.
     *
     * @param string $documentName
     * @param array $responseData The http response.
     * @param object $document The document object to fill, if any.
     * @param array $hints Hints for document creation.
     * @return object The filled and managed document object or NULL, if the result is empty.
     */
    private function createDocument($documentName, $responseData, $document = null, array $hints = array())
    {
        if (isset($responseData['doctrine_metadata'])) {
            $type = $responseData['doctrine_metadata']['type'];
            if(isset($documentName)) {
                // TODO add (optional?) type validation
            }
        } elseif(isset($documentName)) {
            $type = $documentName;
            // TODO automatically add metadata if metadata writing is not disabled
        } else {
            throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }

        $class = $this->dm->getClassMetadata($type);

        $data = array();
        foreach ($responseData as $jsonName => $value) {
            // TODO: For migrations and stuff, maybe there should really be a "rest" field?
            if (isset($class->jsonNames[$jsonName])) {
                $fieldName = $class->jsonNames[$jsonName];
                if (isset($class->fieldMappings[$fieldName])) {
                    $data[$class->fieldMappings[$fieldName]['fieldName']] = $value;
                } else if (isset($class->associationsMappings[$fieldName])) {

                    if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::TO_ONE) {
                        if ($value) {
                            $value = $this->dm->getReference($class->associationsMappings[$fieldName]['targetDocument'], $value);
                        }
                        $data[$class->associationsMappings[$fieldName]['fieldName']] = $value;
                    } else if ($class->associationsMappings[$fieldName]['type'] & ClassMetadata::MANY_TO_MANY) {
                        if ($class->associationsMappings[$fieldName]['isOwning']) {
                            // 1. if owning side we know all the ids
                            $data[$class->associationsMappings[$fieldName]['fieldName']] = new \Doctrine\ODM\CouchDB\PersistentIdsCollection(
                                new \Doctrine\Common\Collections\ArrayCollection(),
                                $class->associationsMappings[$fieldName]['targetDocument'],
                                $this->dm,
                                $value
                            );
                        } else {
                            // 2. if inverse side we need to nest the lazy loading relations view
                        }
                    }
                }
            }
        }
        
        $hints = array('refresh' => true);

        return $this->dm->getUnitOfWork()->createDocument($class->name, $data, $responseData["_id"], $responseData["_rev"], $hints);
    }
}
