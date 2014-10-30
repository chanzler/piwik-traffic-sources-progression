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
        $directSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
                ";
        $direct = \Piwik\Db::fetchAll($directSql, array(
            $idSite
        ));
		$directString = "\"".Piwik::translate('TrafficSources_Direct')."\":{\"label\":\"".Piwik::translate('TrafficSources_Direct')."\", \"data\":[";
        foreach ($direct as &$value) {
			$directString .= "[".$value['timeslot'].", ".$value['traffic']."],";
		}
		$directString = rtrim($directString, ",");
		$directString .= "]}";
		
        $searchSql = "SELECT *
                FROM " . \Piwik\Common::prefixTable("trafficsourcesprogression_sources") . "
                WHERE idsite = ?
                AND source_id = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
                ";
        $search = \Piwik\Db::fetchAll($searchSql, array(
            $idSite
        ));
		$searchString = "\"".Piwik::translate('TrafficSources_Search')."\":{\"label\":\"".Piwik::translate('TrafficSources_Search')."\", \"data\":[";
        foreach ($search as &$value) {
			$searchString .= "[".$value['timeslot'].", ".$value['traffic']."],";
		}
		$searchString = rtrim($searchString, ",");
		$searchString .= "]}";


		$out = "{".$directString.",".$searchString."}";
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
