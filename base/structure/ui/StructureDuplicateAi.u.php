<?php
namespace structure;

/**
 * Dupliquer une ferme d'un groupe : l'entrée dans la roue de la page de gestion, et le panneau.
 *
 * ⚠️ La roue de la carte, sur l'onglet Fermes, a laissé sa place à la case du bandeau
 * (chantier mes-producteurs, lot L2, planche arbitrée le 16/09/2026). Une action de bandeau ne
 * porte pas de raison : celle du refus reste lisible dans la roue de la page de gestion.
 *
 * Contrat : planche « Dupliquer une ferme » v6, validée le 14/09/2026
 * (https://claude.ai/code/artifact/d3d58e48-5b7f-4e41-93a5-57eaa39f4448), Q1 pour la roue et Q2 pour le
 * panneau. Les comptes viennent de `DuplicateAiLib::getCounts()`, préparés par la page.
 */
class StructureDuplicateAiUi {

	public static function url(Structure $eStructureCommunity, Structure $eStructure): string {
		return self::urlBase($eStructureCommunity).$eStructure['id'];
	}

	/**
	 * L'adresse sans la ferme, que le bandeau de l'onglet Fermes complète par la ferme cochée
	 * (`StructureCommunityFarmsAi`).
	 */
	public static function urlBase(Structure $eStructureCommunity): string {
		return '/structure/community:duplicateStructure?id='.$eStructureCommunity['id'].'&structure=';
	}

	/**
	 * L'entrée « Dupliquer la ferme », active ou désactivée avec sa raison.
	 *
	 * Rien quand `duplicate` vaut NULL : la page de gestion pose cette propriété et appelle cette
	 * méthode dans une roue qui porte aussi le retrait.
	 */
	public static function getItem(Structure $eStructureCommunity, Structure $eStructure): string {

		if($eStructure['duplicate'] === NULL) {
			return '';
		}

		if($eStructure['duplicate']) {
			return '<a href="'.self::url($eStructureCommunity, $eStructure).'" class="dropdown-item">'.s("Dupliquer la ferme").'</a>';
		}

		$class = StructureUi::getClass($eStructureCommunity);

		$h = '<a class="dropdown-item disabled">';
			$h .= '<div class="util-item-label">'.s("Dupliquer la ferme").'</div>';
			$h .= '<div class="util-item-more">'.s("Cette ferme n'a autorisé aucune duplication à {value}", encode($class::getText($eStructureCommunity, 'audienceStaff'))).'</div>';
		$h .= '</a>';

		return $h;

	}

	/**
	 * Le panneau, une famille par groupe, au même plan.
	 *
	 * `$asked` dit les familles que le groupe demande, dans l'ordre du panneau : une famille qu'il
	 * ne demande pas ne se montre pas (la commercialisation fermée par défaut). Demandée et refusée
	 * par la ferme, elle se montre avec sa raison, en texte : il n'y a rien à choisir.
	 *
	 * ⚠️ **Le oui / non ne s'affiche que quand les deux familles sont accordées.** Seule, la famille
	 * accordée garde sa liste et poste sa valeur en caché : un oui / non n'y aurait qu'une réponse
	 * valable, l'action refusant de ne rien dupliquer.
	 */
	public function duplicate(Structure $eStructureCommunity, Structure $eStructureSource, array $asked, array $granted, array $counts): \Panel {

		$form = new \util\FormUi();

		$class = StructureUi::getClass($eStructureCommunity);
		$audience = encode($class::getText($eStructureCommunity, 'audienceStaff'));

		$alone = (count(array_filter($granted)) === 1);

		$h = $form->hidden('id', $eStructureCommunity);
		$h .= $form->hidden('structure', $eStructureSource);

		$fields = '';

		foreach($asked as $family) {

			$name = match($family) {
				'production' => 'copyProduction',
				'selling' => 'copySelling',
			};

			$label = match($family) {
				'production' => s("Dupliquer la production"),
				'selling' => s("Dupliquer la commercialisation"),
			};

			if($granted[$family]) {

				$lines = match($family) {
					'production' => $this->getProductionLines($counts['production']),
					'selling' => $this->getSellingLines($counts['selling']),
				};

				$content = $this->getList($lines);
				$content .= $alone ? $form->hidden($name, TRUE) : $form->yesNo($name, TRUE);

				if($family === 'selling') {
					$content .= \util\FormUi::info(s("Sans la production, les espèces de ces produits et de ces rapports sont dupliquées aussi, sans leurs variétés, et les rapports perdent leur détail par série."));
				}

			} else {

				$content = \util\FormUi::info(match($family) {
					'production' => s("Cette ferme n'a pas autorisé la duplication de sa production à {value}", $audience),
					'selling' => s("Cette ferme n'a pas autorisé la duplication de sa commercialisation à {value}", $audience),
				});

			}

			$fields .= $form->group($label, $content);

		}

		$fields .= $form->dynamicGroup(new Structure(['name' => s("{value} (copie)", $eStructureSource['name'])]), 'name', function($d) {
			$d->label = s("Nom de la nouvelle ferme");
		});

		$source = '<b>'.encode($eStructureSource['name']).'</b>';

		// Le bloc dit ce que fait le bouton : le confirm() qui le doublait redemandait ce qu'il vient de dire.
		$h .= \main\DesignUi::getWeigh(
			'info',
			'copy',
			s("Dupliquer la structure {value}", $source),
			NULL,
			[
				['icon' => 'house-add', 'text' => s("<b>Une nouvelle structure</b> sera créée sur votre compte en structure de test, avec les données de {value} que vous avez choisies.", $source)]
			],
			[s("La structure {value} ne change pas.", $source)],
			TRUE
		);

		$h .= $fields;

		return new \Panel(
			id: 'panel-structure-duplicate',
			title: s("Dupliquer une ferme"),
			dialogOpen: $form->openAjax('/structure/community:doDuplicateStructure', ['class' => 'panel-dialog']),
			dialogClose: $form->close(),
			body: $h,
			footer: $form->submit(s("Dupliquer"), ['data-waiter' => s("Duplication en cours...")])
		);

	}

	/**
	 * Une ligne par libellé, et les lignes vides ne s'écrivent pas : une ferme qui n'a pas de
	 * prévisionnel ne se voit pas annoncer « 0 saison ».
	 */
	protected function getList(array $lines): string {

		$lines = array_filter($lines, fn(?string $value) => $value !== NULL);

		if($lines === []) {
			return '';
		}

		$h = '<ul>';

			foreach($lines as $label => $value) {
				$h .= '<li>'.s("{label} : {value}", ['label' => $label, 'value' => $value]).'</li>';
			}

		$h .= '</ul>';

		return $h;

	}

	protected function getProductionLines(array $counts): array {

		return [
			s("Le plan de la ferme") => $this->getEnumeration([
				$this->getCount($counts['zones'], p("{value} parcelle", "{value} parcelles", $counts['zones'])),
				$this->getCount($counts['plots'], p("{value} jardin", "{value} jardins", $counts['plots'])),
				$this->getCount($counts['beds'], p("{value} planche", "{value} planches", $counts['beds'], ['value' => \util\TextUi::number($counts['beds'])])),
			]),
			s("Les espèces et variétés") => $this->getEnumeration([
				$this->getCount($counts['plants'], p("{value} espèce", "{value} espèces", $counts['plants'])),
				$this->getCount($counts['varieties'], p("{value} variété", "{value} variétés", $counts['varieties'], ['value' => \util\TextUi::number($counts['varieties'])])),
				$this->getCount($counts['sizes'], p("{value} calibre", "{value} calibres", $counts['sizes'])),
			]),
			s("Les itinéraires techniques") => $this->getCount($counts['sequences'], p("{value} itinéraire", "{value} itinéraires", $counts['sequences'], ['value' => \util\TextUi::number($counts['sequences'])])),
			s("Le plan de culture") => $this->getSeries($counts),
			s("Le temps de travail") => $this->getCount($counts['hours'], p("{value} heure, sans le nom des personnes", "{value} heures, sans le nom des personnes", $counts['hours'], ['value' => \util\TextUi::number($counts['hours'])])),
			s("Le paramétrage de la production") => $this->getEnumeration([
				$this->getCount($counts['actions'], p("{value} intervention", "{value} interventions", $counts['actions'])),
				$this->getCount($counts['methods'], p("{value} méthode de travail", "{value} méthodes de travail", $counts['methods'])),
				$this->getCount($counts['tools'], p("{value} matériel", "{value} matériels", $counts['tools'], ['value' => \util\TextUi::number($counts['tools'])])),
				$this->getCount($counts['suppliers'], p("{value} fournisseur de semences et plants", "{value} fournisseurs de semences et plants", $counts['suppliers'])),
			]),
			s("Le prévisionnel financier") => match($counts['forecasts']) {
				0 => NULL,
				1 => s("la saison {value}", $counts['forecastsFirst']),
				default => s("{value} saisons, de {first} à {last}", ['value' => $counts['forecasts'], 'first' => $counts['forecastsFirst'], 'last' => $counts['forecastsLast']]),
			},
		];

	}

	/**
	 * « 908 séries de 2021 à 2027, avec 22 250 interventions et 10 707 récoltes ».
	 *
	 * Les saisons se disent comme le prévisionnel : une seule se nomme (« en 2026 »), plusieurs se
	 * bornent. Les interventions et les récoltes ne se disent qu'avec leurs séries.
	 */
	protected function getSeries(array $counts): ?string {

		if($counts['series'] === 0) {
			return NULL;
		}

		$series = ($counts['seriesFirst'] === $counts['seriesLast'])
			? p("{value} série en {season}", "{value} séries en {season}", $counts['series'], ['value' => \util\TextUi::number($counts['series']), 'season' => $counts['seriesFirst']])
			: p("{value} série de {first} à {last}", "{value} séries de {first} à {last}", $counts['series'], ['value' => \util\TextUi::number($counts['series']), 'first' => $counts['seriesFirst'], 'last' => $counts['seriesLast']]);

		$details = $this->getEnumeration([
			$this->getCount($counts['tasks'], p("{value} intervention", "{value} interventions", $counts['tasks'], ['value' => \util\TextUi::number($counts['tasks'])])),
			$this->getCount($counts['harvests'], p("{value} récolte", "{value} récoltes", $counts['harvests'], ['value' => \util\TextUi::number($counts['harvests'])])),
		]);

		return ($details === NULL) ? $series : s("{series}, avec {details}", ['series' => $series, 'details' => $details]);

	}

	protected function getSellingLines(array $counts): array {

		return [
			s("Les produits") => $this->getCount($counts['products'], p("{value} produit", "{value} produits", $counts['products'], ['value' => \util\TextUi::number($counts['products'])])),
			s("Les ventes") => $this->getSales($counts),
			s("Les rapports") => $this->getReports($counts),
		];

	}

	protected function getReports(array $counts): ?string {

		if($counts['reports'] === 0) {
			return NULL;
		}

		return ($counts['reportsFirst'] === $counts['reportsLast'])
			? p("{value} rapport en {season}", "{value} rapports en {season}", $counts['reports'], ['value' => \util\TextUi::number($counts['reports']), 'season' => $counts['reportsFirst']])
			: p("{value} rapport de {first} à {last}", "{value} rapports de {first} à {last}", $counts['reports'], ['value' => \util\TextUi::number($counts['reports']), 'first' => $counts['reportsFirst'], 'last' => $counts['reportsLast']]);

	}

	/**
	 * « 426 ventes de 2021 à 2026, regroupées par semaine sur deux clients, « Ventes aux particuliers »
	 * et « Ventes aux professionnels » ».
	 *
	 * Les clients nommés sont ceux que l'agrégat des ventes de la ferme école crée, un par type de
	 * vente présent : le lecteur apprend ici que les clients de la ferme ne passent pas. Deux types
	 * de vente au plus, donc un ou deux clients, et le `match` lève sur un troisième.
	 */
	protected function getSales(array $counts): ?string {

		if($counts['sales'] === 0) {
			return NULL;
		}

		$sales = ($counts['salesFirst'] === $counts['salesLast'])
			? p("{value} vente en {season}", "{value} ventes en {season}", $counts['sales'], ['value' => \util\TextUi::number($counts['sales']), 'season' => $counts['salesFirst']])
			: p("{value} vente de {first} à {last}", "{value} ventes de {first} à {last}", $counts['sales'], ['value' => \util\TextUi::number($counts['sales']), 'first' => $counts['salesFirst'], 'last' => $counts['salesLast']]);

		$customers = $this->getEnumeration(array_map(fn(string $customer) => s("« {value} »", encode($customer)), $counts['customers']));

		return match(count($counts['customers'])) {
			1 => s("{sales}, regroupées par semaine sur un client, {customers}", ['sales' => $sales, 'customers' => $customers]),
			2 => s("{sales}, regroupées par semaine sur deux clients, {customers}", ['sales' => $sales, 'customers' => $customers]),
		};

	}

	protected function getCount(int $count, string $text): ?string {
		return ($count === 0) ? NULL : $text;
	}

	/** « a », « a et b », « a, b et c », sans les valeurs absentes. */
	protected function getEnumeration(array $values): ?string {

		$values = array_values(array_filter($values, fn(?string $value) => $value !== NULL));

		if($values === []) {
			return NULL;
		}

		if(count($values) === 1) {
			return $values[0];
		}

		$last = array_pop($values);

		return s("{values} et {last}", ['values' => implode(', ', $values), 'last' => $last]);

	}

}
?>
