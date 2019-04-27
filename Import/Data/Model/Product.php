<?php
namespace Skwirrel\Pim\Import\Data\Model;

use Magento\Framework\DataObject;

class Product extends DataObject
{

    public function getAttributes(){

        return $this->getData('attributes');
    }
}