<?php
namespace Skwirrel\Pim\Model\Import\Attribute;

/*
 *  Factory to get the attribute type
 */
class TypeFactory
{
    /**
     * @var array
     */
    private $cache = [];

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $objectManager;

    /**
     * @var \Magento\Framework\ObjectManagerInterface
     */
    private $types;

    /**
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param array $types
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        array $types
    ) {
        $this->objectManager = $objectManager;
        $this->types = $types;
    }

    /**
     * @param $type
     * @return \Skwirrel\Pim\Api\ImportAttributeTypeInterface
     */
    public function create($type)
    {
        if (!array_key_exists($type, $this->types)) {
            $type = 'default';
        }

        // Return created attribute adapter
        return $this->objectManager->create($this->types[$type]);
    }
}
