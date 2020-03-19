<?php
namespace Skwirrel\Pim\Logger\Handler;

use Magento\Framework\Logger\Handler\System;

class Report extends System
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/skwirrel/reports.log';
}
