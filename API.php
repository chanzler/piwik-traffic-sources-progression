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
		date_default_timezone_set(Site::getTimezoneFor($idSite));
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
        $campaignToday=0;
        foreach ($campaign as &$value) {
			if (date("d", $value['timeslot']*1200)==date("d")){
				$campaignString .= "[".$value['timeslot'].", ".$value['traffic']."],";
				$campaignToday++;
			}
		}
        for($i=(round((time()+$timeZoneDiff)/1200)-(72-$campaignToday))-1; $i<round((time()+$timeZoneDiff)/1200); $i++){
				$campaignString .= "[".$i.", 0],";
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
		$directString = "\"".Piwik::translate('TrafficSourcesProgression_Direct')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Direct')."\", \"data\":[";
        $directToday=0;
        foreach ($direct as $key=>&$value) {
			if (date("d", $value['timeslot']*1200)==date("d")){
				$directString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic'])."],";
				$directToday++;
			}
		}
        for($i=(round((time()+$timeZoneDiff)/1200)-(72-$directToday))-1; $i<round((time()+$timeZoneDiff)/1200); $i++){
				$directString .= "[".$i.", 0],";
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
		$searchString = "\"".Piwik::translate('TrafficSourcesProgression_Search')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Search')."\", \"data\":[";
        $searchToday=0;
        foreach ($search as $key=>&$value) {
			if (date("d", $value['timeslot']*1200)==date("d")){
				$searchString .= "[".$value['timeslot'].", ".($value['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
				$searchToday++;
			}
		}
        for($i=(round((time()+$timeZoneDiff)/1200)-(72-$searchToday))-1; $i<round((time()+$timeZoneDiff)/1200); $i++){
				$searchString .= "[".$i.", 0],";
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
		$websiteString = "\"".Piwik::translate('TrafficSourcesProgression_Links')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Links')."\", \"data\":[";
        $websiteToday=0;
        foreach ($website as $key=>&$value) {
			if (date("d", $value['timeslot']*1200)==date("d")){
				$websiteString .= "[".$value['timeslot'].", ".($value['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
				$websiteToday++;
			}
		}
        for($i=(round((time()+$timeZoneDiff)/1200)-(72-$websiteToday))-1; $i<round((time()+$timeZoneDiff)/1200); $i++){
				$websiteString .= "[".$i.", 0],";
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
		$socialString = "\"".Piwik::translate('TrafficSourcesProgression_Social')."\":{\"label\":\"".Piwik::translate('TrafficSourcesProgression_Social')."\", \"data\":[";
        $socialToday=0;
        foreach ($social as $key=>&$value) {
			if (date("d", $value['timeslot']*1200)==date("d")){
				$socialString .= "[".$value['timeslot'].", ".($value['traffic']+$website[$key]['traffic']+$search[$key]['traffic']+$campaign[$key]['traffic']+$direct[$key]['traffic'])."],";
				$socialToday++;
			}
		}
        for($i=(round((time()+$timeZoneDiff)/1200)-(72-$socialToday))-1; $i<round((time()+$timeZoneDiff)/1200); $i++){
				$socialString .= "[".$i.", 0],";
		}
		$socialString = rtrim($socialString, ",");
		$socialString .= "]}";

		$out = "{".$socialString.",".$websiteString.",".$searchString.",".$directString.",".$campaignString."}";
		//$out = '{"Social":{"label":"Social", "data":[[1179059, 0],[1179060, 7],[1179061, 5],[1179062, 0],[1179063, 7],[1179064, 0],[1179065, 1],[1179066, 0],[1179067, 0],[1179068, 1],[1179069, 0],[1179070, 0],[1179071, 1],[1179072, 0],[1179073, 0],[1179074, 0],[1179075, 1],[1179076, 0],[1179077, 0],[1179078, 0],[1179079, 0],[1179080, 0],[1179081, 0],[1179082, 0],[1179083, 0],[1179084, 1],[1179085, 0],[1179086, 0],[1179087, 0],[1179088, 1],[1179089, 1],[1179090, 0],[1179091, 2],[1179092, 1],[1179093, 4],[1179094, 5],[1179095, 5],[1179096, 4],[1179097, 2],[1179098, 1],[1179099, 2],[1179100, 1],[1179101, 4],[1179102, 5],[1179103, 4],[1179104, 0],[1179105, 3],[1179106, 2],[1179107, 1],[1179108, 5],[1179109, 4],[1179110, 2],[1179111, 1],[1179112, 7],[1179113, 3],[1179114, 3],[1179115, 4],[1179116, 1],[1179117, 3],[1179118, 4],[1179119, 9],[1179120, 4],[1179121, 3],[1179122, 5],[1179123, 7],[1179124, 5],[1179125, 5],[1179126, 2],[1179127, 4],[1179128, 1],[1179129, 0],[1179130, 0]]},
		//"Links":{"label":"Links", "data":[[1179059, 0],[1179060, 4],[1179061, 5],[1179062, 0],[1179063, 1],[1179064, 0],[1179065, 1],[1179066, 0],[1179067, 0],[1179068, 1],[1179069, 0],[1179070, 0],[1179071, 1],[1179072, 0],[1179073, 0],[1179074, 0],[1179075, 1],[1179076, 0],[1179077, 0],[1179078, 0],[1179079, 0],[1179080, 0],[1179081, 0],[1179082, 0],[1179083, 0],[1179084, 1],[1179085, 0],[1179086, 0],[1179087, 0],[1179088, 1],[1179089, 1],[1179090, 0],[1179091, 2],[1179092, 1],[1179093, 4],[1179094, 5],[1179095, 5],[1179096, 4],[1179097, 2],[1179098, 1],[1179099, 2],[1179100, 1],[1179101, 4],[1179102, 5],[1179103, 4],[1179104, 0],[1179105, 3],[1179106, 2],[1179107, 1],[1179108, 5],[1179109, 4],[1179110, 2],[1179111, 1],[1179112, 7],[1179113, 3],[1179114, 3],[1179115, 4],[1179116, 1],[1179117, 3],[1179118, 4],[1179119, 9],[1179120, 4],[1179121, 3],[1179122, 5],[1179123, 7],[1179124, 5],[1179125, 5],[1179126, 2],[1179127, 4],[1179128, 1],[1179129, 0],[1179130, 0]]},
		//"Suche":{"label":"Suche", "data":[[1179059, 0],[1179060, 4],[1179061, 5],[1179062, 0],[1179063, 1],[1179064, 0],[1179065, 1],[1179066, 0],[1179067, 0],[1179068, 1],[1179069, 0],[1179070, 0],[1179071, 1],[1179072, 0],[1179073, 0],[1179074, 0],[1179075, 1],[1179076, 0],[1179077, 0],[1179078, 0],[1179079, 0],[1179080, 0],[1179081, 0],[1179082, 0],[1179083, 0],[1179084, 1],[1179085, 0],[1179086, 0],[1179087, 0],[1179088, 1],[1179089, 1],[1179090, 0],[1179091, 2],[1179092, 1],[1179093, 4],[1179094, 5],[1179095, 5],[1179096, 4],[1179097, 1],[1179098, 1],[1179099, 2],[1179100, 1],[1179101, 4],[1179102, 5],[1179103, 4],[1179104, 0],[1179105, 3],[1179106, 2],[1179107, 1],[1179108, 5],[1179109, 4],[1179110, 2],[1179111, 1],[1179112, 7],[1179113, 3],[1179114, 3],[1179115, 4],[1179116, 1],[1179117, 3],[1179118, 4],[1179119, 9],[1179120, 4],[1179121, 3],[1179122, 5],[1179123, 6],[1179124, 5],[1179125, 5],[1179126, 2],[1179127, 4],[1179128, 1],[1179129, 0],[1179130, 0]]},
		//"Direkt":{"label":"Direkt", "data":[[1179059, 0],[1179060, 1],[1179061, 0],[1179062, 0],[1179063, 0],[1179064, 0],[1179065, 0],[1179066, 0],[1179067, 0],[1179068, 0],[1179069, 0],[1179070, 0],[1179071, 0],[1179072, 0],[1179073, 0],[1179074, 0],[1179075, 0],[1179076, 0],[1179077, 0],[1179078, 0],[1179079, 0],[1179080, 0],[1179081, 0],[1179082, 0],[1179083, 0],[1179084, 1],[1179085, 0],[1179086, 0],[1179087, 0],[1179088, 0],[1179089, 0],[1179090, 0],[1179091, 1],[1179092, 0],[1179093, 1],[1179094, 2],[1179095, 0],[1179096, 0],[1179097, 0],[1179098, 1],[1179099, 1],[1179100, 0],[1179101, 1],[1179102, 0],[1179103, 2],[1179104, 0],[1179105, 2],[1179106, 0],[1179107, 0],[1179108, 1],[1179109, 1],[1179110, 0],[1179111, 0],[1179112, 2],[1179113, 0],[1179114, 1],[1179115, 2],[1179116, 0],[1179117, 0],[1179118, 0],[1179119, 0],[1179120, 1],[1179121, 0],[1179122, 0],[1179123, 1],[1179124, 0],[1179125, 0],[1179126, 0],[1179127, 0],[1179128, 0],[1179129, 0],[1179130, 0]]},
		//"Kampagnen":{"label":"Kampagnen", "data":[[1179059, 0],[1179060, 0],[1179061, 0],[1179062, 0],[1179063, 0],[1179064, 0],[1179065, 0],[1179066, 0],[1179067, 0],[1179068, 0],[1179069, 0],[1179070, 0],[1179071, 0],[1179072, 0],[1179073, 0],[1179074, 0],[1179075, 0],[1179076, 0],[1179077, 0],[1179078, 0],[1179079, 0],[1179080, 0],[1179081, 0],[1179082, 0],[1179083, 0],[1179084, 0],[1179085, 0],[1179086, 0],[1179087, 0],[1179088, 0],[1179089, 0],[1179090, 0],[1179091, 0],[1179092, 0],[1179093, 0],[1179094, 0],[1179095, 0],[1179096, 0],[1179097, 0],[1179098, 0],[1179099, 0],[1179100, 0],[1179101, 0],[1179102, 0],[1179103, 0],[1179104, 0],[1179105, 0],[1179106, 0],[1179107, 0],[1179108, 0],[1179109, 0],[1179110, 0],[1179111, 0],[1179112, 0],[1179113, 0],[1179114, 0],[1179115, 0],[1179116, 0],[1179117, 0],[1179118, 0],[1179119, 0],[1179120, 0],[1179121, 0],[1179122, 0],[1179123, 0],[1179124, 0],[1179125, 0],[1179126, 0],[1179127, 0],[1179128, 0],[1179129, 0],[1179130, 0]]}}';
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
