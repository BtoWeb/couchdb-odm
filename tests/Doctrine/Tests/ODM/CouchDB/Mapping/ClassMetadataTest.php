<?php

namespace Doctrine\Tests\ODM\CouchDB\Mapping;

use Doctrine\ODM\CouchDB\Mapping\ClassMetadata;

class ClassMetadataTest extends \PHPUnit_Framework_Testcase
{
    public function testClassName()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");

        $this->assertEquals("Doctrine\Tests\ODM\CouchDB\Mapping\Person", $cm->name);
        $this->assertType('ReflectionClass', $cm->reflClass);

        return $cm;
    }

    /**
     * @depends testClassName
     */
    public function testMapFieldWithId($cm)
    {
        $cm->mapField(array('fieldName' => 'id', 'id' => true));

        $this->assertTrue(isset($cm->fieldMappings['id']));
        $this->assertEquals(array('jsonName' => '_id', 'id' => true, 'type' => 'string', 'fieldName' => 'id'), $cm->fieldMappings['id']);

        $this->assertEquals('id', $cm->identifier);
        $this->assertEquals(array('_id' => 'id'), $cm->jsonNames);

        return $cm;
    }

    /**
     * @depends testMapFieldWithId
     */
    public function testmapField($cm)
    {
        $cm->mapField(array('fieldName' => 'username', 'type' => 'string'));
        $cm->mapField(array('fieldName' => 'created', 'type' => 'datetime'));

        $this->assertTrue(isset($cm->fieldMappings['username']));
        $this->assertTrue(isset($cm->fieldMappings['created']));

        $this->assertEquals(array('jsonName' => 'username', 'type' => 'string', 'fieldName' => 'username'), $cm->fieldMappings['username']);
        $this->assertEquals(array('jsonName' => 'created', 'type' => 'datetime', 'fieldName' => 'created'), $cm->fieldMappings['created']);

        $this->assertEquals(array('_id' => 'id', 'username' => 'username', 'created' => 'created'), $cm->jsonNames);

        return $cm;
    }

    /**
     * @depends testmapField
     */
    public function testmapFieldWithoutNameThrowsException($cm)
    {
        $this->setExpectedException('Doctrine\ODM\CouchDB\Mapping\MappingException');

        $cm->mapField(array());
    }

    /**
     * @depends testmapField
     */
    public function testReflectionProperties($cm)
    {
        $this->assertType('ReflectionProperty', $cm->reflFields['username']);
        $this->assertType('ReflectionProperty', $cm->reflFields['created']);
    }
    
    /**
     * @depends testmapField
     */
    public function testNewInstance($cm)
    {
        $instance1 = $cm->newInstance();
        $instance2 = $cm->newInstance();

        $this->assertType('Doctrine\Tests\ODM\CouchDB\Mapping\Person', $instance1);
        $this->assertNotSame($instance1, $instance2);
    }

    /**
     * @depends testmapField
     */
    public function testMapVersionField($cm)
    {
        $this->assertFalse($cm->isVersioned);
        $cm->mapField(array('fieldName' => 'version', 'jsonName' => '_rev', 'isVersionField' => true));

        $this->assertTrue($cm->isVersioned);
        $this->assertEquals('version', $cm->versionField);
    }

    public function testmapFieldWithoutType_DefaultsToString()
    {
        $cm = new ClassMetadata("Doctrine\Tests\ODM\CouchDB\Mapping\Person");

        $cm->mapField(array('fieldName' => 'username'));

        $this->assertEquals(array('jsonName' => 'username', 'type' => 'string', 'fieldName' => 'username'), $cm->fieldMappings['username']);
    }

    /**
     * @param ClassMetadata $cm
     * @depends testClassName
     */
    public function testMapAssociationManyToOne($cm)
    {
        $cm->mapManyToOne(array('fieldName' => 'address', 'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address'));

        $this->assertTrue(isset($cm->associationsMappings['address']), "No 'address' in associations map.");
        $this->assertEquals(array(
            'fieldName' => 'address',
            'targetDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Address',
            'jsonName' => 'address',
            'sourceDocument' => 'Doctrine\Tests\ODM\CouchDB\Mapping\Person',
            'isOwning' => true,
            'type' => ClassMetadata::MANY_TO_ONE,
        ), $cm->associationsMappings['address']);

        $this->assertArrayHasKey('address', $cm->jsonNames);
        $this->assertEquals('address', $cm->jsonNames['address']);

        return $cm;
    }

    /**
     * @param ClassMetadata $cm
     * @depends testClassName
     */
    public function testMapAttachments($cm)
    {
        $cm->mapAttachments("attachments");

        $this->assertTrue($cm->hasAttachments);
        $this->assertEquals("attachments", $cm->attachmentField);
        $this->assertArrayHasKey("attachments", $cm->reflFields);
    }
}

class Person
{
    public $id;

    public $username;

    public $created;

    public $address;

    public $version;

    public $attachments;
}

class Address
{
    public $id;
}