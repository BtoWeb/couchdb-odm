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
        $this->databaseName = $dm->getConfiguration()->getDatabaseName();
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

    public function putDocument(array $data, $id, $rev = null)
    {
        $data['_id'] = $id;
        if ($rev) {
            $data['_rev'] = $rev;
        }
        return $this->httpClient->request('PUT', '/' . $this->databaseName . '/' . $id , json_encode($data));
    }

    public function deleteDocument($id, $rev)
    {
        return $this->httpClient->request('DELETE', '/' . $this->databaseName . '/' . $id . '?rev=' . $rev);
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
        $uow = $this->dm->getUnitOfWork();

        $errors = array();
        foreach ($this->queuedInserts as $document) {
            $oid = spl_object_hash($document);
            $data = array();
            $class = $this->dm->getClassMetadata(get_class($document));
            foreach ($uow->getDocumentChangeSet($document) AS $k => $v) {
                $data[$class->properties[$k]['resultkey']] = $v;
            }
            $data['doctrine_metadata'] = array('type' => get_class($document));

            $rev = $uow->getDocumentRevision($document);
            if (isset($rev)) {
                $response = $this->putDocument($data, $uow->getDocumentIdentifier($document), $uow->getDocumentRevision($document));
            } else {
                $response = $this->postDocument($data);
            }

            if ( ($response->status === 200 || $response->status == 201) && $response->body['ok'] == true) {
                $this->documentRevisions[$oid] = $response->body['rev'];
            } else {
                $errors[] = $document;
            }
        }
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
     * @param id $criteria The criteria by which to load the document.
     * @param object $document The document to load the data into. If not specified,
     *        a new document is created.
     * @param $assoc The association that connects the document to load to another document, if any.
     * @param array $hints Hints for document creation.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     * @todo Check iddocument map? loadById method? Try to guess whether $criteria is the id?
     */
    public function load($criteria, $document = null, $assoc = null, array $hints = array())
    {
        try {
            // TODO add ability to handle other criteria as an array structure
            // like view support with view parameters and couchdb parameters (include_docs, limit, sort direction)
            $response = $this->findDocument($criteria);
            return $this->createDocument($response, $document, $hints);
        } catch(\Doctrine\ODM\CouchDB\DocumentNotFoundException $e) {
            return null;
        }
    }

    /**
     * Creates or fills a single document object from a result.
     *
     * @param $response The http response.
     * @param object $document The document object to fill, if any.
     * @param array $hints Hints for document creation.
     * @return object The filled and managed document object or NULL, if the result is empty.
     */
    private function createDocument($response, $document = null, array $hints = array())
    {
        if ($response->status > 400) {
            return null;
        }

        list($class, $data) = $this->processResponseBody($response->body);
        $hints = array('refresh' => true);

        return $this->dm->getUnitOfWork()->createDocument($class->name, $data, $response->body["_id"], $response->body["_rev"], $hints);
    }

    /**
     * Processes a response body that contains data for an document of the type
     * this persister is responsible for.
     *
     * Subclasses are supposed to override this method if they need to change the
     * hydration procedure for entities loaded through basic find operations or
     * lazy-loading (not DQL).
     *
     * @param array $responseBody The response body to process.
     * @return array A tuple where the first value is an instance of
     *               Doctrine\ODM\CouchDB\Mapping\ClassMetadata and the
     *              second value the prepared data of the document
     *              (a map from field names to values).
     */
    protected function processResponseBody(array $responseBody)
    {
        if (!isset($responseBody['doctrine_metadata'])) {
            throw new \InvalidArgumentException("Missing Doctrine metadata in the Document, cannot hydrate (yet)!");
        }
        $type = $responseBody['doctrine_metadata']['type'];
        $class = $this->dm->getClassMetadata($type);

        $data = array();
        foreach ($responseBody as $resultKey => $value) {
            // TODO: Check how ORM does this? Method or public property?
            if (isset($class->resultKeyProperties[$resultKey])) {
                $property = $class->resultKeyProperties[$resultKey];
                $data[$property] = $value;
            }
        }

        return array($class, $data);
    }

    /**
     * Loads an document of this persister's mapped class as part of a single-valued
     * association from another document.
     *
     * @param array $assoc The association to load.
     * @param object $sourcedocument The document that owns the association (not necessarily the "owning side").
     * @param object $targetdocument The existing ghost document (proxy) to load, if any.
     * @param array $identifier The identifier of the document to load. Must be provided if
     *                          the association to load represents the owning side, otherwise
     *                          the identifier is derived from the $sourcedocument.
     * @return object The loaded and managed document instance or NULL if the document can not be found.
     */
    public function loadOneToOneDocument(array $assoc, $sourcedocument, $targetdocument, array $identifier = array())
    {
        //TODO: implement
    }

    /**
     * Refreshes a managed document.
     *
     * @param array $id The identifier of the document as an associative array from
     *                  column or field names to values.
     * @param object $document The document to refresh.
     */
    public function refresh(array $id, $document)
    {
        //TODO: implement
    }

    /**
     * Loads a list of entities by a list of field criteria.
     *
     * @param array $criteria
     * @return array
     */
    public function loadAll(array $criteria = array())
    {
        //TODO: implement
    }

    /**
     * Checks whether the given managed document exists in the database.
     *
     * @param object $document
     * @return boolean TRUE if the document exists in the database, FALSE otherwise.
     */
    public function exists($document)
    {
        //TODO: implement
    }
}
