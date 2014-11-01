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
		$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
		$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
		$origin_dt = new \DateTime("now", $origin_dtz);
		$refTime = $origin_dt->format('Y-m-d H:i:s');
        $campaignSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_CAMPAIGN."
                ORDER BY timeslot ASC
                ";
        $campaign = \Piwik\Db::fetchAll($campaignSql, array(
            $idSite
        ));
		$campaignString = "\"".Piwik::translate('TrafficSources_Campaign')."\":{\"label\":\"".Piwik::translate('TrafficSources_Campaign')."\", \"data\":[";
        foreach ($campaign as &$value) {
			$campaignString .= "[".$value['timeslot'].", ".$value['traffic']."],";
		}
		$campaignString = rtrim($campaignString, ",");
		$campaignString .= "]}";

        $directSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
                ORDER BY timeslot ASC
                ";
        $direct = \Piwik\Db::fetchAll($directSql, array(
            $idSite
        ));
		$directString = "\"".Piwik::translate('TrafficSources_Direct')."\":{\"label\":\"".Piwik::translate('TrafficSources_Direct')."\", \"data\":[";
        foreach ($direct as $key=>&$value) {
			$directString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic'])."],";
		}
		$directString = rtrim($directString, ",");
		$directString .= "]}";
		
        $searchSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
                ORDER BY timeslot ASC
                ";
        $search = \Piwik\Db::fetchAll($searchSql, array(
            $idSite
        ));
		$searchString = "\"".Piwik::translate('TrafficSources_Search')."\":{\"label\":\"".Piwik::translate('TrafficSources_Search')."\", \"data\":[";
        foreach ($search as $key=>&$value) {
			$searchString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$searchString = rtrim($searchString, ",");
		$searchString .= "]}";

        $websiteSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_WEBSITE."
                ORDER BY timeslot ASC
                ";
        $website = \Piwik\Db::fetchAll($websiteSql, array(
            $idSite
        ));
		$websiteString = "\"".Piwik::translate('TrafficSources_Links')."\":{\"label\":\"".Piwik::translate('TrafficSources_Links')."\", \"data\":[";
        foreach ($website as $key=>&$value) {
			$websiteString .= "[".$value['timeslot'].", ".($value['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$websiteString = rtrim($websiteString, ",");
		$websiteString .= "]}";

        $socialSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = 10
                ORDER BY timeslot ASC
                ";
        $social = \Piwik\Db::fetchAll($socialSql, array(
            $idSite
        ));
		$socialString = "\"".Piwik::translate('TrafficSources_Social')."\":{\"label\":\"".Piwik::translate('TrafficSources_Social')."\", \"data\":[";
        foreach ($social as $key=>&$value) {
			$socialString .= "[".$value['timeslot'].", ".($value['traffic']+$website[$key]['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
		}
		$socialString = rtrim($socialString, ",");
		$socialString .= "]}";

		$out = "{".$socialString.",".$websiteString.",".$searchString.",".$directString.",".$socialString.",".$campaignString."}";
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
