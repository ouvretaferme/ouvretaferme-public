<?php
namespace structure;

/**
 * Ce que le package `structure` apporte à l'accueil : la météo de la position de la structure, dans le
 * groupe Bureau du menu, et ce que la ferme n'a encore jamais essayé.
 *
 * Les deux sont légers : ils ne lisent que la base commune et une clé de cache, aucune base de
 * structure, et s'affichent avec la page au lieu d'arriver après elle.
 *
 * La météo n'a pas de liste derrière elle. Son adresse est la source des prévisions, dont la licence
 * exige l'attribution (arbitré par V-Dashboard le 2026-10-05, piste A) ; le bloc la cite au bas du menu
 * de son modèle (L82).
 */
class DashboardLib {

	/**
	 * « À essayer » se pose sous les colonnes, et sous `settings`, la dernière section : une suggestion
	 * vient après ce que la structure fait déjà, jamais à la place d'une action (L67, L69). Une
	 * section à lui demanderait un onglet d'univers, que le socle exige pour chaque section
	 * (`DashboardBlockSentinel`), et ce bloc n'a pas d'écran à y ouvrir. `assertStartedFeatures` : le
	 * catalogue est celui d'une ferme, et ouvrir une fonctionnalité est un geste de qui la gère, comme
	 * sur la page d'arrivée (`structure.p.php`, `start`). La déclaration le porte pour que le panneau
	 * « Personnaliser mon accueil » ne le propose pas à un groupe ni à une AMAP.
	 */
	public static function getBlocks(): array {

		return [
			'structure-start' => [
				'section' => 'settings',
				'column' => \dashboard\DashboardSetting::FOLLOW,
				'shape' => \dashboard\DashboardSetting::OPEN,
				'right' => 'assertStartedFeatures'
			]
		];

	}

	/**
	 * La météo, dans le groupe Bureau du menu sous ses entrées (L52, avant dans la tête de l'accueil
	 * depuis L15). `feature`
	 * production : elle sert à décider d'un geste au champ. `canRead` est le défaut, la prévision ne dit
	 * rien que la structure doive taire à un membre.
	 */
	public static function getContributions(): array {

		return [
			'structure-weather' => [
				'slot' => \dashboard\DashboardSetting::WEATHER,
				'feature' => 'production'
			]
		];

	}

	public static function load(string $name, Structure $eStructure, ?string $focus = NULL): ?array {

		return match($name) {
			'structure-weather' => WeatherAiLib::getByStructure($eStructure, $eStructure->getMember()),
			'structure-start' => self::getStart($eStructure)
		};

	}

	public static function getUrl(string $name, Structure $eStructure, ?array $data): string {

		return match($name) {
			'structure-weather' => WeatherAiLib::SOURCE_URL,
			'structure-start' => StructureUi::urlStart($eStructure)
		};

	}

	/**
	 * Les entrées du catalogue de la page d'arrivée (`StructureUi::getStartedFeatures()`) que la ferme
	 * n'a jamais essayées, sous `remaining`, ou `NULL` quand elle les a toutes essayées : le bloc
	 * disparaît alors. `step` est celle que l'accueil désigne comme premier pas, la première restante
	 * dans `StructureSetting::START_STEPS`, ou `NULL` quand aucune de celles-là ne reste.
	 *
	 * « Jamais essayé » se lit sur ce que la ferme a fait, pas sur un réglage. Sept fonctionnalités le
	 * disent par leur drapeau de `structureFeature`, posé au premier geste par le Lib qui le fait, et la
	 * ligne est déjà chargée par l'accueil. Le site n'a pas de drapeau : une ligne de `website` en tient
	 * lieu, sur son index `UNIQUE structure`.
	 *
	 * Une entrée que la structure ne peut pas ouvrir est tenue pour essayée, par le même `accept*` que
	 * l'écran où elle mène : une carte proposée mènerait à un refus, et une entrée comptée que le
	 * catalogue ne montre pas laisserait un bloc vide. Une boutique n'a ainsi ni production ni relevés
	 * de prix, la comptabilité comme la facturation électronique demandent accounting/ et une structure
	 * déclarée, et la boutique, les factures comme la caisse demandent commercialisation/. Une production
	 * que la structure a désactivée vaut de même : la carte rouvrirait un univers qu'elle a fermé.
	 */
	private static function getStart(Structure $eStructure): ?array {

		$tried = [
			'shop' => ($eStructure->conf(Feature::class)['hasShops'] or $eStructure->acceptCommercialisation() === FALSE),
			'invoice' => ($eStructure->conf(Feature::class)['hasInvoices'] or $eStructure->acceptCommercialisation() === FALSE),
			'pdp' => ($eStructure->conf(Feature::class)['hasPdp'] or $eStructure->acceptInvoicing() === FALSE),
			'market' => ($eStructure->conf(Feature::class)['hasMarket'] or $eStructure->acceptCommercialisation() === FALSE),
			'accounting' => ($eStructure->hasAccounting() or $eStructure->acceptAccounting() === FALSE),
			'planning' => ($eStructure->conf(Feature::class)['hasCultivations'] or $eStructure->acceptProduction() === FALSE or $eStructure->conf(Feature::class)['production'] === FALSE),
			'quotation' => ($eStructure->conf(Feature::class)['hasQuotation'] or $eStructure->acceptQuotation() === FALSE),
			'website' => \website\Website::model()
				->whereStructure($eStructure)
				->exists(),
		];

		$remaining = array_keys(array_filter($tried, fn(bool $isTried) => $isTried === FALSE));

		if($remaining === []) {
			return NULL;
		}

		// Le pas de la vente mène à la création d'une vente, la carte de la même entrée propose la
		// facture : une structure qui vend sans facturer garde la carte, mais ne se voit pas désigner
		// un geste qu'elle fait déjà.
		$steps = array_diff(StructureSetting::START_STEPS, $eStructure->conf(Feature::class)['hasSales'] ? ['invoice'] : []);

		return [
			'step' => array_find($steps, fn(string $step) => in_array($step, $remaining, TRUE)),
			'remaining' => $remaining
		];

	}

}
?>
