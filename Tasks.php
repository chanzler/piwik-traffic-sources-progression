<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSourcesProgression;

use Piwik\Site;
use Piwik\Common;

class Tasks extends \Piwik\Plugin\Tasks
{
    public function schedule()
    {
        $this->hourly('getTrafficSourcesTask');  // method will be executed once every hour
    }

    function getTrafficSourcesTask()
    {
       	foreach (API::getSites() as $site)
        {
			$idSite = $site['id'];
	        $lastMinutes = 20;
			$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
			$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
			$origin_dt = new \DateTime("now", $origin_dtz);
			$refTime = $origin_dt->format('Y-m-d H:i:s');
	        //direct traffic
	        $directSql = "SELECT COUNT(*) AS number, round(UNIX_TIMESTAMP(visit_last_action_time) /1200) AS timeslot
	                FROM " . \Piwik\Common::prefixTable("log_visit") . "
	                WHERE idsite = ?
	                AND DATE_SUB('".$refTime."', INTERVAL 1 DAY) < visit_last_action_time
	                AND referer_type = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
	                GROUP BY round(UNIX_TIMESTAMP(visit_last_action_time) / ?)
	                ";
	        $direct = \Piwik\Db::fetchAll($directSql, array(
	            $idSite, $lastMinutes * 60
	        ));
	        \Piwik\Db::deleteAllRows(Common::prefixTable('trafficsourcesprogression_sources'), "WHERE idsite = ? AND source_id = ?", "", 100000, array($idSite, Common::REFERRER_TYPE_DIRECT_ENTRY));
	        for($i=(round(time()/1200)-72); $i<round(time()/1200); $i++){
				$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     (idsite, source_id, timeslot, traffic) VALUES (?, ?, ?, ?)";
				\Piwik\Db::query($insert, array(
		            $idSite, Common::REFERRER_TYPE_DIRECT_ENTRY, $i, 0
				));
	        }
	        foreach ($direct as &$value) {
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
		            $value['number'], $idSite, Common::REFERRER_TYPE_DIRECT_ENTRY, $value['timeslot']
				));
        	}

	        //search traffic
	        $searchSql = "SELECT COUNT(*) AS number, round(UNIX_TIMESTAMP(visit_last_action_time) /1200) AS timeslot
	                FROM " . \Piwik\Common::prefixTable("log_visit") . "
	                WHERE idsite = ?
	                AND DATE_SUB('".$refTime."', INTERVAL 1 DAY) < visit_last_action_time
	                AND referer_type = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
	                GROUP BY round(UNIX_TIMESTAMP(visit_last_action_time) / ?)
	                ";
	        $search = \Piwik\Db::fetchAll($searchSql, array(
	            $idSite, $lastMinutes * 60
	        ));
	        \Piwik\Db::deleteAllRows(Common::prefixTable('trafficsourcesprogression_sources'), "WHERE idsite = ? AND source_id = ?", "", 100000, array($idSite, Common::REFERRER_TYPE_SEARCH_ENGINE));
	        for($i=(round(time()/1200)-72); $i<round(time()/1200); $i++){
				$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     (idsite, source_id, timeslot, traffic) VALUES (?, ?, ?, ?)";
				\Piwik\Db::query($insert, array(
		            $idSite, Common::REFERRER_TYPE_SEARCH_ENGINE, $i, 0
				));
	        }
	        foreach ($search as &$value) {
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
		            $value['number'], $idSite, Common::REFERRER_TYPE_SEARCH_ENGINE, $value['timeslot']
				));
        	}

		}
    }

}