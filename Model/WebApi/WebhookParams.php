<?php
namespace Skwirrel\Pim\Model\WebApi;

use Skwirrel\Pim\WebApi\WebhookParamsInterface;

class WebhookParams implements WebhookParamsInterface
{
    public $product;

    /**
     * @return mixed
     */
    public function getProduct()
    {
        return $this->product;
    }

    /**
     * @param mixed $product
     * @return mixed
     */
    public function setProduct($product)
    {
        $this->product = $product;
    }
}