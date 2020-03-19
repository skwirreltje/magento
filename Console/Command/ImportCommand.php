<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\ObjectManager;
use Skwirrel\Pim\Api\MappingInterface;
use Skwirrel\Pim\Import\Mapping\Config\Reader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends Command
{

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

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
     * @var \Skwirrel\Pim\Model\ImportManager
     */
    private $importManager;

    /**
     * ParseProducts constructor.
     * @param \Skwirrel\Pim\Console\Progress $progress
     * @param \Skwirrel\Pim\Model\ImportManager $importManager
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Model\ImportManager $importManager,
        \Magento\Framework\App\State $state
    ) {

        parent::__construct();

        $this->state = $state;
        $this->progress = $progress;
        $this->importManager = $importManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $this->progress->setOutput($output);

        $productFactory = ObjectManager::getInstance()->create('\Magento\Catalog\Model\ProductFactory');
        $product = $productFactory->create();

        $this->importManager->run();
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
        $this->setName("skwirrel:import");
        $this->setDescription("Run a full import");
        parent::configure();
    }


}
