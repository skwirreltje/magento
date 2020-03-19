<?php
namespace Skwirrel\Pim\WebApi;

interface WebhookParamsInterface
{
    /**
     * @return mixed
     */
    public function getProduct();

    /**
     * @param mixed $product
     * @return mixed
     */
    public function setProduct($product);
}