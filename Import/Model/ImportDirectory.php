<?php
namespace Skwirrel\Pim\Import\Model;

use Magento\Framework\Simplexml\Config;
use Magento\Framework\Simplexml\Element;

class ImportDirectory
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\Filesystem\Directory\ReadFactory
     */
    protected $directoryReadFactory;

    /**
     * @var string
     */
    protected $subfolder;

    /**
     * @var \Magento\Framework\Simplexml\Config
     */
    protected $xml;

    /**
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory
     * @param string $subfolder
     */
    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\Filesystem\Directory\ReadFactory $directoryReadFactory,
        $subfolder = ""
    ) {
        $this->logger = $logger;
        $this->directoryReadFactory = $directoryReadFactory;
        $this->subfolder = $subfolder;
    }

    /**
     * Get the combined XML of all the import XML files in specified directory
     *
     * @param string $path
     * @return Element|bool
     */
    public function getXml($path = null)
    {

        if (!isset($this->xml) || empty($this->xml->getNode($path))) {
            $basepath = '/data/www/magstore/var/import/pim';
            if (!empty($this->subfolder)) {
                $basepath .= DIRECTORY_SEPARATOR . $this->subfolder;
            }

            if (!file_exists($basepath)) {
                throw new \Exception('Import directory "' . $basepath . '" does not exist');
            }

            $this->xml = new Config(new Element('<Root></Root>'));
            $directoryRead = $this->directoryReadFactory->create($basepath);
            $files = $directoryRead->read();

            foreach ($files as $file) {
                $filepath = $basepath . DIRECTORY_SEPARATOR . $file;
                if (is_dir($filepath)) {
                    continue;
                }

                if (substr_compare($file, '.xml', -4)) {
                    continue;
                }


                $config = new Config($filepath);;
                $configNode = $config->getNode();
                $name = $configNode->getName();
                if (!$configNode || $name != $path) {
                    continue;
                }
                $this->xml->getNode()->appendChild($configNode);
            }
        }

        if (!$this->xml instanceof Config) {
            throw new \Exception('Invalid import directory XML.');
        }

        return $this->xml->getNode($path);
    }

}