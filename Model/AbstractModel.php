<?php
namespace Skwirrel\Pim\Model;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Console\Progress;

/**
 * Abstract model for the Skwirrel import to get module logger, import mapping
 * handler and configuration helper.
 */
abstract class AbstractModel
{
    /**
     * Enable debug logging
     *
     * @var bool
     */
    protected $debugMode = false;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;


    /**
     * @var MappingInterface
     */
    protected $mapping;

    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    protected $helper;
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    protected $progress;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper

    ) {
        $this->mapping = $mapping;
        $this->helper = $helper;
        $this->debugMode = $helper->isDebugMode();
        $this->logger = $logger;
        $this->progress = $progress;
    }

    protected function getProcess(){
        $name = (new \ReflectionClass($this))->getShortName();
        return $this->mapping->getProcess($name);
    }
}
