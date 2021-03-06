<?php

/**
 * Checkout.com
 * Authorized and regulated as an electronic money institution
 * by the UK Financial Conduct Authority (FCA) under number 900816.
 *
 * PHP version 7
 *
 * @category  Magento2
 * @package   Checkout.com
 * @author    Platforms Development Team <platforms@checkout.com>
 * @copyright 2010-2019 Checkout.com
 * @license   https://opensource.org/licenses/mit-license.html MIT License
 * @link      https://docs.checkout.com/
 */

namespace CheckoutCom\Magento2\Model\Service;

/**
 * Class VersionHandlerService.
 */
class VersionHandlerService
{

    /**
     * @var Config
     */
    public $config;

    /**
     * @var Utilities
     */
    public $utilities;

    /**
     * @var Logger
     */
    public $logger;

    /**
     * @var HttpHandler
     */
    public $httpHandler;

    /**
     * @var Curl
     */
    public $curl;

    public $moduleDirReader;

    /**
     * ApiHandlerService constructor.
     */
    public function __construct(
        \CheckoutCom\Magento2\Gateway\Config\Config $config,
        \CheckoutCom\Magento2\Helper\Utilities $utilities,
        \CheckoutCom\Magento2\Helper\Logger $logger,
        \Checkout\Library\HttpHandler $httpHandler,
        \Magento\Framework\HTTP\Client\Curl $curl,
        \Magento\Framework\Module\Dir\Reader $moduleDirReader,
        \Magento\Framework\Filesystem\Driver\File $fileDriver
    ) {
        $this->config = $config;
        $this->utilities = $utilities;
        $this->logger = $logger;
        $this->httpHandler = $httpHandler;
        $this->curl = $curl;
        $this->moduleDirReader = $moduleDirReader;
        $this->fileDriver = $fileDriver;
    }

    /*
     * Returns type of version update
     */
    public function getVersionType($currentVersion, $latestVersion)
    {
        $versions = [explode('.', $currentVersion), explode('.', $latestVersion)];

        // Compare version numbers - major, minor and then revision
        for ($i = 0; $i < 3; $i++) {
            if ($versions[0][$i] < $versions[1][$i]) {
                switch ($i) {
                    case 0;
                    return  'major';
                    break;

                    case 1;
                    return 'minor';
                    break;

                    case 2;
                    return 'revision';
                    break;
                }
            };
        }
    }

    /*
     * Check if module needs updating
     */
    public function needsUpdate($currentVersion, $latestVersion)
    {
        $versions = [explode('.', $currentVersion), explode('.', $latestVersion)];

        // Compare version numbers - major, minor and then revision
        for ($i = 0; $i < 3; $i++) {
            if ($versions[0][$i] < $versions[1][$i]) {
                return true;
            };
        }
        return false;
    }

    /*
     * Get array of releases from Github
     */
    public function getVersions()
    {
        $this->curl->setHeaders([
            'User-Agent' => 'checkout-magento2-plugin'
        ]);

        // Send the request
        $this->curl->get('https://api.github.com/repos/checkout/checkout-magento2-plugin/releases');

        // Get the response
        $content = $this->curl->getBody();

        // Return the array of releases
        return json_decode($content, true);
    }

    /*
     * Get latest version number
     */
    public function getLatestVersion($versions)
    {
        foreach ($versions as $version) {
            // Find latest release that is not beta
            if (isset($version['tag_name']) && count(explode('-', $version['tag_name'])) == 1) {
                return $version['tag_name'];
            }
        }
    }

    /**
     * Get the module version
     */
    public function getModuleVersion($prefix = '')
    {
        // Build the composer file path
        $filePath = $this->moduleDirReader->getModuleDir(
            '',
            'CheckoutCom_Magento2'
        ) . '/composer.json';

        // Get the composer file content
        $fileContent = json_decode(
            $this->fileDriver->fileGetContents($filePath)
        );

        return $prefix . $fileContent->version;
    }
}
