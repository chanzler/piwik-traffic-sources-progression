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
        
    	$campaignSql = "SELECT COUNT(idvisit) AS number, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".Common::REFERRER_TYPE_CAMPAIGN."
		                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
		                ";
		$campaign = \Piwik\Db::fetchAll($campaignSql, array(
				$minutesToMidnight/20, $idSite, ($minutesToMidnight<60)?$minutesToMidnight:80, $lastMinutes * 60
		));
		$index=0;
		foreach ($campaign as &$value) {
			if ($index > 0 || $minutesToMidnight < 20){
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
						$value['number'], $idSite, Common::REFERRER_TYPE_CAMPAIGN, $value['timeslot']
				));
			}
			$index++;
		}
        $campaignSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "	
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_CAMPAIGN."
                ORDER BY timeslot ASC
                ";
        $campaign = \Piwik\Db::fetchAll($campaignSql, array(
            $idSite
        ));
		$campaignString = "\"".Piwik::translate('TrafficSourcesProgression_Campaign')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Campaign')."\", \"data\":[";
        foreach ($campaign as &$value) {
			$campaignString .= "[".$value['timeslot'].", ".$value['traffic']."],";
		}
		$campaignString = rtrim($campaignString, ",");
		$campaignString .= "]}";

		$directSql = "SELECT COUNT(idvisit) AS number, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
		                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
		                ";
		$direct = \Piwik\Db::fetchAll($directSql, array(
				$minutesToMidnight/20, $idSite, ($minutesToMidnight<60)?$minutesToMidnight:80, $lastMinutes * 60
		));
		$index=0;
		foreach ($direct as &$value) {
			if ($index > 0 || $minutesToMidnight < 20){
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
						$value['number'], $idSite, Common::REFERRER_TYPE_DIRECT_ENTRY, $value['timeslot']
				));
			}
			$index++;
		}
        $directSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
                ORDER BY timeslot ASC
                ";
        $direct = \Piwik\Db::fetchAll($directSql, array(
            $idSite
        ));
		$directString = "\"".Piwik::translate('TrafficSourcesProgression_Direct')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Direct')."\", \"data\":[";
        foreach ($direct as $key=>&$value) {
			$directString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic'])."],";
		}
		$directString = rtrim($directString, ",");
		$directString .= "]}";
		
    	$searchSql = "SELECT COUNT(idvisit) AS number, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
		                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
		                ";
		$search = \Piwik\Db::fetchAll($searchSql, array(
				$minutesToMidnight/20, $idSite, ($minutesToMidnight<60)?$minutesToMidnight:80, $lastMinutes * 60
		));
		$index=0;
		foreach ($search as &$value) {
			if ($index > 0 || $minutesToMidnight < 20){
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
				                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
						$value['number'], $idSite, Common::REFERRER_TYPE_SEARCH_ENGINE, $value['timeslot']
				));
			}
			$index++;
		}
		$searchSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
                ORDER BY timeslot ASC
                ";
        $search = \Piwik\Db::fetchAll($searchSql, array(
            $idSite
        ));
		$searchString = "\"".Piwik::translate('TrafficSourcesProgression_Search')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Search')."\", \"data\":[";
        foreach ($search as $key=>&$value) {
			$searchString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$searchString = rtrim($searchString, ",");
		$searchString .= "]}";

    	$websiteSql = "SELECT COUNT(idvisit) AS number, round(round(UNIX_TIMESTAMP(visit_first_action_time) /1200) - @timenum  + @rownum) AS timeslot
		                FROM " . \Piwik\Common::prefixTable("log_visit") . "
						cross join (select @timenum := round(UNIX_TIMESTAMP('".$refTime."') /1200)) r
						cross join (select @rownum := ?) s
		                WHERE idsite = ?
		                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_first_action_time
		                AND referer_type = ".Common::REFERRER_TYPE_WEBSITE."
		                GROUP BY round(UNIX_TIMESTAMP(visit_first_action_time) / ?)
		                ";
		$website = \Piwik\Db::fetchAll($websiteSql, array(
				$minutesToMidnight/20, $idSite, ($minutesToMidnight<60)?$minutesToMidnight:80, $lastMinutes * 60
		));
		$index=0;
		foreach ($website as &$value) {
			if ($index > 0 || $minutesToMidnight < 20){
				$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		                     SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
				\Piwik\Db::query($insert, array(
						$value['number'], $idSite, Common::REFERRER_TYPE_WEBSITE, $value['timeslot']
				));
			}
			$index++;
		}		
		$websiteSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_WEBSITE."
                ORDER BY timeslot ASC
                ";
        $website = \Piwik\Db::fetchAll($websiteSql, array(
            $idSite
        ));
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
				$minutesToMidnight/20, $idSite, ($minutesToMidnight<60)?$minutesToMidnight:100
	    ));
	    for($i=($minutesToMidnight/20)-3; $i<=($minutesToMidnight/20); $i++){
	       	$socialCount = 0;
	        foreach ($social as &$value) {
	       		if(API::isSocialUrl($value['referer_url']) && $i==$value['timeslot']) $socialCount++;
		    }
			$insert = "UPDATE ". \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
		               SET traffic = ? WHERE idsite = ? AND source_id = ? AND timeslot = ?";
			\Piwik\Db::query($insert, array(
		           $socialCount, $idSite, 10, $i
			));
		}
		$socialSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = 10
                ORDER BY timeslot ASC
                ";
        $social = \Piwik\Db::fetchAll($socialSql, array(
            $idSite
        ));
		$socialString = "\"".Piwik::translate('TrafficSourcesProgression_Social')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Social')."\", \"data\":[";
        foreach ($social as $key=>&$value) {
			$socialString .= "[".$value['timeslot'].", ".($value['traffic']+$website[$key]['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$socialString = rtrim($socialString, ",");
		$socialString .= "]}";

		$out = "{".$socialString.",".$websiteString.",".$searchString.",".$directString.",".$campaignString."}";
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
