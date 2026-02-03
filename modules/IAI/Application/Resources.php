<?php
namespace app\modules\IAI\Application;

/**
 * Class containing different resources useful while embedding application in IdoSell Shop panel
 */
class Resources
{
    /**
     * Application config
     *
     * @var \app\modules\IAI\Application\Config
     */
    protected $config;

    /**
     * Resources constructor
     *
     * @param \app\modules\IAI\Application\Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns URL of the gate which serves IdoSell Shop panel's stylesheets that should be used in application
     *
     * @return string
     */
    public function getStyleSheetUrl()
    {
        return
            'https://' . $this->config->getPanelTechnicalDomain() . '/panel/action/applications/getCss' .
            '/application/' . $this->config->getId() . '/secret/' . $this->config->getSecret();
    }
}