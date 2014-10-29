<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSourcesProgression;

use Piwik\WidgetsList;

/**
 */
class TrafficSourcesProgression extends \Piwik\Plugin
{
    /**
     * @see Piwik\Plugin::getListHooksRegistered
     */
    public function getListHooksRegistered()
    {
        return array(
            'AssetManager.getJavaScriptFiles' => 'getJsFiles',
            'AssetManager.getStylesheetFiles' => 'getStylesheetFiles',
            'WidgetsList.addWidgets' => 'addWidget',
        );
    }

    public function getJsFiles(&$jsFiles)
    {
        $jsFiles[] = 'plugins/TrafficSourcesProgression/javascripts/jquery.flot.js';
        $jsFiles[] = 'plugins/TrafficSourcesProgression/javascripts/trafficsources.js';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/TrafficSourcesProgression/stylesheets/trafficsources.css";
    }

    /**
     * Add Widget to Live! >
     */
    public function addWidget()
    {
        WidgetsList::add( 'Live!', 'TrafficSourcesProgression_WidgetName', 'TrafficSourcesProgression', 'index');
    }

}
