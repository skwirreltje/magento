<?php


namespace Skwirrel\Pim\Config\Config;

class Converter implements \Magento\Framework\Config\ConverterInterface
{

    /**
     * Convert dom node tree to array
     *
     * @param \DOMDocument $source
     * @return array
     */
    public function convert($source)
    {
        $output = [];
        $xpath = new \DOMXPath($source);
        $nodes = $xpath->evaluate('/config/api');
        
        /** @var $node \DOMNode */
        foreach ($nodes as $node) {
            $nodeId = $node->attributes->getNamedItem('id');
        
            $data = [];
            $data['id'] = $nodeId;
            foreach ($node->childNodes as $childNode) {
                if ($childNode->nodeType != XML_ELEMENT_NODE) {
                    continue;
                }
        
                $data[$childNode->nodeName] = $childNode->nodeValue;
            }
            $output['api'][$nodeId] = $data;
        }
        
        return $output;
    }
}
