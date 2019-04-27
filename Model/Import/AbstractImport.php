<?php
namespace Skwirrel\Pim\Model\Import;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Api\ImportInterface;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Model\AbstractModel;

class AbstractImport extends AbstractModel implements ImportInterface
{
    protected $convertedData;
    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    protected $mapping;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    protected $helper;
    /**
     * @var ConverterInterface
     */
    protected $converter;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        ConverterInterface $converter)
    {
        parent::__construct($logger, $progress, $mapping, $helper);

        $this->converter = $converter;
    }

    /**
     * {@inheritDoc}
     */
    public function import()
    {
        throw new \Exception('import function not implemented.');
    }

    /**
     * {@inheritDoc}
     */
    public function getConvertedData()
    {
        if (empty($this->convertedData)) {
            $this->converter->init()->convertData();
            $this->convertedData = $this->converter->getConvertedData();
        }

        return $this->convertedData;
    }
}