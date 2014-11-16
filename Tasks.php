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

    public function getTrafficSourcesTask()
    {
       	foreach (API::getSites() as $site)
        {
			$idSite = $site['id'];
	        $lastMinutes = 20;
			$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
			$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
			$origin_dt = new \DateTime("now", $origin_dtz);
            $utc_dtz = new \DateTimeZone('UTC');
            $utc_dt = new \DateTime("now", $utc_dtz);
            $refTime = $utc_dt->format('Y-m-d H:i:s');
 	        $hours = intval($origin_dt->format('H'));
			$minutes = intval($origin_dt->format('i'));
			$minutesToMidnight = $minutes+($hours*60);
			$statTimeSlot = ceil($minutesToMidnight/20);
	        $sources = array(Common::REFERRER_TYPE_DIRECT_ENTRY, Common::REFERRER_TYPE_SEARCH_ENGINE, Common::REFERRER_TYPE_WEBSITE, Common::REFERRER_TYPE_CAMPAIGN);
			foreach($sources as &$source) {
				$lastProcessedTimeslotSql = "SELECT MIN(timeslot)
		                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
						WHERE idsite = ?
						AND date = ?
						AND processed = 0
						";
		        $lastProcessedTimeslot = \Piwik\Db::fetchOne($lastProcessedTimeslotSql, array(
		            $idSite, $origin_dt->format('d.m.Y')
		        ));
				if ($lastProcessedTimeslot == null) $lastProcessedTimeslot = 72;
				
				$sql = "SELECT COUNT(*) AS number, (round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum)) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".$source."
		                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
		                ";
		        $result = \Piwik\Db::fetchAll($sql, array(
		            $statTimeSlot, $idSite, ($minutesToMidnight<20)?$minutesToMidnight:((($statTimeSlot-$lastProcessedTimeslot)*20)+20), $lastMinutes * 60
		        ));
		        $db_dateSql = "SELECT MAX(date)
		                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
						WHERE idsite = ? AND timeslot = 72 AND source_id = ?
		                ";
				//Initialize sources
		        $db_date = \Piwik\Db::fetchOne($db_dateSql, array($idSite, $source));
		        if (strcmp($origin_dt->format('d.m.Y'), $db_date)!=0) {
			        //\Piwik\Db::deleteAllRows(\Piwik\Common::prefixTable('trafficsourcesprogression_sources'), "WHERE idsite = ? AND source_id = ?", "", 100000, array($idSite, $source));
			        for($i=1; $i<=72; $i++){
						$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				                     (idsite, source_id, timeslot, traffic, date, processed) VALUES (?, ?, ?, ?, ?, ?)";
						\Piwik\Db::query($insert, array(
				            $idSite, $source, $i, 0, $origin_dt->format('d.m.Y'), 0
						));
			        }
		        }
		        foreach ($result as &$value) {
		        	$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
			                     SET traffic = ?, processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
					\Piwik\Db::query($insert, array(
			            $value['number'], $idSite, $source, $value['timeslot'], $origin_dt->format('d.m.Y')
					));
	        	}
		        for($i=1; $i<=$statTimeSlot; $i++){
		        	$update = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
			                     SET processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
					\Piwik\Db::query($update, array(
			            $idSite, $source, $i, $origin_dt->format('d.m.Y')
					));
    	    	}
	        }

	        $socialSql = "SELECT referer_url, (round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum)) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".Common::REFERRER_TYPE_WEBSITE."
		                ";
	        $social = \Piwik\Db::fetchAll($socialSql, array(
		            $statTimeSlot, $idSite, ($minutesToMidnight<20)?$minutesToMidnight:((($statTimeSlot-$lastProcessedTimeslot)*20)+20)
	        ));
			//Initialize social
	        $db_date = \Piwik\Db::fetchOne($db_dateSql, array($idSite, 10));
	        if (strcmp($origin_dt->format('d.m.Y'), $db_date)!=0) {
		        for($i=1; $i<=72; $i++){
					$insert = "INSERT INTO ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
			                     (idsite, source_id, timeslot, traffic, date, processed) VALUES (?, ?, ?, ?, ?, ?)";
					\Piwik\Db::query($insert, array(
			            $idSite, 10, $i, 0, $origin_dt->format('d.m.Y'), 0
					));
		        }
	        }
	        for($i=$lastProcessedTimeslot-1; $i<=$statTimeSlot; $i++){
	        	$socialCount = 0;
	            foreach ($social as &$value) {
	        		if(API::isSocialUrl($value['referer_url']) && $i==$value['timeslot']) $socialCount++;
		        }
				$update = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
			               SET traffic = ?, processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
				\Piwik\Db::query($update, array(
			           $socialCount, $idSite, 10, $i, $origin_dt->format('d.m.Y')
				));
		    }
		}
    }
}