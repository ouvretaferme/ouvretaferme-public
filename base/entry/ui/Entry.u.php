<?php
namespace entry;

/**
 * Le champ de saisie à l'écran : le champ du menu, et ce que chaque lecture renvoie à `entry.js`.
 *
 * Le champ est une omnibox (`main\OmniboxUi`) alimentée par `/entry/entry:read`. Les suggestions portent
 * leur nature dans leur valeur (`intention:harvest`, `plant:<id>`, `object:<id>`, `cultivation:<id>`,
 * `task:<id>`, `all`, `write`) : `entry.js` s'en sert pour poser le jeton, la réponse à la question ou
 * l'écriture.
 */
class EntryUi {

	use \Notifiable;

	/**
	 * D'où part la pastille d'une écriture : la ligne de confirmation de la liste, dans le champ qui écrit. Une
	 * page en porte plusieurs, les cases d'un jour de récolte avec celui du menu : `entry.js` marque celui qui
	 * poste (`data-entry-sending`).
	 */
	const string TOAST_FROM = '.entry-field[data-entry-sending] .util-omnibox-item[data-value="write"]';

	public function __construct() {

		\Asset::css('entry', 'entry.css');
		\Asset::js('entry', 'entry.js');

	}

	/**
	 * Le champ du menu, en tête de la colonne sous les univers, et au-dessus de la barre du bas au
	 * téléphone (`template-structure.css`). Il n'a pas de jeton de verbe : la phrase dit ce qu'elle
	 * saisit, ou se lit en récolte (`EntryLib::readImplicit()`).
	 *
	 * Vide, il propose les gestes du « + » (`$gestures`, par univers, `[icône, libellé, adresse]`), tous
	 * rendus ici : `entry.js` garde ceux de l'univers de l'écran. Un geste que le champ sait faire, de quelque
	 * univers qu'il vienne, code fermé compris, porte en quatrième élément son intention : il pose alors le jeton du verbe et propose la suite, sans
	 * quitter la page (`EntryField.pick()`).
	 *
	 * `$origin` dit où il est rendu : `menu`, ou `dashboard` quand l'accueil le porte en tête de page
	 * (`StructureTemplate::$entryInPage`). Celui de l'accueil ne porte pas l'id `entry-menu`, que la feuille
	 * du gabarit fixe en bas de l'écran hors du bureau.
	 */
	public function getMenu(\structure\Structure $eStructure, array $gestures, array $sections, string $origin = 'menu'): string {

		$idle = [];

		foreach($gestures as $section => $entries) {

			foreach($entries as $gesture) {

				[$icon, $label, $url, $intention] = $gesture + [3 => NULL];

				$suggestion = [
					'group' => $sections[$section],
					'section' => $section,
					'label' => $label,
					'vignette' => \Asset::icon($icon),
				];

				if($intention === NULL) {
					$suggestion['url'] = $url;
				} else {
					$suggestion['value'] = 'intention:'.$intention;
				}

				$idle[] = $suggestion;

			}

		}

		$id = match($origin) {
			'menu' => 'entry-menu',
			'dashboard' => 'entry-dashboard',
		};

		$chips = array_merge(...array_map(fn(string $ui) => $ui::getChips(), array_values(self::getIntentions())));

		return $this->getField(
			['id' => $id],
			[
				'structure' => $eStructure['id'],
			],
			s("Saisir…"),
			'entry-omnibox',
			$idle,
			array_values(array_unique($chips)),
			fullscreenBottom: ($origin === 'menu')
		);

	}

	/**
	 * Le champ dans la case d'un jour de récolte du planning, à la place de la quantité, de l'unité et de
	 * l'horloge : la série, le jour et le couple variété · calibre de la ligne y sont posés sans être dits, et
	 * voyagent avec chaque lecture (`cell`, `EntryLib::getCell()`). Il ne lit que la récolte, « 12 kg » suffit.
	 * Une page en porte un par case : il n'a pas d'id, `entry.js` le tient par sa classe. Après une écriture, il
	 * émet `entryWrite` depuis la case, et l'événement remonte jusqu'au document (`EntryField.send()`).
	 * `$part` dit la case d'une part de la série, dont le couple est fixé même vide : la part « Non spécifiée »
	 * poste le même couple que la case de la série entière, et seul ce drapeau les distingue.
	 */
	public function getHarvestCell(\structure\Structure $eStructure, \series\Cultivation $eCultivation, string $date, \plant\Variety $eVariety = new \plant\Variety(), \plant\Size $eSize = new \plant\Size(), bool $part = FALSE): string {

		$body = [
			'structure' => $eStructure['id'],
			'cell[cultivation]' => $eCultivation['id'],
			'cell[date]' => $date,
			'cell[variety]' => $eVariety->empty() ? '' : $eVariety['id'],
			'cell[harvestSize]' => $eSize->empty() ? '' : $eSize['id'],
		];

		if($part) {
			$body['cell[part]'] = 1;
		}

		return $this->getField(
			['class' => 'entry-cell'],
			$body,
			QuantityComplementUi::getBlankPlaceholder(),
			NULL,
			[],
			HarvestIntentionUi::getChips(),
			searchIcon: FALSE,
			scroll: TRUE
		);

	}

	/**
	 * Le champ, dans son enveloppe (`$attributes`), qui porte ce qu'il dit quand une écriture ne rend pas de
	 * réponse lisible (`EntryField.lost()`) : la base a pu la valider avant l'erreur.
	 * `$scroll` garde au champ sa largeur quels que soient ses jetons (`util-omnibox-scroll`), pour une case qui ne
	 * doit pas élargir sa colonne. `$fullscreenBottom` est celui du menu : là où la nav est en bas, le champ y prend
	 * l'écran en restant sous le pouce ; ailleurs, et pour tout autre champ, il reste en place.
	 */
	private function getField(array $attributes, array $body, string $placeholder, ?string $id, array $idle, array $chips, bool $searchIcon = TRUE, bool $scroll = FALSE, bool $fullscreenBottom = FALSE): string {

		$attributes['data-lost-write'] = s("La saisie a pu être enregistrée malgré l'erreur. Vérifiez la page, rechargée, avant de la refaire.");

		// Le libellé du jeton de chaque verbe, qu'un geste ou la liste des verbes pose (`EntryField.getIntentionToken()`).
		$attributes['data-intention-tokens'] = json_encode(array_map(fn(string $ui) => $ui::getToken(), self::getIntentions()));

		$h = '<div '.attrs($attributes).'>';

			$h .= \main\OmniboxUi::get(
				suggestionsUrl: '/entry/entry:read',
				suggestionsBody: $body,
				placeholder: $placeholder,
				id: $id,
				class: 'entry-field',
				fullscreen: $fullscreenBottom,
				fullscreenBottom: $fullscreenBottom,
				suggestionsIdle: $idle,
				chips: $chips,
				searchIcon: $searchIcon,
				chipsExplicit: TRUE,
				scroll: $scroll,
				// Effacer recommence la phrase, texte tapé compris.
				restart: TRUE
			);

		$h .= '</div>';

		return $h;

	}

	/**
	 * La semaine et la parcelle que l'ancien planning affiche en vue semaine : l'écriture s'en sert
	 * pour ne rendre que les lignes qui changent (`EntryField.setContext()`).
	 */
	public function getContext(string $week, \map\Plot $ePlot = new \map\Plot()): string {

		return '<div id="entry-context" '.attr('data-week', $week).($ePlot->notEmpty() ? ' '.attr('data-plot', $ePlot['id']) : '').'></div>';

	}

	/**
	 * L'accueil, qui porte le champ en tête de page : l'écriture et l'annulation y redessinent les blocs
	 * qu'elles changent (`HomeLib::getChange()`), d'après ceux que la page montre au moment de poster
	 * (`EntryField.setContext()`), et dont l'id porte le préfixe donné ici.
	 */
	public function getHomeContext(): string {

		return '<div id="entry-context" '.attr('data-origin', 'dashboard').' '.attr('data-block', \dashboard\DashboardUi::getBlockId('')).'></div>';

	}

	/**
	 * L'écran de chaque intention, par clé, en miroir de `EntryLib::getIntentions()` : celui d'une intention
	 * publique porte le nom de sa classe du moteur (`HarvestIntentionLib` pour `HarvestIntentionUi`), celui
	 * d'une intention déclarée par un package fermé vient de son observateur d'écran, sous la même garde.
	 * La présentation ne lit pas le registre du moteur : elle reçoit ses données, pas ses appels.
	 */
	public static function getIntentions(): array {

		$intentions = array_map(fn(string $class) => substr($class, 0, -3).'Ui', EntrySetting::INTENTIONS);

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return $intentions;
		}

		return $intentions + array_merge(...self::notify('getIntentions'));

	}

	/**
	 * Ce qu'une écriture ou une annulation de la production dit aux écrans que sa réponse ne réécrit pas,
	 * dans l'événement `entryWrite` ou `entryCancel` d'`entry.js` (`IntentionUi::getWriteEvent()`). Un
	 * temps seul n'a ni récolte, ni quantité, ni unité. Une intervention n'a d'espèce obligatoire que pour une
	 * récolte (`Task::properties()`, `plant`) : un désherbage peut n'en porter aucune.
	 */
	public static function getEvent(\structure\Structure $eStructure, \series\Task $eTask, string $date, \series\Harvest $eHarvest = new \series\Harvest(), ?float $quantity = NULL, ?string $unit = NULL): array {

		return [
			'structure' => $eStructure['id'],
			'harvest' => $eHarvest->empty() ? NULL : $eHarvest['id'],
			'task' => $eTask['id'],
			'cultivation' => $eTask['cultivation']->empty() ? NULL : $eTask['cultivation']['id'],
			'plant' => $eTask['plant']->empty() ? NULL : $eTask['plant']['id'],
			'date' => $date,
			'quantity' => $quantity,
			'unit' => $unit,
		];

	}

	/**
	 * « Annuler » sur la pastille d'une écriture : le délégué `[data-entry-cancel]` d'`entry.js` le
	 * poste à `doCancel`. La semaine et la parcelle ne sont pas figées ici : la pastille survit à une
	 * navigation ajax, qui ne remplace que `main`, et `cancel()` les lit sur la page affichée au
	 * moment du clic. Le message d'une réponse illisible voyage avec le lien pour la même raison.
	 *
	 * `$undo` est ce que l'intention a posé pour l'annulation (`afterCommit()`) : l'intention qui annule
	 * (`intention`) et le jeton de son ticket (`ticket`). Le délégué poste à l'adresse de cette intention.
	 */
	public static function getUndo(\structure\Structure $eStructure, array $undo): array {

		$attributes = [
			'href' => '#',
			'data-entry-cancel' => self::getIntentions()[$undo['intention']]::getCancelUrl(),
			'data-lost' => s("L'annulation a pu être enregistrée malgré l'erreur. Vérifiez la page, rechargée, avant de recommencer."),
			'post-structure' => $eStructure['id'],
		];

		foreach($undo as $name => $value) {
			$attributes['post-'.$name] = $value;
		}

		return [
			'label' => s("Annuler"),
			'attributes' => $attributes,
		];

	}

	/**
	 * Les suggestions d'une lecture, au format de `UtilOmnibox` : les intentions tant que le verbe
	 * manque, le refus s'il y en a un, puis celles de l'intention (`IntentionUi::getResults()`).
	 *
	 * `$cElement` et `$intentions` arrivent déjà filtrés par les mots en attente
	 * (`EntryLib::findObjects()`, `EntryLib::findIntentions()`).
	 *
	 * Un mot en frappe que les objets continuent tous de la même façon (`EntryLib::getCompletion()`) se
	 * complète par la première ligne : au doigt, il n'y a ni Tab ni flèche.
	 */
	public static function getResults(\structure\Structure $eStructure, array $reading, \Collection $cElement, array $intentions, \Collection $cAction = new \Collection(), ?array $completion = NULL): array {

		$results = [];

		if($reading['intention'] === NULL) {

			foreach($intentions as $intention) {
				$results[] = ['group' => s("Saisir"), 'label' => self::getIntentionLabel($intention), 'value' => 'intention:'.$intention];
			}

			return $results;

		}

		// Un refus prend la place de la ligne qui écrirait, dès la frappe : Entrée n'a rien à prendre. Ce
		// que le champ ne sait pas corriger se corrige ailleurs, par les liens que l'intention ajoute.
		if($reading['refusal'] !== NULL) {

			$results = [[
				'group' => s("À corriger"),
				'label' => self::getRefusal($reading),
				'notice' => TRUE,
			]];

			return array_merge($results, self::getIntentions()[$reading['intention']]::getRefusalResults($eStructure, $reading));

		}

		$ui = self::getIntentions()[$reading['intention']];

		$results = $ui::getResults($eStructure, $reading, $cElement, $cAction);

		// Ce que la phrase attend encore se dit en tête de liste, en ligne muette que rien ne choisit : la
		// question se lit dans la liste comme dans le champ, au-dessus des puces qui la précisent. Pas dans la
		// case vide, dont la liste ne s'ouvre qu'à la première frappe (`HarvestIntentionLib::setChips()`).
		$awaited = (self::getStep($reading) === $ui::getAwaitedStep() and $reading['blank'] === FALSE) ? $ui::getAwaited($reading) : NULL;

		if($awaited !== NULL) {

			array_unshift($results, [
				'group' => $ui::getLabel(),
				'label' => $awaited,
				'notice' => 'muted',
			]);

		}

		return self::withCompletion($results, $completion);

	}

	/**
	 * La ligne qui complète le mot en frappe, en tête de la famille de la première suggestion. Seule dans
	 * sa famille, la suggestion se choisit elle-même et mène plus loin : la ligne la doublerait. Une
	 * complétion n'existe que tant que l'objet manque (`EntryLib::getCompletion()`) : elle ne précède que
	 * des objets.
	 */
	private static function withCompletion(array $results, ?array $completion): array {

		if(
			$completion === NULL or
			count(array_filter($results, fn(array $result) => $result['group'] === ($results[0]['group'] ?? NULL))) < 2
		) {
			return $results;
		}

		array_unshift($results, [
			'group' => $results[0]['group'],
			'label' => $completion['word'].'…',
			'value' => 'completion',
		]);

		return $results;

	}

	public static function getIntentionLabel(string $intention): string {
		return self::getIntentions()[$intention]::getLabel();
	}

	/**
	 * Ce que `entry.js` lit d'une lecture : l'étape, la culture ou l'intervention retenue, les mots que
	 * rien n'a réclamés, la fin du mot en frappe à proposer, le texte du champ au repos et le refus.
	 *
	 * `object` dit l'objet nommé comme son jeton la porterait, avec les mots qui l'ont nommé, `intention` de même
	 * le verbe tapé et `plant` l'espèce qu'une intention lit après son objet. Une réponse choisie pose ce qui est
	 * déjà dit en jetons et retire ces mots du champ (`EntryField.setSaid()`) : le jeton de série prend la place de
	 * l'espèce, que l'écran garde pour la rendre en jeton si la série est retirée. `couple.named` et
	 * `complementsNamed` disent de même, par famille de puces et par complément, les mots que la puce choisie
	 * retire du champ.
	 *
	 * `tokens` redit la phrase en jetons quand l'intention lit des lignes (`IntentionUi::getLines()`) : le
	 * jour dit s'il n'est pas aujourd'hui, le verbe, l'objet, puis une ligne complète par jeton. L'écran les
	 * pose à la place des siens quand on choisit l'objet d'une ligne, et la frappe garde `rest` : le jeton
	 * choisi vient après les lignes qu'il suit. Le jour tapé devient le jeton du jour, sans quoi il partirait
	 * avec la frappe que les jetons remplacent.
	 */
	public static function getEntry(array $reading, ?array $completion = NULL): array {

		$step = self::getStep($reading);

		// L'intervention qu'un temps ajoute au planning n'a pas encore d'id : sa réponse se dit par sa culture, ou
		// par l'espèce de « Hors série ». Hors de Culture, elle n'a ni l'une ni l'autre et rien ne se répond.
		$added = ($reading['task']->notEmpty() and $reading['task']->exists() === FALSE);

		$entry = [
			'step' => $step,
			'cultivation' => $reading['cultivation']->empty() ? NULL : $reading['cultivation']['id'],
			'task' => $reading['task']->exists() ? $reading['task']['id'] : NULL,
			'addCultivation' => ($added and $reading['task']['cultivation']->notEmpty()) ? $reading['task']['cultivation']['id'] : NULL,
			'addNoSeries' => ($added and $reading['task']['cultivation']->empty() and $reading['task']['plant']->notEmpty()) ? $reading['task']['plant']['id'] : NULL,
			'pending' => $reading['pending'],
			'typingPrefix' => $reading['typingPrefix'],
			'completion' => ($completion === NULL) ? NULL : $completion['suffix'],
			'intention' => NULL,
			'object' => NULL,
			'plant' => NULL,
			'complementsNamed' => array_map(fn(array $words) => ($words === []) ? NULL : implode(' ', $words), $reading['complementsNamed']),
			'couple' => ($reading['couple'] === NULL) ? NULL : [
				'named' => array_map(fn(array $words) => implode(' ', $words), $reading['couple']['named']),
			],
			'outcome' => $reading['outcome'],
			'placeholder' => self::getPlaceholder($reading, $step),
			'refusal' => ($reading['refusal'] === NULL) ? NULL : self::getRefusal($reading),
			'tokens' => NULL,
			'rest' => NULL,
			'days' => NULL,
		];

		if($reading['intention'] === NULL) {
			return $entry;
		}

		$ui = self::getIntentions()[$reading['intention']];

		$entry['days'] = $ui::getDays($reading);

		if($reading['verb'] !== []) {

			$entry['intention'] = [
				'value' => $reading['intention'],
				'label' => $ui::getToken(),
				'named' => implode(' ', $reading['verb']),
			];

		}

		if($reading['plant']->notEmpty()) {

			$entry['plant'] = [
				'value' => $reading['plant']['id'],
				'label' => $reading['plant']['name'],
				'icon' => ($reading['plant']['fqn'] === NULL) ? NULL : \plant\PlantUi::getVignetteHref($reading['plant']['fqn']),
				'named' => implode(' ', $reading['plantNamed']),
			];

		}

		if($reading['named'] !== NULL) {

			$eElement = $reading['object'];

			$entry['object'] = [
				'value' => $eElement['id'],
				'label' => $eElement['name'],
				'icon' => $ui::getObjectIcon($eElement),
				'named' => implode(' ', $reading['named']),
			];

			$lines = $ui::getLines($reading);

			if($lines !== NULL) {

				$entry['tokens'] = [];

				if($reading['date'] !== \Clock::date()) {
					$entry['tokens'][] = ['group' => 'date', 'value' => $reading['date'], 'label' => DateComplementUi::getToken($reading['date']), 'icon' => NULL];
				}

				$entry['tokens'][] = ['group' => 'intention', 'value' => $reading['intention'], 'label' => $ui::getLabel(), 'icon' => NULL];
				$entry['tokens'][] = ['group' => 'object', 'value' => $entry['object']['value'], 'label' => $entry['object']['label'], 'icon' => $entry['object']['icon']];

				foreach($lines['tokens'] as $token) {
					$entry['tokens'][] = ['group' => 'line'] + $token;
				}

				$entry['rest'] = $lines['rest'];

			}

		}

		return $entry;

	}

	/**
	 * L'étape de la saisie : ce qui manque d'abord. Une fois l'objet résolu, l'intention dit ce qu'elle
	 * attend (`IntentionUi::getAwaitedStep()`) : la récolte une quantité, le temps une durée.
	 */
	public static function getStep(array $reading): string {

		if($reading['intention'] === NULL) {
			return 'intention';
		} else if($reading['object']->empty()) {
			return 'object';
		} else if($reading['question'] !== NULL) {
			return 'question';
		} else if($reading['outcome'] === EntryLib::WRITE) {
			return 'write';
		} else {
			return self::getIntentions()[$reading['intention']]::getAwaitedStep();
		}

	}

	/**
	 * Le refus, dit par l'intention quand il porte sur ce qu'elle seule sait (le jour, l'objet daté, le mot
	 * en trop), par le complément ou par la lecture sinon. Un refus ne vient qu'après le verbe
	 * (`EntryLib::readText()`).
	 */
	public static function getRefusal(array $reading): string {

		$refusal = $reading['refusal'];

		return self::getIntentions()[$reading['intention']]::getRefusal($reading) ?? match($refusal['reason']) {
			'object' => self::getObjectRefusal(),
			'duration' => DurationComplementUi::getRefusal(),
			'minutes' => DurationComplementUi::getMinutesRefusal(),
			'untracked' => DurationComplementUi::getUntrackedRefusal(),
			'whole' => UnitComplementUi::getWhole($refusal['unit']),
			'date' => DateComplementUi::getInvalid(),
			'week' => WeekComplementUi::getInvalid(),
			'cell' => s("Cette case ne saisit que la récolte de sa série."),
			'cellSeries' => s("Cette série ne se récolte pas ce jour-là."),
			'cellDate' => s("Pour un autre jour, saisissez dans sa colonne."),
		};

	}

	public static function getObjectRefusal(): string {
		return s("Rien ne porte ce nom.");
	}

	private static function getPlaceholder(array $reading, string $step): string {

		if($step === 'intention') {
			return s("Que saisir ?");
		}

		return self::getIntentions()[$reading['intention']]::getPlaceholder($reading, $step);

	}

}
?>
