<?php
namespace Skwirrel\Pim\Model;

use Skwirrel\Pim\Api\MappingInterface;

class ImportManager
{

    /**
     * @var \Skwirrel\Pim\Api\MappingInterface
     */
    private $mapping;

    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    private $progress;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $logger;

    /**
     * @var \Skwirrel\Pim\Model\ModelFactory
     */
    private $importModelFactory;


    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        MappingInterface $mapping,
        \Skwirrel\Pim\Console\Progress $progress,
        \Skwirrel\Pim\Model\ModelFactory $importModelFactory
    ) {
        $this->mapping = $mapping;
        $this->progress = $progress;
        $this->logger = $logger;
        $this->importModelFactory = $importModelFactory;
    }

    public function run($force = false)
    {
        try {
            $this->logger->info('Starting import');

            $this->mapping->load();
            $this->progress->info('Mapping loaded');

            // Run mapped import processes
            $processes = $this->mapping->getProcesses();
            foreach ($processes as $process) {
                $this->runProcess($process);
            }


            $this->progress->info('Import finished successfully');
            $this->logger->info('Import finished successfully');
        } catch (\Exception $e) {
            $this->progress->info($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->logger->error($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->logger->critical($e);
        } finally {
            $this->logger->info('Import ended');
        }
    }

    public function runProcess($process)
    {

        // If the process is extending another, skip it here
        if (!empty($process['extend']['entities'])) {
            return;
        }

        $this->progress->info('Start ' . $process['entity'] . ' process');
        $this->logger->info('Start ' . $process['entity'] . ' process');

        try {
            // Start import with process adapter
            $this->runImportAdapter($process['adapter']);
        } catch (\Exception $e) {
            $this->progress->info($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->logger->error($e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            $this->logger->critical($e);
        } finally {
            $this->progress->info('End ' . $process['entity'] . ' process');
            $this->logger->info('End ' . $process['entity'] . ' process');
        }
    }

    private function runImportAdapter($adapter)
    {
        $importModel = $this->importModelFactory->get($adapter);
        $importModel->import();

    }
}