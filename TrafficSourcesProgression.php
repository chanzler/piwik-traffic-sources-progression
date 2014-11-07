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
use Piwik\Site;

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
        $jsFiles[] = 'plugins/TrafficSourcesProgression/javascripts/trafficsourcesprogression.js';
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
                        traffic INT( 11 ) NOT NULL,
                        date VARCHAR( 10 ) NOT NULL
            		)";
            \Piwik\Db::exec($sql);
			$unique = "ALTER TABLE " . Common::prefixTable('trafficsourcesprogression_sources') . " ADD UNIQUE (
						`idsite` ,
						`source_id` ,
						`timeslot` ,
						`date`
						);";
            \Piwik\Db::exec($unique);
        } catch (Exception $e) {
            // ignore error if table already exists (1050 code is for 'table already exists')
            if (!\Piwik\Db::get()->isErrNo($e, '1050')) {
                throw $e;
            }
        }
       	foreach (API::getSites() as $site)
        {
			$idSite = $site['id'];
	        $lastMinutes = 20;
			$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
			$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
			$origin_dt = new \DateTime("now", $origin_dtz);
	        $sources = array(Common::REFERRER_TYPE_DIRECT_ENTRY, Common::REFERRER_TYPE_SEARCH_ENGINE, Common::REFERRER_TYPE_WEBSITE, Common::REFERRER_TYPE_CAMPAIGN, 10);
			foreach($sources as &$source) {
		        \Piwik\Db::deleteAllRows(Common::prefixTable('trafficsourcesprogression_sources'), "WHERE idsite = ? AND source_id = ?", "", 100000, array($idSite, $source));
				for($i=1; $i<=72; $i++){
					$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				               (idsite, source_id, timeslot, traffic, date) VALUES (?, ?, ?, ?, ?)";
					\Piwik\Db::query($insert, array(
				        $idSite, $source, $i, 0, $origin_dt->format('d.m.Y')
					));
				}
	        }
	    }        
    }

    public function uninstall()
    {
        \Piwik\Db::dropTables(Common::prefixTable('trafficsourcesprogression_sources'));
    }

}
