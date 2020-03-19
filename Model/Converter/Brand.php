<?php
namespace Skwirrel\Pim\Model\Converter;

use Skwirrel\Pim\Api\ConverterInterface;
use Skwirrel\Pim\Model\Mapping;

class Brand implements ConverterInterface
{

    const PROCESS_NAME = 'Brand';

    protected $configPath;
    /**
     * @var Mapping
     */
    protected $mapping;
    /**
     * @var \Skwirrel\Pim\Model\Import\Attribute\TypeFactory
     */
    private $typeFactory;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $helper;

    protected $convertedData = [];
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    private $progress;
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Helper\Data $helper,
        \Skwirrel\Pim\Client\ApiClient $apiClient


    )
    {
        $this->logger = $logger;
        $this->helper = $helper;
        $this->progress = $progress;
        $this->apiClient = $apiClient;
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
     * @param Mapping $mapping
     * @return $this
     */
    public function setMapping(Mapping $mapping)
    {
        $this->mapping = $mapping;
        return $this;
    }

    /**
     * This function converts the Skwirrel data to Magento 2 ready data and i run
     * from the construct by default. Should be an array of entities's data.
     * Entity data structure depends on its corresponding import model.
     *
     * @return void
     */
    public function convertData()
    {
        $this->loadBrands();
    }

    protected function loadBrands(){

        $response = $this->apiClient->makeRequest('getBrands');

        if (isset($response->brands)) {
            $count = count((array) $response->brands);
            $this->convertedData = $this->parseBrands($response->brands);
        }

    }

    function parseBrands($brands){
        return (array) $brands;
    }


    /**
     * Get the data converted in convertData()
     *
     * @return array
     */
    public function getConvertedData()
    {
        if (empty($this->convertedData)) {
            $this->init()->convertData();
        }
        return $this->convertedData;

    }
}