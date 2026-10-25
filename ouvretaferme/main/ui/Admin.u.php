<?php
namespace main;

Class AdminUi {

	use \Notifiable;

	public function __construct() {
		\Asset::css('main', 'admin.css');
		\Asset::js('main', 'adminAi.js');
	}

	/**
	 * L'en-tête d'une page d'administration : la navigation, puis ce que la page y ajoute
	 * (sa sous-navigation, sa recherche), en HTML déjà rendu.
	 */
	public function getHeader(string $selection, string $more = ''): string {
		return '<div class="admin-navigation stick-xs">'.$this->getNavigation($selection).$more.'</div>';
	}

	/**
	 * Get navigation in admin
	 */
	public function getNavigation(string $selection): string {

		$links = [
			'user' => ['/user/admin/', \Asset::icon('people-fill').'<div class="base-nav-link-legend">'.s("Utilisateur").'</div>'],
			'structure' => ['/structure/admin/', \Asset::icon('building-fill').'<div class="base-nav-link-legend">'.s("Structure").'</div>'],
		];

		if(
			\structure\StructureSetting::hasAdminPackage() or
			\structure\StructureSetting::hasCommercialisationApp()
		) {
			$links += array_merge(...self::notify('getAdminLinks'));
		}

		$links += [
			'communication' => ['/communication/admin/noteAi', \Asset::icon('bell-fill').'<div class="base-nav-link-legend">'.s("Notifications").'</div>'],
			'learning' => ['/learning/admin/course', \Asset::icon('mortarboard-fill').'<div class="base-nav-link-legend">'.s("Formations").'</div>'],
			'dev' => ['/dev/admin/', \Asset::icon('code-slash').'<div class="base-nav-link-legend">'.s("Dev").'</div>'],
		];

		if(\learning\LearningSetting::$active === FALSE) {
			unset($links['learning']);
		}

		// Le lien des statistiques est réécrit au rendu vers la dernière entrée consultée : sans quoi
		// l'écran s'ouvre toujours sur le volume d'affaires, quel que soit ce qu'on y suit
		$h = '<div class="nav" '.attr('onrender', 'MainAdminAi.restoreStatistics(this)').'>';

			foreach($links as $key => [$href, $name]) {
				$h .= '<a href="'.$href.'" '.attr('data-admin-link', $key).' class="base-nav-link text-center '.($selection === $key ? 'active' : '').'">'.$name.'</a>';
			}

		$h .= '</div>';

		return $h;

	}

}
?>
