<?php
namespace Skwirrel\Pim\Logger\Handler;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Debug extends Base
{
    /**
     * @var string
     */
    protected $fileName = '/var/log/skwirrel/exceptions.log';

    /**
     * @var int
     */
    protected $loggerType = Logger::DEBUG;
}
