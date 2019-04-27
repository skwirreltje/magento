<?php
namespace Skwirrel\Pim\Import\Mapping\Config;

use Skwirrel\Pim\Import\Mapping\Config\Parser\ParserFactory;
use Skwirrel\Pim\Import\Mapping\Config\Resolver\ResolverInterface;

class Reader
{
    /**1
     * @var \Skwirrel\Pim\Model\Mapping\Config\Resolver\ResolverInterface
     */
    protected $resolver;
    protected $doc;
    protected $basePath = '/mapping';

    protected $cachedConfig = [];

    /**
     * @var \Skwirrel\Pim\Model\Mapping\Config\Parser\ParserFactory
     */
    private $parserFactory;

    public function __construct(ResolverInterface $resolver, ParserFactory $parserFactory)
    {
        $this->resolver = $resolver;

        $this->parserFactory = $parserFactory;
    }

    public function getDocument()
    {
        if (!isset($this->doc)) {

            $configPath = $this->resolver->resolve();

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            try {
                $dom->load($configPath);
                $this->doc = $dom;
            } catch (\Exception $e) {

            }
        }
        return $this->doc;
    }

    public function getPath($path)
    {
        if(isset($this->cachedConfig[$path])){
            return $this->cachedConfig[$path];
        }

        $doc = $this->getDocument();
        $xpath = new \DOMXPath($doc);
        $nodeList = $xpath->query($this->basePath . '/' . $path);

        $parser = $this->parserFactory->createParser($path);
        return $this->cachedConfig[$path] =  $parser->parse($nodeList->item(0));
    }
}