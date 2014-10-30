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
        $this->hourly('getTrafficSources');  // method will be executed once every hour
    }

    public static function getTrafficSources()
    {
       	foreach (API::getSites() as $site)
        {
			$idSite = $site['id'];
	        $lastMinutes = 20;
			$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
			$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
			$origin_dt = new \DateTime("now", $origin_dtz);
			$refTime = $origin_dt->format('Y-m-d H:i:s');
	        $directSql = "SELECT COUNT(*) AS number, round(UNIX_TIMESTAMP(visit_last_action_time) /1200) AS timeslot
	                FROM " . \Piwik\Common::prefixTable("log_visit") . "
	                WHERE idsite = ?
	                AND DATE_SUB('".$refTime."', INTERVAL 1 DAY) < visit_last_action_time
	                AND referer_type = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
	                GROUP BY  round(UNIX_TIMESTAMP(visit_last_action_time) / ?)
	                ";
	        $direct = \Piwik\Db::fetchAll($directSql, array(
	            $idSite, $lastMinutes * 60
	        ));
	        foreach ($direct as &$value) {
				$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     (idsite, source_id, timeslot, traffic) VALUES (?, ?, ?, ?)";
				\Piwik\Db::query($insert, array(
		            $idSite, Common::REFERRER_TYPE_DIRECT_ENTRY, $value['timeslot']*1200, $value['number']
				));
        	}
		}
    }

}