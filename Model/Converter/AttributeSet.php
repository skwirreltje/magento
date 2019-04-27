<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\Mapping;

class AttributeSet extends AbstractConverter
{


    protected $attributeSets = [];
    protected $configPath;
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Api\MappingInterface $mapping,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Model\Import\Attribute\TypeFactory $typeFactory

    )
    {
        parent::__construct($logger, $progress, $mapping, $helper);
        $this->typeFactory = $typeFactory;
    }

    /**
     * Initialize the converter
     *
     * @return ConverterInterface
     */
    public function init()
    {
        $this->configPath = Mapping::XML_MAGENTO_MAPPING_ENTITIES;

        return $this;
    }

    /**
     * This function converts the inRiver data to Magento 2 ready data and i run
     * from the construct by default. Should be an array of entities's data.
     * Entity data structure depends on its corresponding import model.
     *
     * @return void
     */
    public function convertData()
    {
        $attributeSets = $this->mapping->getAttributeSets();
        $this->attributeSets = $attributeSets;
    }


    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        if (empty($this->co)) {
            $this->init()->convertData();
        }
        return $this->attributeSets;

    }
}