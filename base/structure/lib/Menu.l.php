<?php
namespace structure;

/**
 * Ce que les menus d'univers ouvrent à un membre, sans rien de leur présentation : les univers, leurs
 * onglets et les sous-entrées de chaque onglet. `MenuUi` les lit, par la structure (`menus?`,
 * `menuCategories?`), pour savoir quoi rendre, et garde les noms, les icônes et les adresses.
 *
 * Ce que le code ouvert ne peut pas lire n'y est pas : l'univers de la comptabilité, dont les entrées
 * dépendent en plus de l'exercice choisi dans le menu (`MenuUi::getAccountingSection()`), et
 * l'abonnement. `MenuUi` les garde.
 */
class MenuLib {

	/**
	 * Les univers ouverts dans l'ordre du rail, et les onglets ouverts dans l'ordre de leur univers. Un
	 * univers ouvert y est même sans onglet, et un onglet ouvert même sans sous-entrée : le menu rend
	 * l'un et l'autre, l'onglet d'analyse grisé.
	 *
	 * @return array [univers => [onglet, …]]
	 */
	public static function getMenus(Structure $eStructure): array {

		$menus = [];

		if($eStructure->assertProduction()) {
			$menus['production'] = self::getProductionMenus($eStructure);
		}

		if($eStructure->assertCommercialisation()) {
			$menus['commercialisation'] = self::getCommercialisationMenus($eStructure);
		}

		$menus['settings'] = self::getSettingsMenus($eStructure);

		return $menus;

	}

	private static function getProductionMenus(Structure $eStructure): array {

		$menus = [];

		if($eStructure->canPlanning() or $eStructure->canPlanningLimited()) {
			$menus[] = 'planning';
			$menus[] = 'planning-v2';
		}

		if($eStructure->acceptCultivation()) {
			$menus[] = 'cultivation';
		}

		if(
			$eStructure->acceptVegetable() and
			$eStructure->canManage()
		) {
			$menus[] = 'intelligence-vegetable';
		}

		if($eStructure->canWrite()) {
			$menus[] = 'toolbox';
		}

		if($eStructure->canAnalyzeProduction()) {
			$menus[] = 'analyze-production';
		}

		return $menus;

	}

	/**
	 * Les menus de l'AMAP en vue groupe et les pages de chacun, pour le compte connecté. Le bureau y
	 * gère l'AMAP, un adhérent n'y a que son espace, un référent hors bureau y ajoute ses producteurs,
	 * et un compte du bureau qui est aussi adhérent reçoit les deux. Une page n'y entre que sous la
	 * garde de sa route : le menu reflète les portes, il n'en pose aucune.
	 *
	 * Les clés sont au singulier, pour ne pas se lire comme les blocs du tableau de bord de l'AMAP
	 * (`amap-distributions`).
	 *
	 * @return array [menu => [page, …]], un menu sans page menant à sa seule adresse
	 */
	public static function getAmapGroupMenus(Structure $eStructure): array {

		$space = StructureLib::getAmapSpace($eStructure);

		$menus = [];

		if($eStructure->canManage() === FALSE) {

			if($space !== []) {
				$menus['amap-space'] = $space;
			}

			// Une clé à part de la page Référents du bureau, que sa route garde par `canManage()` :
			// le référent n'y ouvre que les producteurs qu'il suit.
			if(StructureLib::isAmapReferent($eStructure)) {
				$menus['amap-referent-space'] = [];
			}

			return $menus;

		}

		$menus['amap-dashboard'] = [];

		$menus['amap-distribution'] = ['distribution'];

		$menus['amap-producer'] = ['producer', 'wallet', 'referent'];

		if($eStructure->canCommunication()) {
			$menus['communications'] = self::getCategories($eStructure, 'communications');
		}

		$menus['amap-customer'] = ['customer'];

		if(StructureLib::hasAmapFeature($eStructure, 'form')) {
			$menus['amap-customer'][] = 'membershipRequest';
		}

		if(StructureLib::hasAmapFeature($eStructure, 'membership')) {
			$menus['amap-customer'][] = 'membership';
		}

		// Une permanence est tenue par des adhérents : la page vit avec eux, pas avec le calendrier.
		if(StructureLib::hasAmapFeature($eStructure, 'duties')) {
			$menus['amap-customer'][] = 'duty';
		}

		if($space !== []) {
			$menus['amap-space'] = $space;
		}

		// Paramètres est une seule page, qui mène à chaque réglage de l'AMAP.
		$menus['settings'] = [];

		return $menus;

	}

	private static function getCommercialisationMenus(Structure $eStructure): array {

		$menus = [];

		if($eStructure->canSelling()) {

			$menus[] = 'selling';

			if($eStructure->canCommunication()) {
				$menus[] = 'communications';
			}

		}

		if($eStructure->canAnalyzeCommercialisation()) {
			$menus[] = 'analyze-commercialisation';
		}

		return $menus;

	}

	private static function getSettingsMenus(Structure $eStructure): array {

		$menus = [];

		if($eStructure->canManage()) {

			$menus[] = 'structure';

			if($eStructure->isAmap() === FALSE) {
				$menus[] = 'team';
			}

		}

		if(self::getCategories($eStructure, 'feature') !== []) {
			$menus[] = 'feature';
		}

		// L'onglet porte les intégrations à côté des imports et des exports, et une structure sans
		// identité légale, qui n'a pas les seconds, garde les premières.
		if($eStructure->canManage() and self::getCategories($eStructure, 'data') !== []) {
			$menus[] = 'data';
		}

		return $menus;

	}

	/**
	 * Les sous-entrées d'un onglet que le membre peut ouvrir, hors comptabilité.
	 */
	public static function getCategories(Structure $eStructure, string $menu): array {

		switch($menu) {

			case 'planning' :

				return [Member::DAILY, Member::WEEKLY, Member::YEARLY];

			case 'cultivation' :

				$categories = ['series', 'soil', 'forecast', 'seedling', 'sequence', 'map'];

				if($eStructure->canAnalyzeProduction() === FALSE) {
					array_delete($categories, 'forecast');
					array_delete($categories, 'seedling');
				}

				return $categories;

			case 'intelligence-vegetable' :

				return ['intelligence-crop-plan', 'intelligence-variety'];

			case 'toolbox' :
				return $eStructure['cFavorite?']()->getKeys();

			case 'analyze-production' :

				$categories = [];

				if(
					$eStructure->conf(Feature::class)['hasCultivations'] and
					$eStructure->conf()['time']
				) {
					$categories[] = 'working-time';
				}

				if($eStructure->conf(Feature::class)['hasCultivations']) {
					$categories[] = 'cultivation';
				}

				return $categories;

			case 'selling' :

				$categories = ['sale'];

				if(
					$eStructure->conf(Feature::class)['hasSales'] and
					$eStructure->assertInvoice()
				) {
					$categories[] = 'invoice';
				}

				$categories[] = 'customer';

				if($eStructure->isLegal()) {

					if(StructureLib::hasMenuWallet($eStructure)) {
						$categories[] = 'wallet';
					}

					$categories[] = 'product';

					if($eStructure->conf(Feature::class)['hasStock']) {
						$categories[] = 'stock';
					}

					$categories[] = 'catalog';

				}

				if($eStructure->assertShop()) {
					$categories[] = 'shop';
				}

				if($eStructure->assertAmapProducer()) {
					$categories[] = 'amap';
				}

				if($eStructure->isLegal()) {
					$categories[] = 'market';
				}

				return $categories;

			case 'communications' :
				return ['website', 'mailing'];

			case 'analyze-commercialisation' :

				$categories = [];

				if($eStructure->conf(Feature::class)['hasSales']) {
					$categories[] = 'sales';
				}

				if(
					StructureSetting::hasAnalyzePackage() and
					$eStructure->conf(Feature::class)['hasCultivations'] and
					$eStructure->conf(Feature::class)['hasSales']
				) {
					$categories[] = 'report';
				}

				// Les relevés de prix ne cotent que du végétal vendu au poids ou à la pièce :
				// l'ouvrir à un éleveur lui ferait ouvrir une page où aucune de ses
				// productions n'apparaît.
				if($eStructure->acceptQuotation()) {
					$categories[] = 'quotation';
				}

				return $categories;

			case 'structure':

				$categories = [];

				$categories[] = 'general';

				// Une AMAP n'a pas de fiche publique (acceptReadProfile()). Pour les autres, sans
				// condition : une ferme non assujettie a un profil comme les autres, et rien de ce
				// que porte cette page ne dépend du régime fiscal.
				if($eStructure->isAmap()) {
					$categories[] = 'team';
				} else {
					$categories[] = 'profile';
				}

				if($eStructure->acceptVat()) {
					$categories[] = 'vat';
				}

				// Les interrupteurs de la production, du temps de travail et de la RH restent atteignables production
				// éteinte : c'est là qu'on la rallume.
				if($eStructure->acceptProduction() and $eStructure->canManage()) {
					$categories[] = 'features';
				}

				return $categories;

			case 'feature':

				$categories = [];

				if($eStructure->assertProduction() and $eStructure->canManage()) {
					$categories[] = 'settings-production';
				}

				if($eStructure->acceptFeatureSection('amap') and $eStructure->canManage()) {
					$categories[] = 'settings-amap';
				}

				// Une AMAP règle ses distributions sur la page de ses fonctionnalités, celle de
				// `settings-amap` : une seconde entrée mènerait au même écran.
				if(
					$eStructure->acceptCommercialisation() and
					$eStructure->canSettingsCommercialisation() and
					$eStructure->isAmap() === FALSE
				) {
					$categories[] = 'settings-commercialisation';
				}

				if(
					$eStructure->acceptFeatureSection('amap') and
					$eStructure->canManage() and
					StructureLib::hasAmapFeature($eStructure, 'duties')
				) {
					$categories[] = 'settings-duty';
				}

				if(
					$eStructure->acceptFeatureSection('amap') and
					$eStructure->canManage() and
					StructureLib::hasAmapFeature($eStructure, 'membership')
				) {
					$categories[] = 'settings-membership';
				}

				if(
					$eStructure->acceptFeatureSection('amap') and
					$eStructure->canManage() and
					StructureLib::hasAmapFeature($eStructure, 'form')
				) {
					$categories[] = 'settings-form';
				}

				if($eStructure->acceptFeatureSection('amap') and $eStructure->canManage()) {
					$categories[] = 'settings-remind';
				}

				if($eStructure->acceptAccounting()) {

					$categories[] = 'settings-accounting';

					if($eStructure->acceptInvoicing()) {
						$categories[] = 'settings-invoicing';
					}

				}

				return $categories;

			case 'data':

				if($eStructure->acceptImportExport()) {
					$categories = $eStructure->isAmap() ? ['customers'] : ['export', 'import'];
				} else {
					$categories = [];
				}

				if($eStructure->acceptApi()) {
					$categories[] = 'api';
				}

				return $categories;

		}

	}

}
?>
