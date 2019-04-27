<?php
namespace Skwirrel\Pim\Model\Converter\Category;

use InvalidArgumentException;
use Magento\Framework\ObjectManagerInterface;

class ObjectFactory
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * Factory constructor
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     */
    public function __construct(ObjectManagerInterface $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param string $entityName
     * @throws \InvalidArgumentException
     * @return \Skwirrel\Pim\Model\Converter\Category\ObjectInterface
     */
    public function create($entityName = null)
    {
        $instance = $this->objectManager->create('\Skwirrel\Pim\Model\Converter\Category\ObjectInterface', ['entityName' => $entityName]);
        if (!$instance instanceof ObjectInterface) {
            throw new InvalidArgumentException(
                $entityName .
                ' is not instance of ObjectInterface'
            );
        }
        return $instance;
    }
}