<?php
namespace Skwirrel\Pim\Model\Converter\Category;


class Object implements ObjectInterface
{
    public $id;
    public $parentId;
    public $name;
    public $translations = [];
    public $children = [];
    public $additionalData= [];
    /**
     * @var null
     */
    private $entityName;

    public function __construct($entityName = null)
    {
        $this->entityName = $entityName;
    }

    public function getEntityName()
    {
        return $this->entityName;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }


    public function isActive()
    {
        return true;
    }

    /**
     * Get URL key identifier for category
     *
     * @return string
     */
    public function getUrlKey()
    {
        return $this->name;
    }

    /**
     * @return mixed
     */
    public function getParentId()
    {
        return $this->parentId;
    }

    /**
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getTranslations()
    {
        return $this->translations;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param mixed $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @param mixed $parentId
     */
    public function setParentId($parentId)
    {
        $this->parentId = $parentId;
    }

    /**
     * @param mixed $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @param array $translations
     */
    public function setTranslations($translations)
    {
        $this->translations = $translations;
    }

    /**
     * @param array $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return array
     */
    public function getAdditionalData()
    {
        return $this->additionalData;
    }


}