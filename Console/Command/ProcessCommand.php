<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Import\Mapping\Config\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ProcessCommand extends Command
{

    /**
     * @var \Magento\Catalog\Model\ProductFactory
     */
    private $productFactory;
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;
    /**
     * @var \Magento\Eav\Model\Entity\Attribute
     */
    private $entityAttribute;
    /**
     * @var \Magento\Catalog\Model\ProductRepository
     */
    private $productRepository;
    /**
     * @var \Magento\Catalog\Model\ResourceModel\Eav\Attribute
     */
    private $attributeFactory;

    protected $attributeInfo = [];
    /**
     * @var \Skwirrel\Pim\Client\ApiClient
     */
    private $apiClient;
    /**
     * @var \Skwirrel\Pim\Helper\Data
     */
    private $dataHelper;
    /**
     * @var Reader
     */

    protected $entityType = 'product';

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    private $directoryReadFactory;
    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    private $mapping;
    /**
     * @var \Skwirrel\Pim\Model\ModelFactory
     */
    private $importModelFactory;
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    protected $progress;

    /**
     * ParseProducts constructor.
     * @param null $name
     * @param \Skwirrel\Pim\Client\ApiClient $apiClient
     * @param \Skwirrel\Pim\Helper\Data $dataHelper
     * @param \Skwirrel\Pim\Model\Converter\ProductItem $productItemConverter
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Helper\Data $dataHelper,
        MappingInterface $mapping,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        \Skwirrel\Pim\Model\ModelFactory $importModelFactory,
        \Magento\Framework\App\State $state
    ) {

        parent::__construct();

        $this->state = $state;
        $this->dataHelper = $dataHelper;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->mapping = $mapping;
        $this->importModelFactory = $importModelFactory;
        $this->progress = $progress;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->progress->setOutput($output);

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $basePath = $this->getFileBasePath();

        $this->mapping->load();

        $entityName = $input->getOption('entity');

        $process = $this->mapping->getProcess($entityName);

        if (!$process) {
            $output->writeln("No process for entity :" . $entityName);
        }

        $this->runProcess($process);

    }


    function runProcess($process)
    {
        $importModel = $this->importModelFactory->get($process['adapter']);
        $importModel->import();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:process");
        $this->setDescription("Process a certain entity type");
        $this->addOption('entity', null, InputOption::VALUE_REQUIRED, 'Entity type to run');

        parent::configure();
    }

    private function getFileBasePath()
    {
        $path = $this->dataHelper->getDirectory('var');
        if (!file_exists($path . '/import')) {
            mkdir($path . '/import');
        }
        return $path . '/import';
    }


}
