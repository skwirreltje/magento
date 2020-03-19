<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\AbstractModel;

abstract class AbstractConverter extends AbstractModel implements  ConverterInterface
{

    protected $convertedData = [];

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper
    ) {
        parent::__construct($logger,$progress, $mapping, $helper);
    }


    public function getConvertedData()
    {
        return $this->convertedData;
    }

    /**
     * @param Mapping $mapping
     * @return $this
     */
    public function setMapping(Mapping $mapping)
    {
        $this->mapping = $mapping;
        return $this;
    }

}