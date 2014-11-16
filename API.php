<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSourcesProgression;

use Piwik\Piwik;
use Piwik\API\Request;
use \DateTimeZone;
use Piwik\Site;
use Piwik\Common;


/**
 * API for plugin ConcurrentsByTrafficSource
 *
 */
class API extends \Piwik\Plugin\API {

	public static function isSocialUrl($url, $socialName = false)
	{
		foreach (Common::getSocialUrls() as $domain => $name) {
	
			if (preg_match('/(^|[\.\/])'.$domain.'([\.\/]|$)/', $url) && ($socialName === false || $name == $socialName)) {
	
				return true;
			}
		}
	
		return false;
	}
	
	public static function get_timezone_offset($remote_tz, $origin_tz = null) {
    		if($origin_tz === null) {
        		if(!is_string($origin_tz = date_default_timezone_get())) {
            			return false; // A UTC timestamp was returned -- bail out!
        		}
    		}
    		$origin_dtz = new \DateTimeZone($origin_tz);
    		$remote_dtz = new \DateTimeZone($remote_tz);
    		$origin_dt = new \DateTime("now", $origin_dtz);
    		$remote_dt = new \DateTime("now", $remote_dtz);
    		$offset = $origin_dtz->getOffset($origin_dt) - $remote_dtz->getOffset($remote_dt);
    		return $offset;
	}
	
	private static function startsWith($haystack, $needle){
    	return $needle === "" || strpos($haystack, $needle) === 0;
	}

	private static function getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $origin_dt, $referrerType, $update, $statTimeSlot, $lastProcessedTimeslot){
		if ($update){
			$sql = "SELECT COUNT(idvisit) AS number, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
			                FROM " . \Piwik\Common::prefixTable("log_visit") . "
							cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
							cross join (select @rownum := ?) s
			                WHERE idsite = ?
			                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
			                AND referer_type = ".$referrerType."
			                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
			                ";
			$numbers = \Piwik\Db::fetchAll($sql, array(
		            $statTimeSlot, $idSite, ($minutesToMidnight<20)?$minutesToMidnight:((($statTimeSlot-$lastProcessedTimeslot)*20)+20), $lastMinutes * 60
			));
			//$index=0;
			foreach ($numbers as &$value) {
				//if ($index >= 0 || $minutesToMidnight < 20){
					$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
					                     SET traffic = ?, processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
					\Piwik\Db::query($insert, array(
							$value['number'], $idSite, $referrerType, $value['timeslot'], $origin_dt->format('d.m.Y')
					));
				//}
				//$index++;
			}
	        for($i=1; $i<=$statTimeSlot; $i++){
	        	$update = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     SET processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
				\Piwik\Db::query($update, array(
		            $idSite, $referrerType, $i, $origin_dt->format('d.m.Y')
				));
   	    	}
		}
        $sql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "	
                WHERE idsite = ?
                AND source_id = ".$referrerType."
                AND date = ?
                ORDER BY timeslot ASC
                ";
        $numbers = \Piwik\Db::fetchAll($sql, array(
            $idSite, $origin_dt->format('d.m.Y')
        ));
		if (count($numbers) == 0){
		     $clone = clone $origin_dt;    
			 $clone->modify( '-1 day' );
			 $sql = "SELECT *
		                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "	
	    	            WHERE idsite = ?
	        	        AND source_id = ".Common::REFERRER_TYPE_CAMPAIGN."
	            	    AND date = ?
	                	ORDER BY timeslot ASC
	                ";
		    $numbers = \Piwik\Db::fetchAll($sql, array(
		    	$idSite, $clone->format('d.m.Y')
	        ));
		}
		return $numbers;
	}
		
    /**
     * Retrieves visit count from lastMinutes and peak visit count from lastDays
     * in lastMinutes interval for site with idSite.
     *
     * @param int $idSite
     * @param int $lastMinutes
     * @param int $lastDays
     * @return int
     */
    public static function getTrafficSourcesProgression($idSite, $lastMinutes=20)
    {
        \Piwik\Piwik::checkUserHasViewAccess($idSite);
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
		$statTimeSlot = round($minutesToMidnight/20);
		$lastProcessedTimeslotSql = "SELECT MIN(timeslot)
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				WHERE idsite = ?
				AND date = ?
				AND processed = 0
				";
        $lastProcessedTimeslot = \Piwik\Db::fetchOne($lastProcessedTimeslotSql, array(
            $idSite, $origin_dt->format('d.m.Y')
        ));
        
		//Get the actual data
		$campaign = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $origin_dt, Common::REFERRER_TYPE_CAMPAIGN, true, $statTimeSlot, $lastProcessedTimeslot);
		$campaignString = "\"".Piwik::translate('TrafficSourcesProgression_Campaign')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Campaign')."\", \"data\":[";
        foreach ($campaign as &$value) {
			$campaignString .= "[".$value['timeslot'].", ".$value['traffic']."],";
		}
		$campaignString = rtrim($campaignString, ",");
		$campaignString .= "]}";

		$direct = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $origin_dt, Common::REFERRER_TYPE_DIRECT_ENTRY, true, $statTimeSlot, $lastProcessedTimeslot);
		$directString = "\"".Piwik::translate('TrafficSourcesProgression_Direct')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Direct')."\", \"data\":[";
        foreach ($direct as $key=>&$value) {
			$directString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic'])."],";
		}
		$directString = rtrim($directString, ",");
		$directString .= "]}";
		
		$search = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $origin_dt, Common::REFERRER_TYPE_SEARCH_ENGINE, true, $statTimeSlot, $lastProcessedTimeslot);
		$searchString = "\"".Piwik::translate('TrafficSourcesProgression_Search')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Search')."\", \"data\":[";
        foreach ($search as $key=>&$value) {
			$searchString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$searchString = rtrim($searchString, ",");
		$searchString .= "]}";
		
		$website = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $origin_dt, Common::REFERRER_TYPE_WEBSITE, true, $statTimeSlot, $lastProcessedTimeslot);
		$websiteString = "\"".Piwik::translate('TrafficSourcesProgression_Links')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Links')."\", \"data\":[";
        foreach ($website as $key=>&$value) {
			$websiteString .= "[".$value['timeslot'].", ".($value['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$websiteString = rtrim($websiteString, ",");
		$websiteString .= "]}";

    	$socialSql = "SELECT referer_url, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
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
        for($i=$lastProcessedTimeslot-1; $i<=$statTimeSlot; $i++){
	       	$socialCount = 0;
	        foreach ($social as &$value) {
	       		if(API::isSocialUrl($value['referer_url']) && $i==$value['timeslot']) $socialCount++;
		    }
			$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		               SET traffic = ?, processed = 1 WHERE idsite = ? AND source_id = ? AND timeslot = ? AND date = ?";
			\Piwik\Db::query($insert, array(
		           $socialCount, $idSite, 10, $i, $origin_dt->format('d.m.Y')
			));
		}
		$socialSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = 10
                AND date = ?
                ORDER BY timeslot ASC
                ";
        $social = \Piwik\Db::fetchAll($socialSql, array(
            $idSite, $origin_dt->format('d.m.Y')
        ));
		if (count($social) == 0){
		    $clone = clone $origin_dt;    
			$clone->modify( '-1 day' );
			$socialSql = "SELECT *
	                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
	                WHERE idsite = ?
	                AND source_id = 10
	                AND date = ?
	                ORDER BY timeslot ASC
	                ";
		    $social = \Piwik\Db::fetchAll($socialSql, array(
		    	$idSite, $clone->format('d.m.Y')
	        ));
		}
		$socialString = "\"".Piwik::translate('TrafficSourcesProgression_Social')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Social')."\", \"data\":[";
        foreach ($social as $key=>&$value) {
			$socialString .= "[".$value['timeslot'].", ".($value['traffic']+$website[$key]['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$socialString = rtrim($socialString, ",");
		$socialString .= "]}";

		//Get the historical data
		$historical_dt = clone $origin_dt;    
		$historical_dt->modify( '-1 day' );
		$historicalCampaign = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $historical_dt, Common::REFERRER_TYPE_CAMPAIGN, false);
		$historicalDirect = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $historical_dt, Common::REFERRER_TYPE_DIRECT_ENTRY, false);
		$historicalSearch = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $historical_dt, Common::REFERRER_TYPE_SEARCH_ENGINE, false);
		$historicalWebsite = API::getNumbers($idSite, $minutesToMidnight, $lastMinutes, $refTime, $historical_dt, Common::REFERRER_TYPE_WEBSITE, false);
		$socialSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = 10
                AND date = ?
                ORDER BY timeslot ASC
                ";
        $social = \Piwik\Db::fetchAll($socialSql, array(
            $idSite, $historical_dt->format('d.m.Y')
        ));
		if (count($social) == 0){
		    $clone = clone $historical_dt;    
			$clone->modify( '-1 day' );
			$socialSql = "SELECT *
	                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
	                WHERE idsite = ?
	                AND source_id = 10
	                AND date = ?
	                ORDER BY timeslot ASC
	                ";
		    $social = \Piwik\Db::fetchAll($socialSql, array(
		    	$idSite, $clone->format('d.m.Y')
	        ));
		}
		$historicalString = "\"".Piwik::translate('TrafficSourcesProgression_Historical')."\":{\"data\":[";
        foreach ($social as $key=>&$value) {
			$historicalString .= "[".$value['timeslot'].", ".($value['traffic']+$historicalWebsite[$key]['traffic']+$historicalSearch[$key]['traffic']+$historicalCampaign[$key]['traffic']+$historicalDirect[$key]['traffic'])."],";
		}
		$historicalString = rtrim($historicalString, ",");
		$historicalString .= "], \"shadowSize\":0, \"color\":\"#444\", \"lines\":{\"lineWidth\":1, \"fill\":false}}";

		//return
		$out = "{".$socialString.",".$websiteString.",".$searchString.",".$directString.",".$campaignString.", ".$historicalString."}";
		return $out;
    }

    public static function getSites()
    {
        $idSites = array();
        $sites = Request::processRequest('SitesManager.getSitesWithAtLeastViewAccess');
        if (!empty($sites)) {
            foreach ($sites as $site) {
                $idSites[] = array(
                	"id" => $site['idsite'],
			"name" =>  $site['name']
		);
            }
        }
        return $idSites;
    }
}
