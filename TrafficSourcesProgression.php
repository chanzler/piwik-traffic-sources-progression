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
use Piwik\Common;

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

    public function install()
    {
        try {
            $sql = "CREATE TABLE " . Common::prefixTable('trafficsourcesprogression_sources') . " (
                        idsite INT( 10 ) NOT NULL ,
                        source_id INT( 10 ) NOT NULL ,
                        timeslot INT( 10 ) NOT NULL ,
                        traffic INT( 11 ) NOT NULL
                    )";
            \Piwik\Db::exec($sql);
        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!\Piwik\Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
		Tasks::getMaxVisits();
    }

    public function uninstall()
    {
        \Piwik\Db::dropTables(Common::prefixTable('trafficsourcesprogression_sources'));
    }

}
