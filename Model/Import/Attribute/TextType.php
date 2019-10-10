<?php
/**
 * Created by PhpStorm.
 * User: robtheeuwes
 * Date: 17-4-19
 * Time: 22:58
 */

namespace Skwirrel\Pim\Model\Import\Attribute;


class TextType extends AbstractType
{

    protected $type = 'text';
    protected $frontend = 'textarea';
}