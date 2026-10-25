<?php
namespace toolbox;

/**
 * Les produits autorisés (planche pf-catalogue-phyto, bancs q1-b et q2-b, arbitrés le
 * 29/09). Une barre de recherche en jetons, une famille par critère, puis la liste des produits
 * rangés par substance active.
 */
class CatalogUi {

	public function __construct() {
		\Asset::css('toolbox', 'catalog.css');
	}

	public static function url(\structure\Structure $eStructure, array $filters = [], array $changes = []): string {

		$url = ToolUi::url($eStructure).'/produits';

		$query = self::getQuery($filters, $changes);

		return ($query === []) ? $url : $url.'?'.http_build_query($query);

	}

	/**
	 * Les critères tels que l'adresse les porte. Le filtre bio s'y écrit toujours, même égal au défaut
	 * de la structure : le lien copié par une structure en bio et ouvert par une autre montre la même
	 * liste.
	 */
	protected static function getQuery(array $filters, array $changes): array {

		$values = [
			'plant' => ($filters['plant'] ?? new \plant\Plant())->empty() ? NULL : $filters['plant']['fqn'],
			'target' => $filters['target'] ?? NULL,
			'function' => $filters['function'] ?? NULL,
			'product' => ($filters['product'] ?? new \work\PhytoProduct())->empty() ? NULL : $filters['product']['amm'],
			'organic' => isset($filters['organic']) ? ($filters['organic'] ? '1' : '0') : NULL,
		];

		return array_filter($changes + $values, fn($value) => $value !== NULL);

	}

	public static function urlProduct(\structure\Structure $eStructure, \work\PhytoProduct $ePhytoProduct, \plant\Plant $ePlant): string {

		if($ePhytoProduct['tool'] !== NULL) {
			return '/outil/'.$ePhytoProduct['tool'];
		}

		return '/toolbox/catalog:product?'.http_build_query([
			'id' => $eStructure['id'],
			'amm' => $ePhytoProduct['amm'],
			'plant' => $ePlant->empty() ? NULL : $ePlant['fqn'],
		]);

	}

	/**
	 * La roue ne porte que ce que `ToolUi` donne à tout outil : la recherche n'a ni réglage ni ajout.
	 */
	public function getHeader(\structure\Structure $eStructure, \Collection $cFavorite): string {

		return new ToolUi()->getHeader($eStructure, Favorite::CATALOG, $cFavorite, menu: '');

	}

	/**
	 * L'accueil d'un outil que la structure n'a pas encore ouvert, comme les densités. Le bouton porte
	 * `open`, qui fait de la recherche un outil de la structure.
	 */
	public function getStart(\structure\Structure $eStructure): string {

		$promises = [
			['flower1', s("Par espèce et par cible"), s("les produits autorisés contre une maladie ou un ravageur")],
			['capsule', s("Par produit ou par substance"), s("les doses et le délai avant récolte")],
		];

		$content = '<p>'.s("Cherchez dans le catalogue des produits phytosanitaires autorisés en France ceux qui conviennent à vos cultures.").'</p>';
		$content .= new ToolUi()->getPromises($promises);

		return new \main\DesignUi()->getStart(
			\Asset::icon('search'),
			s("Le catalogue E-Phy"),
			$content,
			image: \Asset::image('main', 'friends/puceron.svg'),
			buttons: '<a href="'.self::url($eStructure).'?open" class="btn btn-primary btn-xl">'.s("Ouvrir la recherche").'</a>'
		);

	}

	public function get(\structure\Structure $eStructure, array $filters, array $products, int $page): string {

		$h = '<div id="catalog">';

			$h .= '<div class="util-chips">';
				$h .= $this->getSearch($eStructure, $filters);
				$h .= $this->getOrganicChip($eStructure, $filters);
			$h .= '</div>';

			$h .= $this->getProducts($eStructure, $filters, $products, $page);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Une seule barre pour les quatre familles (arbitrage Q1 B). Chaque critère posé est un jeton, qui
	 * mène à la page sans lui ; le placeholder s'efface dès le premier jeton, qui dit mieux que lui ce
	 * que la barre attend.
	 */
	protected function getSearch(\structure\Structure $eStructure, array $filters): string {

		$tokens = [];

		if($filters['plant']->notEmpty()) {
			$tokens[] = [
				'group' => s("Espèces"),
				'label' => $filters['plant']['name'],
				'value' => $filters['plant']['fqn'],
				'url' => self::url($eStructure, $filters, ['plant' => NULL]),
			];
		}

		if($filters['target'] !== NULL) {
			$tokens[] = [
				'group' => s("Cibles"),
				'label' => $filters['target'],
				'url' => self::url($eStructure, $filters, ['target' => NULL]),
			];
		}

		if($filters['function'] !== NULL) {
			$tokens[] = [
				'group' => s("Fonctions"),
				'label' => $filters['function'],
				'url' => self::url($eStructure, $filters, ['function' => NULL]),
			];
		}

		if($filters['product']->notEmpty()) {
			$tokens[] = [
				'group' => s("Produits"),
				'label' => $filters['product']->getDisplayName(),
				'value' => $filters['product']['amm'],
				'url' => self::url($eStructure, $filters, ['product' => NULL]),
			];
		}

		return \main\OmniboxUi::get(
			suggestionsUrl: '/toolbox/catalog:query',
			// Les critères posés voyagent avec la saisie : une espèce restreint les cibles et les
			// produits proposés, et chaque suggestion mène à la page qui garde les autres.
			suggestionsBody: ['id' => $eStructure['id']] + self::getQuery($filters, []),
			tokens: $tokens,
			placeholder: ($tokens === []) ? s("Espèce, cible, produit...") : '',
			clear: self::url($eStructure, $filters, ['plant' => NULL, 'target' => NULL, 'function' => NULL, 'product' => NULL]),
			class: 'util-omnibox-lg',
			singleByGroup: TRUE
		);

	}

	/**
	 * « Utilisables en bio » est un filtre posé d'office pour une structure en bio ou en conversion.
	 * Au repos, le jeton dit ce qu'on voit, et son menu propose l'autre choix (convention des jetons
	 * de la mercuriale, `quotation\ResultUi::getChip()`).
	 */
	protected function getOrganicChip(\structure\Structure $eStructure, array $filters): string {

		$h = '<a class="util-chip'.($filters['organic'] ? ' is-active is-solid' : '').'" data-dropdown="bottom-start">'.($filters['organic'] ? s("Utilisables en bio") : s("Tous les produits")).'</a>';

		$h .= '<div class="dropdown-list">';
			$h .= '<a href="'.self::url($eStructure, $filters, ['organic' => '1']).'" class="dropdown-item'.($filters['organic'] ? ' selected' : '').'">'.s("Utilisables en bio").'</a>';
			$h .= '<a href="'.self::url($eStructure, $filters, ['organic' => '0']).'" class="dropdown-item'.($filters['organic'] ? '' : ' selected').'">'.s("Tous les produits").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Un groupe par substance active, du plus fourni au moins fourni (arbitrage Q2 B). Un produit à
	 * deux substances paraît dans les deux groupes ; le titre, lui, le compte une fois. La colonne du
	 * logo AB reste là quand le filtre bio est posé, où elle est pleine : c'est avec « Tous les
	 * produits » qu'elle distingue, et un tableau qui perd une colonne selon un filtre se relit mal.
	 * Sous 576 px, les usages passent sous le nom du produit plutôt que de disparaître.
	 */
	protected function getProducts(\structure\Structure $eStructure, array $filters, array $products, int $page): string {

		$h = '<h2 class="mt-2">'.p("{value} produit", "{value} produits", $products['count']).'</h2>';

		if($products['count'] === 0) {
			return $h;
		}

		$h .= '<div class="util-table util-table-hover catalog-products">';

			$h .= '<div class="util-table-head">';
				$h .= '<div>'.s("Produit").'</div>';
				$h .= '<div></div>';
				$h .= '<div class="hide-xs-down">'.s("Usage").'</div>';
				$h .= '<div class="text-end">'.s("Dose").'</div>';
				$h .= '<div class="text-end">'.s("Avant récolte").'</div>';
			$h .= '</div>';

			foreach($products['groups'] as $group) {

				$h .= '<div class="util-table-title">';
					$h .= ($group['name'] === '') ? s("Substance non renseignée") : encode(self::getSubstanceName($group['name']));
					$h .= ' <span class="util-counter ms-1">'.$group['count'].'</span>';
				$h .= '</div>';

				foreach($group['cPhytoProduct'] as $ePhytoProduct) {
					$h .= $this->getProductRows($eStructure, $filters, $ePhytoProduct);
				}

			}

		$h .= '</div>';

		$h .= \util\TextUi::pagination($page, $products['pages']);

		return $h;

	}

	/**
	 * Un produit en autant de lignes que de cultures E-Phy qui couvrent une espèce de la structure,
	 * chacune avec sa dose et son délai, le nom du produit et le logo AB sur la première seulement ;
	 * puis ses autres cultures en une ligne atténuée, doses et délais en plage (piste C de
	 * pf-catalogue-usage, « Vos cultures d'abord », validée le 29/09). Un produit qui ne
	 * couvre aucune culture de la structure tient dans cette seule ligne.
	 */
	protected function getProductRows(\structure\Structure $eStructure, array $filters, \work\PhytoProduct $ePhytoProduct): string {

		$rows = [];

		foreach($ePhytoProduct['crops'] as $crop) {
			$rows[] = [
				'usage' => self::getCrop($filters, $crop),
				'dose' => self::getDoses($crop['doses']),
				'harvestDelay' => self::getHarvestDelays($crop['harvestDelays']),
			];
		}

		$other = $ePhytoProduct['cropsOther'];

		if($other['names'] !== []) {

			$names = implode(' · ', array_map(fn($name) => encode(mb_strtolower(mb_substr($name, 0, 1)).mb_substr($name, 1)), $other['names']));

			$rows[] = [
				'usage' => '<span class="util-annotation">'.(($rows === []) ? s("Sur {crops}", ['crops' => $names]) : s("Aussi sur {crops}", ['crops' => $names])).'</span>',
				'dose' => '<span class="util-annotation">'.self::getDoses($other['doses']).'</span>',
				'harvestDelay' => '<span class="util-annotation">'.self::getHarvestDelays($other['harvestDelays']).'</span>',
			];

		}

		$h = '';

		foreach($rows as $position => $row) {

			$h .= '<div class="util-table-row">';
				$h .= '<div>';
					if($position === 0) {
						$h .= '<a href="'.self::urlProduct($eStructure, $ePhytoProduct, $filters['plant']).'">'.encode($ePhytoProduct->getDisplayName()).'</a>';
						if($ePhytoProduct['tool'] !== NULL) {
							$h .= ' <span class="badge badge-neutral">'.s("Dans vos produits").'</span>';
						}
					}
					$h .= '<div class="util-annotation hide-sm-up">'.$row['usage'].'</div>';
				$h .= '</div>';
				$h .= '<div>'.(($position === 0 and $ePhytoProduct['organic']) ? \structure\StructureUi::getOrganicLogo(\structure\Structure::YES, '1.25rem') : '').'</div>';
				$h .= '<div class="hide-xs-down">'.$row['usage'].'</div>';
				$h .= '<div class="text-end">'.$row['dose'].'</div>';
				$h .= '<div class="text-end">'.$row['harvestDelay'].'</div>';
			$h .= '</div>';

		}

		return $h;

	}

	/**
	 * Une culture de la structure, vignette en tête, suivie de ses cibles atténuées : « contre » sans
	 * cible posée, « aussi contre » avec, la cible posée ne se répétant pas. Quand une espèce est
	 * cherchée, c'est elle que la ligne nomme, et la culture E-Phy qui la couvre passe dessous
	 * (« mais du tout pourquoi on a pas le picto de l'ail ? », le 29/09), sauf si elle la
	 * nomme déjà.
	 */
	protected static function getCrop(array $filters, array $crop): string {

		$ePlant = $filters['plant'];

		$h = $ePlant->notEmpty() ?
			self::getCropVignette($ePlant['fqn']).encode($ePlant['name']) :
			self::getCropVignette($crop['plant']).encode($crop['name']);

		if($crop['targets'] !== []) {

			$targets = encode(implode(', ', array_map(fn($target) => (mb_strtoupper($target) === $target) ? $target : mb_strtolower(mb_substr($target, 0, 1)).mb_substr($target, 1), $crop['targets'])));

			$h .= ' <span class="color-muted font-sm">'.(($filters['target'] === NULL) ? s("contre {targets}", ['targets' => $targets]) : s("aussi contre {targets}", ['targets' => $targets])).'</span>';

		}

		if($ePlant->notEmpty() and $crop['plant'] !== $ePlant['fqn']) {
			$h .= '<div class="util-annotation">'.s("Usage E-Phy « {crop} »", ['crop' => encode($crop['name'])]).'</div>';
		}

		return $h;

	}

	/**
	 * La vignette de l'espèce devant le nom de sa culture. Une culture sans dessin en garde la place,
	 * pour que les noms s'alignent d'une ligne à l'autre de la colonne.
	 */
	protected static function getCropVignette(?string $plant): string {

		if(\plant\PlantUi::hasVignette($plant) === FALSE) {
			return '<span class="catalog-vignette-place"></span> ';
		}

		return \plant\PlantUi::getVignette(new \plant\Plant(['fqn' => $plant]), '1.25rem').' ';

	}

	/**
	 * Le catalogue écrit les substances en minuscules (« soufre », « hydroxyde de cuivre ») : seule la
	 * première lettre du titre se relève.
	 */
	protected static function getSubstanceName(string $name): string {
		return mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1);
	}

	/**
	 * Une plage par unité (« 0,4 à 0,56 L/ha »), une valeur seule quand tous les usages retenus
	 * donnent la même.
	 */
	protected static function getDoses(array $doses): string {

		$values = [];

		foreach($doses as $unit => [$min, $max]) {

			// La valeur reste collée à son symbole de mesure et à sa barre (« 11,25 kg/ »), le reste de
			// l'unité peut passer à la ligne : à 360 px la colonne offre 48 px, « 11,25 kg/ha » en
			// tient 65 et « 400 diffuseurs/ha » 75. Un compte d'objets (« diffuseurs/ha ») quitte sa valeur.
			$bound = preg_match('/^(m?L|k?g)(?![a-zA-Z])\/?/u', $unit, $match) ? $match[0] : '';
			$free = str_replace('/', '/<wbr>', encode(mb_substr($unit, mb_strlen($bound))));

			$values[] = ($bound === '') ?
				self::getRange($min, $max, '', ' '.$free) :
				self::getRange($min, $max, ' '.encode($bound), '<wbr>'.$free);

		}

		return implode('<br/>', $values);

	}

	protected static function getHarvestDelays(array $delays): string {

		if($delays === []) {
			return '';
		}

		[$min, $max] = $delays;

		return self::getRange($min, $max, ' '.p("jour", "jours", $max));

	}

	/**
	 * La dernière valeur ne se sépare pas de `$bound`, le début de son unité ; `$free` la suit sans
	 * contrainte. Une plage peut passer à la ligne après « à » (« 3,3 à » puis « 12,5 L/ha »), sans
	 * quoi la colonne déborde à 360 px.
	 */
	protected static function getRange(float $min, float $max, string $bound, string $free = ''): string {

		$last = ($bound === '') ?
			\util\TextUi::number($max, NULL).$free :
			'<span class="util-unit">'.\util\TextUi::number($max, NULL).$bound.'</span>'.$free;

		if($min === $max) {
			return $last;
		}

		return s("{min} à {max}", ['min' => \util\TextUi::number($min, NULL), 'max' => $last]);

	}

	/**
	 * Les suggestions des quatre familles, chacune menant à la page où elle remplace le critère de sa
	 * famille et garde les autres.
	 */
	public static function getSuggestions(\structure\Structure $eStructure, array $filters, array $suggestions): array {

		$url = fn(array $changes) => self::url($eStructure, $filters, $changes);

		$results = [];

		foreach($suggestions['cPlant'] as $ePlant) {

			$suggestion = [
				'group' => s("Espèces"),
				'label' => $ePlant['name'],
				'value' => $ePlant['fqn'],
				'url' => $url(['plant' => $ePlant['fqn']]),
			];

			if(\plant\PlantUi::hasVignette($ePlant['fqn'])) {
				$suggestion['icon'] = \plant\PlantUi::getVignetteHref($ePlant['fqn']);
			}

			$results[] = $suggestion;

		}

		foreach($suggestions['targets'] as $target) {
			$results[] = [
				'group' => s("Cibles"),
				'label' => $target,
				'url' => $url(['target' => $target]),
			];
		}

		foreach($suggestions['functions'] as $function) {
			$results[] = [
				'group' => s("Fonctions"),
				'label' => $function,
				'url' => $url(['function' => $function]),
			];
		}

		foreach($suggestions['cPhytoProduct'] as $ePhytoProduct) {
			$results[] = [
				'group' => s("Produits"),
				'label' => $ePhytoProduct->getDisplayName(),
				'value' => $ePhytoProduct['amm'],
				'url' => $url(['product' => $ePhytoProduct['amm']]),
			];
		}

		return $results;

	}

	/**
	 * La fiche d'un produit du catalogue que la structure n'a pas encore : la même que celle d'un de
	 * ses produits (`work\ToolUi::display()`), avec l'ajout à ses produits en tête : une fiche n'est
	 * pas un formulaire, son geste ne se cherche pas au bas du panneau (« arrête de mettre des boutons
	 * en bas c'est relou », le 29/09).
	 */
	public function displayProduct(\structure\Structure $eStructure, \work\PhytoProduct $ePhytoProduct): \Panel {

		\Asset::css('work', 'routine.css');

		$h = '';

		// Un produit est un matériel, que seul `work\Tool::canWrite()` crée.
		if($eStructure->canManage()) {
			$h .= '<div class="mb-2">';
				$h .= '<a href="/work/tool:create?'.http_build_query(['structure' => $eStructure['id'], 'routineName' => 'phyto', 'amm' => $ePhytoProduct['amm']]).'" class="btn btn-primary">'.\Asset::icon('plus-circle').' '.s("Ajouter à mes produits").'</a>';
			$h .= '</div>';
		}

		$h .= '<dl class="util-presentation util-presentation-1">';

			$h .= '<dt>'.s("AMM").'</dt>';
			$h .= '<dd>'.encode($ePhytoProduct['amm']).'</dd>';

			if($ePhytoProduct['substances'] !== []) {
				$h .= '<dt>'.p("Substance", "Substances", count($ePhytoProduct['substances'])).'</dt>';
				$h .= '<dd>'.encode(implode(', ', array_map(fn($substance) => self::getSubstanceName($substance['name']), $ePhytoProduct['substances']))).'</dd>';
			}

			if($ePhytoProduct['functions'] !== NULL) {
				$h .= '<dt>'.s("Fonction").'</dt>';
				$h .= '<dd>'.encode($ePhytoProduct['functions']).'</dd>';
			}

			if($ePhytoProduct['formulation'] !== NULL) {
				$h .= '<dt>'.s("Formulation").'</dt>';
				$h .= '<dd>'.encode($ePhytoProduct['formulation']).'</dd>';
			}

			if($ePhytoProduct['organic']) {
				$h .= '<dt>'.s("Agriculture biologique").'</dt>';
				$h .= '<dd>'.\structure\StructureUi::getOrganicLogo(\structure\Structure::YES, '1.5rem').'</dd>';
			}

		$h .= '</dl>';

		if($ePhytoProduct['cPhytoUsage']->notEmpty()) {
			$h .= '<h3 class="mt-2">'.s("Doses autorisées").'</h3>';
			$h .= new \work\RoutineUi()->getPhytoUsages($ePhytoProduct['cPhytoUsage'], plants: TRUE);
		}

		return new \Panel(
			id: 'panel-catalog-product',
			title: encode($ePhytoProduct->getDisplayName()),
			body: $h
		);

	}

}
?>
