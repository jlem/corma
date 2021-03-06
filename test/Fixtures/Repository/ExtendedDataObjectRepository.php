<?php
namespace Corma\Test\Fixtures\Repository;

use Corma\Repository\ObjectRepository;

class ExtendedDataObjectRepository extends ObjectRepository
{
    public function causeUniqueConstraintViolation()
    {
        $this->db->insert($this->getTableName(), ['id'=>999, 'myColumn'=>'value']);
        $this->db->insert($this->getTableName(), ['id'=>999, 'myColumn'=>'value']);
    }
}