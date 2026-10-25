<?php
namespace toolbox;

/**
 * Poser un pluviomètre, le nommer, le supprimer (plan Gauge validé par M-Prod le 23/09).
 */
class GaugeUi {

	public static function getName(Gauge $eGauge): string {
		return self::getZoneName($eGauge['zone']);
	}

	/**
	 * Le nom d'un lieu où poser un pluviomètre : la ferme n'a pas de zone.
	 */
	public static function getZoneName(\map\Zone $eZone): string {

		if($eZone->exists()) {
			return s("Parcelle {value}", encode($eZone['name']));
		} else {
			return s("La ferme");
		}

	}

	/**
	 * Le geste qui pose un pluviomètre, sur `$cZoneFree`, les lieux qui n'en ont pas encore. Quand la
	 * ferme est le seul, il n'y a rien à choisir : le bouton pose le pluviomètre directement, et la
	 * page se recharge avec lui (réponse (a) de M-Prod du 23/09). Sans lieu libre, il ouvre le panneau,
	 * qui dit pourquoi (`create()`).
	 */
	public static function getCreate(\structure\Structure $eStructure, \Collection $cZoneFree, string $label, string $class): string {

		$content = \Asset::icon('plus-circle').' '.$label;

		if($cZoneFree->count() === 1 and $cZoneFree->first()->exists() === FALSE) {
			return '<a data-ajax="/toolbox/gauge:doCreate" post-structure="'.$eStructure['id'].'" post-zone="" class="'.$class.'">'.$content.'</a>';
		} else {
			return '<a href="/toolbox/gauge:create?structure='.$eStructure['id'].'" class="'.$class.'">'.$content.'</a>';
		}

	}

	/**
	 * La roue crantée d'un pluviomètre, à droite de son titre (réponse (b) de M-Prod du 23/09). La
	 * suppression, qui emporte les relevés, passe sous la zone de danger (retour du 23/09).
	 */
	public function getActions(Gauge $eGauge): string {

		$h = '<a class="btn btn-color-primary dropdown-toggle" data-dropdown="bottom-end" title="'.s("Réglages du pluviomètre").'">'.\Asset::icon('gear-fill').'</a>';
		$h .= '<div class="dropdown-list">';
			$h .= '<div class="dropdown-title">'.s("Pluviomètre").'</div>';
			$h .= '<a href="/toolbox/gauge:update?id='.$eGauge['id'].'" class="dropdown-item">'.s("Changer de lieu").'</a>';
			if($eGauge->canManage()) {
				$h .= '<a href="/toolbox/gauge:import?id='.$eGauge['id'].'" class="dropdown-item">'.s("Importer des relevés").'</a>';
			}
			$h .= '<div class="dropdown-divider"></div>';
			$h .= '<div class="dropdown-subtitle">'.\Asset::icon('exclamation-circle').'  '.s("Zone de danger").'  '.\Asset::icon('exclamation-circle').'</div>';
			$h .= '<a data-ajax="/toolbox/gauge:doUpdateStatus" post-id="'.$eGauge['id'].'" post-status="'.Gauge::INACTIVE.'" class="dropdown-item" data-confirm="'.s("Supprimer ce pluviomètre et ses relevés ?").'">'.s("Supprimer le pluviomètre").'</a>';
		$h .= '</div>';

		return $h;

	}

	/**
	 * Le panneau ne demande que le lieu : la pluie se note ensuite sur la page, par la ligne
	 * « Il a plu ? » du pluviomètre posé.
	 *
	 * Quand la ferme et chaque parcelle de la saison ont déjà le leur, il n'y a rien à choisir : le
	 * panneau le dit et mène à la cartographie, où se crée la parcelle qui recevra le pluviomètre (retour
	 * du 02/10, « manqe un bouton pour ajouter un pluvio », quand le geste disparaissait).
	 */
	public function create(Gauge $eGauge, \Collection $cZoneFree): \Panel {

		if($cZoneFree->empty()) {

			$h = '<p>'.s("La ferme et chacune de vos parcelles ont déjà leur pluviomètre.").'</p>';
			$h .= '<a href="'.\map\PlanUi::url($eGauge['structure'], \Clock::year(), \map\PlanUi::VIEW_MAP).'" class="btn btn-primary">'.s("Créer une parcelle sur la cartographie").'</a>';

			return new \Panel(
				id: 'panel-gauge-create',
				title: s("Nouveau pluviomètre"),
				body: $h
			);

		}

		$form = new \util\FormUi();

		$eGauge['cZoneFree'] = $cZoneFree;

		$h = $form->openAjax('/toolbox/gauge:doCreate', ['id' => 'gauge-create']);

			$h .= $form->hidden('structure', $eGauge['structure']);
			$h .= $form->dynamicGroup($eGauge, 'zone');

			$h .= $form->group(
				content: $form->submit(s("Poser le pluviomètre"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-gauge-create',
			title: s("Nouveau pluviomètre"),
			body: $h
		);

	}

	/**
	 * Changer de lieu garde les relevés : ils suivent le pluviomètre, pas le lieu.
	 */
	public function update(Gauge $eGauge, \Collection $cZoneFree): \Panel {

		$form = new \util\FormUi();

		$eGauge['cZoneFree'] = $cZoneFree;

		$h = $form->openAjax('/toolbox/gauge:doUpdate', ['id' => 'gauge-update']);

			$h .= $form->hidden('id', $eGauge);
			$h .= $form->dynamicGroup($eGauge, 'zone');

			$h .= $form->group(
				content: $form->submit(s("Enregistrer"))
			);

		$h .= $form->close();

		return new \Panel(
			id: 'panel-gauge-update',
			title: s("Changer de lieu"),
			body: $h
		);

	}

	/**
	 * Le panneau d'import d'un fichier de relevés (planche pf-l8-v9) : la documentation et le choix
	 * du fichier tant qu'aucun n'est lu, l'aperçu ensuite, dans le même panneau.
	 */
	public function import(Gauge $eGauge, ?array $import): \Panel {

		\Asset::css('main', 'csv.css');

		$h = '<p class="color-muted">'.self::getName($eGauge).'</p>';

		if($import === NULL) {
			$h .= $this->getImportFile($eGauge);
		} else {
			$h .= $this->getImportPreview($eGauge, $import);
		}

		return new \Panel(
			id: 'panel-gauge-import',
			title: s("Importer des relevés"),
			body: $h
		);

	}

	/**
	 * La documentation est embarquée dans le panneau (demande du 23/09), et non renvoyée à
	 * une page d'aide comme pour les autres imports. Le fichier part dès qu'il est choisi.
	 */
	protected function getImportFile(Gauge $eGauge): string {

		$h = '<div class="util-block-info"><p>'.s("Vous pouvez importer les relevés de ce pluviomètre depuis un fichier CSV, par exemple l'export d'une station météo ou d'un tableur.").'</p></div>';

		$h .= new \main\CsvUi()->getImportButton($eGauge, '/toolbox/gauge:doUpload', ajax: TRUE);

		$h .= '<div class="mt-2">';

			$h .= '<h3>'.s("Le format du fichier").'</h3>';

			$h .= \main\CsvUi::getSyntaxInfo();

			$h .= '<p>';
				$h .= '<a href="'.\Asset::getPath('toolbox', 'rain.csv').'" data-ajax-navigation="never" class="btn btn-outline-primary">'.s("Télécharger un exemple CSV").'</a>';
			$h .= '</p>';

			$h .= '<br/>';

			$h .= '<h3>'.s("Liste des données à fournir").'</h3>';

			$h .= \main\CsvUi::getDataList([
				[s("Jour du relevé").' '.\util\FormUi::asterisk(), 'date', s("Au format AAAA-MM-JJ ou JJ/MM/AAAA. Un jour à venir est refusé."), '2025-10-20'],
				[s("Pluie tombée").' '.\util\FormUi::asterisk(), 'quantity', s("En mm, avec un point ou une virgule pour les décimales. Une ligne à 0 est ignorée."), '12.6'],
			]);

		$h .= '</div>';

		return $h;

	}

	/**
	 * Ce que l'import va faire, avant de l'écrire : les jours ajoutés, ceux dont le fichier remplace le
	 * relevé (arbitrage Q23 A), les lignes à 0 ignorées et les lignes fausses écartées (arbitrage Q24 B).
	 */
	protected function getImportPreview(Gauge $eGauge, array $import): string {

		$name = self::getName($eGauge);
		$count = $import['added'] + $import['replaced'];

		$consequences = [];

		if($import['added'] > 0) {
			$consequences[] = ['icon' => 'cloud-rain', 'text' => p("<b>{value} jour</b> ajouté", "<b>{value} jours</b> ajoutés", $import['added'])];
		}

		if($import['replaced'] > 0) {
			$consequences[] = ['icon' => 'arrow-repeat', 'text' => p("<b>{value} jour déjà relevé</b> prend la valeur du fichier", "<b>{value} jours déjà relevés</b> prennent la valeur du fichier", $import['replaced'])];
		}

		if($import['ignored'] > 0) {
			$consequences[] = ['icon' => 'dash-circle', 'text' => p("<b>{value} ligne à 0 mm</b> est ignorée", "<b>{value} lignes à 0 mm</b> sont ignorées", $import['ignored'])];
		}

		if($import['errors'] > 0) {
			$consequences[] = ['icon' => 'x-circle', 'text' => p("<b>{value} ligne</b> en erreur est écartée", "<b>{value} lignes</b> en erreur sont écartées", $import['errors'])];
		}

		if($count > 0) {

			$h = \main\DesignUi::getWeigh(
				'info',
				'upload',
				p("Importer {value} relevé", "Importer {value} relevés", $count),
				s("Du {first} au {last}", ['first' => \util\DateUi::numeric($import['first'], \util\DateUi::DATE), 'last' => \util\DateUi::numeric($import['last'], \util\DateUi::DATE)]),
				$consequences,
				[s("Les autres relevés de {value} ne changent pas", $name)],
				FALSE
			);

		} else {

			$h = \main\DesignUi::getWeigh(
				'warning',
				'upload',
				s("Aucun relevé à importer"),
				NULL,
				$consequences,
				[s("Les relevés de {value} ne changent pas", $name)],
				TRUE
			);

		}

		$h .= '<div class="util-buttons">';
			if($count > 0) {
				$h .= '<a data-ajax="/toolbox/gauge:doImport" post-id="'.$eGauge['id'].'" class="btn btn-secondary" data-waiter="'.s("Importation en cours, merci de patienter...").'">'.p("Importer {value} relevé", "Importer {value} relevés", $count).'</a>';
			}
			$h .= '<a href="/toolbox/gauge:import?id='.$eGauge['id'].'" class="btn btn-outline-primary">'.s("Choisir un autre fichier").'</a>';
		$h .= '</div>';

		$h .= '<div class="mt-2 util-overflow-xs">';
			$h .= '<table class="tr-even">';
				$h .= '<thead>';
					$h .= '<tr>';
						$h .= '<th>'.s("Jour").'</th>';
						$h .= '<th class="text-end">'.s("Dans le fichier").'</th>';
						$h .= '<th class="text-end">'.s("Déjà noté").'</th>';
						$h .= '<th class="text-end">'.s("Après l'import").'</th>';
					$h .= '</tr>';
				$h .= '</thead>';
				$h .= '<tbody>';

					foreach($import['lines'] as $line) {

						$day = $line['isDate'] ? \util\DateUi::numeric($line['date'], \util\DateUi::DATE) : encode($line['date']);
						$already = $line['eRain']->notEmpty() ? RainUi::getMm($line['eRain']['rain']) : '';

						if($line['error'] !== NULL) {

							$quantity = ($line['rain'] !== NULL) ? RainUi::getMm($line['rain']) : '<span class="color-danger">'.\Asset::icon('exclamation-triangle').' '.encode($line['quantity']).'</span>';

							$h .= '<tr class="csv-error">';
								$h .= '<td>'.$day.'</td>';
								$h .= '<td class="text-end">'.$quantity.'</td>';
								$h .= '<td class="text-end">'.$already.'</td>';
								$h .= '<td></td>';
							$h .= '</tr>';
							$h .= '<tr>';
								$h .= '<td colspan="4" class="color-danger">'.AlertUi::getError($line['error']).'</td>';
							$h .= '</tr>';

						} else if($line['ignored']) {

							$h .= '<tr class="color-muted">';
								$h .= '<td>'.$day.'</td>';
								$h .= '<td class="text-end">'.RainUi::getMm($line['rain']).'</td>';
								$h .= '<td class="text-end">'.$already.'</td>';
								$h .= '<td class="text-end">'.$already.'</td>';
							$h .= '</tr>';

						} else {

							$h .= '<tr>';
								$h .= '<td>'.$day.'</td>';
								$h .= '<td class="text-end">'.RainUi::getMm($line['rain']).'</td>';
								$h .= '<td class="text-end">'.$already.'</td>';
								$h .= '<td class="text-end"><b>'.RainUi::getMm($line['rain']).'</b></td>';
							$h .= '</tr>';

						}

					}

				$h .= '</tbody>';
			$h .= '</table>';
		$h .= '</div>';

		return $h;

	}

	public static function p(string $property): \PropertyDescriber {

		$d = Gauge::model()->describer($property, [
			'zone' => s("Où est le pluviomètre ?"),
		]);

		switch($property) {

			case 'zone' :
				$d->field = function(\util\FormUi $form, Gauge $e) {

					// La ferme n'a pas d'id : son option envoie une chaîne vide, que build() lit comme la ferme.
					// L'invite partage donc sa valeur ; désactivée, elle ne se poste pas du tout, et l'étape
					// `chosen` refuse l'envoi. Un pluviomètre qui change de lieu a déjà le sien, présélectionné
					// par sa valeur : l'invite n'a pas lieu d'être.
					$values = [];

					if($e->exists()) {
						$selection = $e['zone']->empty() ? '' : $e['zone']['id'];
					} else {
						$selection = NULL;
						$values[] = ['value' => '', 'label' => s("< Choisir >"), 'attributes' => ['disabled' => 'disabled', 'selected' => 'selected']];
					}

					foreach($e['cZoneFree'] as $eZone) {
						$values[] = ['value' => $eZone->exists() ? $eZone['id'] : '', 'label' => GaugeUi::getZoneName($eZone)];
					}

					return $form->select('zone', $values, $selection, [
						'required' => TRUE
					]);

				};
				break;

		}

		return $d;

	}

}
?>
