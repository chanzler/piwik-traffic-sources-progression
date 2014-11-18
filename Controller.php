<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 */
namespace Piwik\Plugins\TrafficSourcesProgression;

use Piwik\View;
use Piwik\Piwik;
use Piwik\Common;
use Piwik\Settings\SystemSetting;
use Piwik\Settings\UserSetting;
use Piwik\Settings\Manager as SettingsManager;

/**
 *
 */
class Controller extends \Piwik\Plugin\Controller {

	private function getPluginSettings() {
		$pluginsSettings = SettingsManager::getPluginSettingsForCurrentUser();
		ksort($pluginsSettings);
		return $pluginsSettings;
	}

	public function index() {
		$settings = new Settings('TrafficSourcesProgression');

		$view = new View('@TrafficSourcesProgression/index.twig');
		$this -> setBasicVariablesView($view);
		$view -> idSite = $this -> idSite;
		$view -> refreshInterval = (int)$settings -> refreshInterval -> getValue();

		return $view -> render();
	}

}
