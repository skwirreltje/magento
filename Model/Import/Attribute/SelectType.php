<?php
/**
 * Created by PhpStorm.
 * User: robtheeuwes
 * Date: 17-4-19
 * Time: 22:58
 */

namespace Skwirrel\Pim\Model\Import\Attribute;


class SelectType extends AbstractType
{

    protected $frontend = 'select';
    protected $type  = 'int';
    protected $global = \Magento\Catalog\Model\ResourceModel\Eav\Attribute::SCOPE_GLOBAL;
    protected $source = \Magento\Eav\Model\Entity\Attribute\Source\Table::class;
    protected $isConfigurable = true;

}