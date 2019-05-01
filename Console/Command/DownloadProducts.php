<?php

namespace Skwirrel\Pim\Console\Command;

use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DownloadProducts extends Command
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
     * @var \Skwirrel\Pim\Model\Converter\Etim\EtimAttribute
     */
    private $attributeConverter;
    /**
     * @var \Skwirrel\Pim\Console\Progress
     */
    private $progress;

    public function __construct(
        \Skwirrel\Pim\Console\Progress $progress,

        \Skwirrel\Pim\Client\ApiClient $apiClient,
        \Skwirrel\Pim\Helper\Data $dataHelper,
        \Skwirrel\Pim\Model\Converter\Etim\EtimAttribute $attributeConverter,

        \Magento\Framework\App\State $state
    ) {

        parent::__construct();
        $this->state = $state;
        $this->apiClient = $apiClient;
        $this->dataHelper = $dataHelper;
        $this->attributeConverter = $attributeConverter;
        $this->progress = $progress;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {

        $this->state->setAreaCode(\Magento\Framework\App\Area::AREA_ADMINHTML);

        $basePath = $this->getFileBasePath();

        $this->progress->setOutput($output);
        $this->progress->info('Downloading products');
        $currentPage = 1;
        $continue = true;
        while ($continue) {
            $response = $this->getProductsForPage($currentPage);
            if($currentPage == 1){
                $numProducts = $response->page->total_products;
                $this->progress->barStart('product', $numProducts);
            }

            $numpages = $response->page->number_of_pages;
            if (isset($response->products)) {

                foreach ($response->products as $product) {

                    if ($this->validateProductItem($product)) {
                        $attachments = $this->parseAttachments($product);
                        $product->attachments = $this->writeProductAttachments($basePath,$product,$attachments);
                        $this->writeProductItem($basePath, $product);
                    }
                    else{
                        print_r($product);
                    }
                    $this->progress->barAdvance('product');
                }
            }
            else{
                $this->progress->info('Error while downloading products');
                $continue = false;
            }
            if ($currentPage >= $numpages) {
                $continue = false;
            }
            $currentPage += 1;
        }
        $this->progress->barFinish('product');
    }

    protected function getProductsForPage($pageNumber)
    {
        $selectionId = $this->dataHelper->getConfig('skwirrel/api_options/selection_id') !== null ? $this->dataHelper->getConfig('skwirrel/api_options/selection_id') : 1;

        $response = $this->apiClient->makeRequest('getProductsBySelection', [
            'dynamic_selection_id' => $selectionId,
            'page' => $pageNumber,
            'include_categories' => true,
            'include_attachments' => true,
            'include_trade_items' => true,
            'include_trade_item_prices' => true,
            'include_trade_item_translations' => true,
            'include_etim' => true,
            'include_related_products' => false,
            'include_product_translations' => true,
            'include_languages' => ['en', 'nl']
        ]);

        return $response;

    }


    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("skwirrel:downloadproducts");
        $this->setDescription("download products");
        parent::configure();
    }

    private function getFileBasePath()
    {
        $path = $this->dataHelper->getDirectory('var');
        if (!file_exists($path . '/import')) {
            mkdir($path . '/import');
        }
        if (!file_exists($path . '/import/pim')) {
            mkdir($path . '/import/pim');
        }
        return $path . '/import/pim';
    }

    private function validateProductItem($product)
    {

        if (!isset($product->{'_trade_items'}) || empty($product->{'_trade_items'})) {
           //  return false;
        }
        if (!isset($product->{'_etim'}) || empty($product->{'_etim'}->{'_etim_features'})) {

            return false;
        }

        if(empty((array) $product->_categories)){
            //return false;
        }

        return true;
    }

    private function writeProductItem($basePath, $product)
    {
        $id = $product->product_id;
        $filePath = $basePath . '/product_' . $id;
        file_put_contents($filePath . '.json', json_encode($product));
    }

    private function parseAttachments($product)
    {
        $attachments = [];
        if(!isset($product->{'_attachments'})){
            return $attachments;
        }

        foreach($product->{'_attachments'} as $item){
            if($this->isAttachmentImage($item) && $item->product_attachment_type_code == 'PPI'){
                $attachments[] = [
                    'source_url' => $item->source_url,
                    'mime_type' => $item->file_mimetype,
                    'data' => $item
                ];
            }
        }
        return $attachments;
    }

    private function isAttachmentImage($item)
    {
        if($item->source_type == 'FILE'){
            if(in_array($item->file_mimetype,['image/jpeg','image/png'])){
                return true;
            }
        }
        if($item->source_type == 'URL'){
            $baseName = basename($item->source_url);
            foreach(['.png','.jpeg','.jpg'] as $ext){
                if(strpos(strtolower($baseName),$ext)!== false){
                    return true;
                }

            }
        }
        return false;
    }

    private function writeProductAttachments($basePath, $product, $attachments)
    {
        $writtenAttachments = [];
        $filePath = $basePath.'/attachmments_'.$product->product_id;
        if(!file_exists($filePath)){
            mkdir($filePath);
        }
        foreach($attachments as $attachment){
            $imageFilePath = $filePath.'/'.basename($attachment['source_url']);
            try{
                if($content = file_get_contents($attachment['source_url'])){
                    file_put_contents($imageFilePath, $content);
                }
                $writtenAttachments[] = $imageFilePath;

            }
            catch(\Exception $e){

            }

        }
        return $writtenAttachments;

    }


}
