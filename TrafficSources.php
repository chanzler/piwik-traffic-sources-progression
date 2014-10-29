<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSources;

use Piwik\WidgetsList;

/**
 */
class TrafficSources extends \Piwik\Plugin
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
        $jsFiles[] = 'plugins/TrafficSources/javascripts/trafficsources.js';
    }

    public function getStylesheetFiles(&$stylesheets)
    {
        $stylesheets[] = "plugins/TrafficSources/stylesheets/trafficsources.css";
    }

    /**
     * Add Widget to Live! >
     */
    public function addWidget()
    {
        WidgetsList::add( 'Live!', 'TrafficSources_WidgetName', 'TrafficSources', 'index');
    }

}
