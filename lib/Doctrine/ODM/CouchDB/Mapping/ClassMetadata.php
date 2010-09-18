<?php

namespace Doctrine\ODM\CouchDB\Mapping;

class ClassMetadata
{
    const IDGENERATOR_UUID = 1;
    const IDGENERATOR_ASSIGNED = 2;

    public $name;

    public $idGenerator = self::IDGENERATOR_ASSIGNED;

    public $properties = array();

    public $reflClass = null;
    public $reflProps = array();

    public $identifier = array();

    public $prototype = null;

    public function __construct($name)
    {
        $this->name = $name;
        $this->reflClass = new \ReflectionClass($name);
    }

    public function mapProperty($mapping)
    {
        if (!isset($mapping['type'])) {
            $mapping['type'] = "string";
        }
        $this->properties[$mapping['name']] = $mapping;

        if (isset($mapping['id'])) {
            $this->identifier[] = $mapping['name'];
        }

        $this->reflProps[$mapping['name']] = $this->reflClass->getProperty($mapping['name']);
        $this->reflProps[$mapping['name']]->setAccessible(true);
    }

    public function newInstance()
    {
        if ($this->prototype === null) {
            $this->prototype = unserialize(
                sprintf(
                    'O:%d:"%s":0:{}',
                    strlen($this->name),
                    $this->name
                )
            );
        }
        return clone $this->prototype;
    }

    /**
     * Extracts the identifier values of an entity of this class.
     *
     * For composite identifiers, the identifier values are returned as an array
     * with the same order as the field order in {@link identifier}.
     *
     * @param object $doc
     * @return array
     */
    public function getIdentifierValues($doc)
    {
        $id = array();
        foreach ($this->identifier as $idProperty) {
            $value = $this->reflProps[$idProperty]->getValue($doc);
            if ($value !== null) {
                $id[$idProperty] = $value;
            }
        }
        return $id;
    }
}