<?php

namespace Cypisnet\TemaOrder\Model\Logger;

use Magento\Framework\Filesystem\DriverInterface;

/**
 * Class Handler
 * @package RocketWeb\RestLog\Model\Logger
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Handler constructor.
     * @param DriverInterface $filesystem
     * @param null $filePath
     */
    public function __construct(DriverInterface $filesystem, $filePath = null)
    {
        parent::__construct($filesystem, $filePath);
        $this->getFormatter()->ignoreEmptyContextAndExtra(true);
    }

    /**
     * Logging level
     * @var int
     */
    protected $loggerType = \Monolog\Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = '/var/log/rest_api.log';
}