<?php
namespace Skwirrel\Pim\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

class Data extends AbstractHelper
{
    protected $paths = [];
    /**
     * @var \Magento\Framework\Filesystem\DirectoryList
     */
    private $directoryList;
    /**
     * @var \Magento\Framework\Module\Dir\Reader
     */
    private $moduleReader;

    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Framework\Filesystem\DirectoryList $directoryList,
        \Magento\Framework\Module\Dir\Reader $moduleReader)
    {
        parent::__construct($context);
        $this->directoryList = $directoryList;
        $this->moduleReader = $moduleReader;
        $this->configure();
    }

    public function getDirectory($path){
        return $this->directoryList->getPath($path);
    }

    public function getMappingFilePath()
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

    function isDebugMode(){
        return false;
    }

    public function getImportDataDirectory()
    {
        $varPath = $this->getDirectory('var');
        return $varPath.'/import/pim';
    }

    public function createImageImportFile($imagePath, $doCopy = true){
        $pubPath= $this->getDirectory('pub');

        $baseName = basename($imagePath);

        $targetPath = $pubPath.'/media/import';
        $targetFile = $targetPath.'/'.md5($imagePath).'_'.$baseName;
        if($doCopy){
            copy($imagePath, $targetFile);
        }
        return $targetFile;

    }

    public function getConfig($config_path)
    {
        return $this->scopeConfig->getValue(
            $config_path,
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
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

    public function slugify($str, $maxLen = false){

        if(preg_match_all('/([a-z]+)/i', strtolower($str), $matched)){

            if(isset($matched[1])){
                $slug =  strtolower(implode('_',$matched[1]));
                if($maxLen !== false && strlen($slug) >= $maxLen){
                    return substr($slug,0, $maxLen);
                }
                return $slug;
            }
        }
        return $str;

    }

}