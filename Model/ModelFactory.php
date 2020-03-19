<?php

namespace Skwirrel\Pim\Model;


use InvalidArgumentException;
use Skwirrel\Pim\Api\ImportInterface;
use Magento\Framework\ObjectManagerInterface;

class ModelFactory
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
     * @param string $instanceName
     * @param array $data
     * @throws \InvalidArgumentException
     * @return \Skwirrel\Pim\Api\ImportInterface
     */
    public function create($instanceName, array $data = [])
    {
        /** @var \Skwirrel\Pim\Api\ImportInterface $instance */
        $instance = $this->objectManager->create($instanceName, $data);
        if (!$instance instanceof ImportInterface) {
            throw new InvalidArgumentException(
                $instanceName .
                ' is not instance of \Skwirrel\Pim\Api\ImportInterface'
            );
        }
        return $instance;
    }

    /**
     * Get singleton instance with specified parameters
     *
     * @param string $instanceName
     * @param array $data
     * @throws \InvalidArgumentException
     * @return \Skwirrel\Pim\Api\ImportInterface
     */

    public function get($instanceName, array $data = [])
    {
        /** @var \Skwirrel\Pim\Api\ImportInterface $instance */
        $instance = $this->objectManager->get($instanceName, $data);
        if (!$instance instanceof ImportInterface) {
            throw new InvalidArgumentException(
                $instanceName .
                ' is not instance of \Skwirrel\Pim\Api\ImportInterface'
            );
        }
        return $instance;
    }
}