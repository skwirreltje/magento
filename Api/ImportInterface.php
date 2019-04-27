<?php
namespace Skwirrel\Pim\Api;

interface ImportInterface
{

    public function import();

    /**
     * Get the import's entity data if any exists
     *
     * @return mixed
     */
    public function getConvertedData();
}