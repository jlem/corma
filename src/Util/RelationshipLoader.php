<?php
namespace Corma\Util;

use Corma\DataObject\DataObject;
use Corma\DataObject\DataObjectInterface;
use Corma\Exception\MethodNotImplementedException;
use Corma\ObjectMapper;
use Doctrine\Common\Inflector\Inflector;

class RelationshipLoader
{
    /**
     * @var ObjectMapper
     */
    private $objectMapper;

    public function __construct(ObjectMapper $objectMapper)
    {
        $this->objectMapper = $objectMapper;
    }

    /**
     * Loads a foreign relationship where a property on the supplied objects references an id for another object
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign object to load
     * @param string $foreignIdColumn Property on this object that relates to the foreign tables id
     */
    public function loadOneToMany(array $objects, $className, $foreignIdColumn)
    {
        if(empty($objects)) {
            return;
        }

        $idToForeignId = [];
        $foreignIdColumn = ucfirst($foreignIdColumn);
        $getter = 'get' . $foreignIdColumn;
        foreach($objects as $object) {
            if(method_exists($object, $getter)) {
                $idToForeignId[$object->getId()] = $object->$getter();
            } else {
                throw new MethodNotImplementedException("$getter must be defined on {$object->getClassName()} to load oneToMany relationship with $className");
            }
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_unique(array_values($idToForeignId)));
        $foreignObjectsById = [];
        foreach($foreignObjects as $foreignObject) {
            $foreignObjectsById[$foreignObject->getId()] = $foreignObject;
        }
        unset($foreignObjects);

        $setter = 'set' . $this->methodNameFromColumn($foreignIdColumn);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                $object->$setter($foreignObjectsById[$idToForeignId[$object->getId()]]);
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load oneToMany relationship at $className");
            }
        }
    }

    /**
     * Loads a foreign relationship where a column on another object references the id for the supplied object
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $foreignColumn Property on foreign object that relates to this object id
     */
    public function loadManyToOne(array $objects, $className, $foreignColumn)
    {
        if(empty($objects)) {
            return;
        }

        $ids = DataObject::getIds($objects);
        $foreignObjects = $this->objectMapper->findBy($className, [$foreignColumn=>$ids]);
        $foreignObjectsById = [];
        $getter = 'get' . ucfirst($foreignColumn);
        foreach($foreignObjects as $foreignObject) {
            if(method_exists($foreignObject, $getter)) {
                $id = $foreignObject->$getter();
                $foreignObjectsById[$id][] = $foreignObject;
            } else {
                throw new MethodNotImplementedException("$getter must be defined on $className to load many-to-one relationship with {$foreignObject->getClassName()}");
            }
        }

        $setter = 'set' . $this->methodNameFromClass($className, true);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                $object->$setter($foreignObjectsById[$object->getId()]);
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load many-to-one relationship with $className");
            }
        }
    }

    /**
     * Loads objects of the foreign class onto the supplied objects linked by a link table containing the id's of both objects
     *
     * @param DataObjectInterface[] $objects
     * @param string $className Class name of foreign objects to load
     * @param string $linkTable Table that links two objects together
     * @param string $idColumn Column on link table = the id on this object
     * @param string $foreignIdColumn Column on link table = the id on the foreign object table
     */
    public function loadManyToMany(array $objects, $className, $linkTable, $idColumn = null, $foreignIdColumn = null)
    {
        if(empty($objects)) {
            return;
        }

        $ids = DataObject::getIds($objects);
        $qb = $this->objectMapper->getQueryHelper()
            ->buildSelectQuery($linkTable, [$idColumn.' AS id', $foreignIdColumn.' AS foreignId'], [$idColumn=>$ids]);
        $foreignIdsById = [];
        $foreignIds = [];
        $linkRows = $qb->execute();
        $linkRows->setFetchMode(\PDO::FETCH_OBJ);
        foreach($linkRows as $linkRow) {
            $foreignIdsById[$linkRow->id][] = $linkRow->foreignId;
            $foreignIds[$linkRow->foreignId] = true;
        }

        $foreignObjects = $this->objectMapper->findByIds($className, array_keys($foreignIds));
        unset($foreignIds);

        $foreignObjectsById = [];
        foreach($foreignObjects as $foreignObject) {
            $foreignObjectsById[$foreignObject->getId()] = $foreignObject;
        }
        unset($foreignObjects);

        $setter =  'set' . $this->methodNameFromColumn($foreignIdColumn, true);
        foreach($objects as $object) {
            if(method_exists($object, $setter)) {
                $foreignIds = $foreignIdsById[$object->getId()];
                $foreignObjects = [];
                foreach($foreignIds as $foreignId) {
                    $foreignObjects[] = $foreignObjectsById[$foreignId];
                }
                $object->$setter($foreignObjects);
            } else {
                throw new MethodNotImplementedException("$setter must be defined on {$object->getClassName()} to load many-to-many relationship with $className");
            }
        }
    }

    /**
     * @param string $columnName
     * @param bool $plural
     * @return string Partial method name to get / set object(s)
     */
    protected function methodNameFromColumn($columnName, $plural = false)
    {
        $method = ucfirst(str_replace(['Id', '_id'], '', $columnName));
        if($plural) {
            return Inflector::pluralize($method);
        } else {
            return $method;
        }
    }

    /**
     * @param string $className
     * @param bool $plural
     * @return string Partial method name to get / set object(s)
     */
    protected function methodNameFromClass($className, $plural = false)
    {
        $method = substr($className, strrpos($className, '\\') + 1);
        if($plural) {
            return Inflector::pluralize($method);
        } else {
            return $method;
        }
    }
}
