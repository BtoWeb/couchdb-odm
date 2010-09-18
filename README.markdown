# CouchDB ODM

# Hackaton Discussions: CouchDB + Doctrine

##Problem 1: Query API

* Views
* CouchDB-Lucene hidden transparently in the Background
** Allows a dynamic query language to be implemented
** Depending on the search backend (query language translator)

##Problem 2: Lazy Loading

How to implement object-graph traversal transparently?

* two views required, because bi-directional relationships?
* emit([doc.type, doc.field, doc._id], 0); (triple)

We need some matadata to be stored in doctrine couchdb odm documents: "type", "relations"

## Problem 3: Joins

2 possibilities: embedded, with ids

"Foreign Keys":
* one-to-many: save one key-reference in each "many"-document
* many-to-many: save ids in the owning-side document
* one-to-one: maybe good use-case for embedded documents

## Problem 4: Embedded Documents Use-Case?

Value objects (Color example)

## Problem 5: Computed Values from View

## Problem 6: @DynamicFields

Just have mapping type "array".

    class Address
    {
        /** @var array
        public $additional = array();
    }

## Problem 7: "Eventual Migration" / Liberal Reads

MongoODM has solution for that

## Problem 8: Write/Flushing changes

* Conflict Management throws an Exception into the users face :)

## Problem 9: Attachments

Easily lazyloaded by resource handle or "transparent" proxy

## Problem 10: HTTP Client

* Should be interfaced
* Different implementations: Socket, Stream Wrapper, pecl/http

## Problem 11: Objects without "Doctrine Metadata"

* Eventual migration possibilities for this case should be possible

## Problem 12: ID Generation

* Assigned IDs (Username for the User)
* Unique Constraints
* UUID (Generate IDs upfront possible)

# Requirements

1. type of the document in each couchdb "doc" (
2. Expose revision to the user!!!
3. metadata field in each "doctrine handled" document

## Struct

    {
        "_id": "asbaklsjdfksjddf",
        "__doctrine": {
            "type": "foo",
            "relations" : {
                "bar": [ "id1", "id2" ], // M:N
             }
        },
        "fieldA": "foobar"
        "embeddedA": [{...}, {...}]
    }

    class User
    {
        /** @Id @Field */
        public $username;
    }
