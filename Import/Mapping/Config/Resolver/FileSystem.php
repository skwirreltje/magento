<?php
namespace Skwirrel\Pim\Import\Mapping\Config\Resolver;

class FileSystem implements ResolverInterface
{
    protected $paths = [];
    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    private $moduleReader;
    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;

    public function __construct(
        \Magento\Framework\Module\Dir\Reader $moduleReader,
        \Magento\Framework\Filesystem\DirectoryList $directoryList
    ) {
        $this->moduleReader = $moduleReader;
        $this->directoryList = $directoryList;
        $this->configure();
    }

    public function resolve()
    {
        $configFile = false;
        foreach ($this->paths as $path) {
            if (file_exists($path . '/mapping.xml')) {
                $configFile = $path . '/mapping.xml';
            }
        }

        if (!$configFile) {
            throw new \Exception(sprintf('Cannot find mapping.xml in paths : %s', implode("\n", $this->paths)));
        }
        return $configFile;
    }

    private function configure()
    {
        $baseDir = $this->moduleReader->getModuleDir(
            \Magento\Framework\Module\Dir::MODULE_ETC_DIR,
            'Skwirrel_Pim'
        );

        $this->paths[] = $baseDir . '/import';
        $this->paths[] = $this->directoryList->getPath('etc') . '/import';
    }
}