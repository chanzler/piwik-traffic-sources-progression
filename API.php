<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSources;

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

	private static function isSocialUrl($url, $socialName = false)
	{
		foreach (Common::getSocialUrls() as $domain => $name) {
	
			if (preg_match('/(^|[\.\/])'.$domain.'([\.\/]|$)/', $url) && ($socialName === false || $name == $socialName)) {
	
				return true;
			}
		}
	
		return false;
	}
	
	private static function get_timezone_offset($remote_tz, $origin_tz = null) {
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
    public static function getTrafficSources($idSite, $lastMinutes=20)
    {
        \Piwik\Piwik::checkUserHasViewAccess($idSite);
		$timeZoneDiff = API::get_timezone_offset('UTC', Site::getTimezoneFor($idSite));
		$origin_dtz = new \DateTimeZone(Site::getTimezoneFor($idSite));
		$origin_dt = new \DateTime("now", $origin_dtz);
		$refTime = $origin_dt->format('Y-m-d H:i:s');
        $directSql = "SELECT COUNT(*)
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type = ".Common::REFERRER_TYPE_DIRECT_ENTRY."
                ";
        $direct = \Piwik\Db::fetchOne($directSql, array(
            $idSite, $lastMinutes+($timeZoneDiff/60)
        ));

        $searchSql = "SELECT COUNT(*)
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type = ".Common::REFERRER_TYPE_SEARCH_ENGINE."
                ";
        $search = \Piwik\Db::fetchOne($searchSql, array(
            $idSite, $lastMinutes+($timeZoneDiff/60)
        ));

        $campaignSql = "SELECT COUNT(*)
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type = ".Common::REFERRER_TYPE_CAMPAIGN."
                ";
        $campaign = \Piwik\Db::fetchOne($campaignSql, array(
        		$idSite, $lastMinutes+($timeZoneDiff/60)
        ));
        
        $websiteSql = "SELECT COUNT(*)
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type = ".Common::REFERRER_TYPE_WEBSITE."
                ";
        $website = \Piwik\Db::fetchOne($websiteSql, array(
        		$idSite, $lastMinutes+($timeZoneDiff/60)
        ));

        $socialSql = "SELECT referer_url
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type = ".Common::REFERRER_TYPE_WEBSITE."
                ";
                
        $social = \Piwik\Db::fetchAll($socialSql, array(
        		$idSite, $lastMinutes+($timeZoneDiff/60)
        ));
        $socialCount = 0;
        foreach ($social as &$value) {
        	if(API::isSocialUrl($value['referer_url'])) $socialCount++;
        }

/*        $sql = "SELECT referer_url, referer_type
                FROM " . \Piwik\Common::prefixTable("log_visit") . "
                WHERE idsite = ?
                AND DATE_SUB('".$refTime."', INTERVAL ? MINUTE) < visit_last_action_time
                AND referer_type IN (".Common::REFERRER_TYPE_WEBSITE.",	".Common::REFERRER_TYPE_CAMPAIGN.",	".Common::REFERRER_TYPE_SEARCH_ENGINE.",".Common::REFERRER_TYPE_DIRECT_ENTRY.")
                ";
                
        $result = \Piwik\Db::fetchAll($sql, array(
        		$idSite, $lastMinutes+($timeZoneDiff/60)
        ));
        $socialCount = 0;
        $direct = 0;
        $search = 0;
        $campaign = 0;
        $website = 0;
        foreach ($result as &$value) {
        	if (API::isSocialUrl($value['referer_url'])) $socialCount++;
        	if ($value['referer_type'] == Common::REFERRER_TYPE_WEBSITE) $website++;
        	if ($value['referer_type'] == Common::REFERRER_TYPE_CAMPAIGN) $campaign++;
           	if ($value['referer_type'] == Common::REFERRER_TYPE_SEARCH_ENGINE) $search++;
           	if ($value['referer_type'] == Common::REFERRER_TYPE_DIRECT_ENTRY) $direct++;
        }
*/
        $totalVisits = (int)$direct+$search+$campaign+$website;
        return array(
        	array('id'=>1, 'name'=>Piwik::translate('TrafficSources_Direct'), 'value'=>$direct, 'percentage'=>($totalVisits==0)?0:round($direct/$totalVisits*100,1)),
        	array('id'=>2, 'name'=>Piwik::translate('TrafficSources_Search'), 'value'=>$search, 'percentage'=>($totalVisits==0)?0:round($search/$totalVisits*100,1)),
        	array('id'=>3, 'name'=>Piwik::translate('TrafficSources_Campaign'), 'value'=>$campaign, 'percentage'=>($totalVisits==0)?0:round($campaign/$totalVisits*100,1)),
        	array('id'=>4, 'name'=>Piwik::translate('TrafficSources_Links'), 'value'=>$website, 'percentage'=>($totalVisits==0)?0:round(($website-$socialCount)/$totalVisits*100,1)), //subtract socials
        	array('id'=>5, 'name'=>Piwik::translate('TrafficSources_Social'), 'value'=>$socialCount, 'percentage'=>($totalVisits==0)?0:round($socialCount/$totalVisits*100,1))
        );
/*        return array(
        	array('id'=>1, 'name'=>Piwik::translate('TrafficSources_Direct'), 'value'=>(int)68, 'percentage'=>68),
        	array('id'=>2, 'name'=>Piwik::translate('TrafficSources_Search'), 'value'=>(int)20, 'percentage'=>20),
        	array('id'=>3, 'name'=>Piwik::translate('TrafficSources_Campaign'), 'value'=>(int)2, 'percentage'=>3),
        	array('id'=>4, 'name'=>Piwik::translate('TrafficSources_Links'), 'value'=>(int)80-(int)3, 'percentage'=>73), //subtract socials
        	array('id'=>5, 'name'=>Piwik::translate('TrafficSources_Social'), 'value'=>(int)32, 'percentage'=>32)
        );*/
    }

}
