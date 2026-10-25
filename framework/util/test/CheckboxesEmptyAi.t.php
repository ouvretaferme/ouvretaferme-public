<?php
namespace util;

/**
 * Chaque groupe de cases dit s'il sait se vider, et pourquoi.
 *
 * ⚠️ **Le contrat que ce test garde.** Un groupe de cases entièrement décoché ne poste rien : le
 * navigateur n'envoie que les cases cochées. `build()` ne voit donc pas la propriété, la valeur
 * précédente reste en base, et l'écran ne peut pas se vider. `FormUi::checkboxes()` sait poser un
 * champ caché qui répare ça — `['postEmpty' => TRUE]` — mais **il ne peut pas le faire par
 * défaut** : un même nom de champ peut être rendu par plusieurs appels, et il posterait alors une
 * valeur vide par appel, à chaque enregistrement, cases cochées comprises.
 *
 * Le choix se fait donc appel par appel, et ce test existe pour qu'il ne se fasse pas en silence :
 * un appel neuf fait échouer la suite tant qu'il n'a rien déclaré. Sans lui, l'option resterait un
 * piège que le prochain champ de cases reprendrait sans le voir.
 *
 * ⚠️ **L'inventaire se dérive du code par l'analyseur lexical de PHP**, et non par un `grep` : six
 * commentaires du dépôt citent `checkboxes()` pour en parler (`Member.u.php`, `VariantAi.u.php`,
 * `Offer.u.php`, `Sector.u.php`, `Follow.l.php`), et un relevé textuel les compterait comme des
 * appels.
 *
 * Lancement : dev/test package=util file=CheckboxesEmptyAi
 */
class CheckboxesEmptyAiTest extends \Test {

	/**
	 * Ce que chaque appel déclare, et la raison quand il ne peut pas se vider.
	 *
	 * La clé est `<chemin>:<ligne du nom de champ>` — le nom du champ seul ne suffirait pas,
	 * `qualities[]` étant rendu par trois écrans sans rapport.
	 */
	const APPELS = [

		// ---- peuvent se vider, et le déclarent par postEmpty ----

		// Les filières qu'une ferme suit : une par ligne de `quotation\Follow` depuis le 13/09/2026,
		// là où c'était le `set` `Configuration.quotationSectors` — le champ a suivi la colonne de
		// `base/structure` vers `quotation`. Ne rien suivre reste une réponse valable, et sans
		// postEmpty tout décocher ne posterait rien : la liste précédente resterait en base.
		// Le même champ est rendu par deux écrans (le panneau de paramétrage et l'encart de
		// saisie), tous deux par ce seul helper, donc un seul appel dans le dépôt.
		'commercialisation/quotation/ui/Sector.u.php' => ['sector[]', TRUE],

		// Les jours travaillés du contrat d'une période : aucun jour coché est une réponse que la mise à
		// jour doit pouvoir enregistrer. L'étape `postEmpty` de `hr\Contract::properties()` écarte la
		// chaîne vide avant le cast.
		'base/hr/ui/Contract.u.php' => ['days[]', TRUE],

		// ---- ne peuvent pas, ou n'en ont pas besoin ----

		// ⚠️ Quatre appels pour un seul nom de champ, un par famille de critères (mesuré à quatre
		// familles : production, resistance, work, quality). postEmpty poserait quatre valeurs
		// vides à chaque enregistrement, cases cochées comprises.
		'production/plant/ui/Plant.u.php' => ['criteria[]', FALSE],

		// ⚠️ L'écran ne montre que les dates à venir (`DateLib::getFutureByShop()`) : « tout
		// décoché » n'y veut pas dire « aucune date », et le champ ne peut pas parler pour celles
		// qu'il n'affiche pas.
		'commercialisation/shop/ui/Range.u.php' => ['datesList[]', FALSE],

		// Les jours du panneau « Programmer des dates de livraison » : une liste de travail lue par
		// DateLib::prepareCollection(), pas une propriété. Aucun jour coché est refusé
		// (`Date::days.check`), il n'y a donc rien à vider.
		'commercialisation/shop/ui/Date.u.php' => ['days[]', FALSE],

		// Les tuiles de l'étape des livraisons de la mise en route : une liste de travail lue par
		// ShopLib::prepareStartPoints(), pas une propriété. Aucune tuile cochée est refusé
		// (`Date::pointCreate.empty`), il n'y a donc rien à vider.
		'commercialisation/shop/ui/StartAi.u.php' => ['pointCreate[]', FALSE],

		// N'en a pas besoin : `qualities` porte un défaut posé après le cast
		// (`Structure.e.php`, `addAfterCast('default')`), donc une propriété absente devient un
		// Set vide et l'écran se vide déjà. Mesuré le 12/09/2026 : absent → 0.
		'base/structure/ui/Structure.u.php' => ['qualities[]', FALSE],
		// Le formulaire public d'adhésion ne fait que créer : aucune valeur précédente ne reste à
		// vider, et une propriété absente laisse la colonne vide.
		'commercialisation/amap/ui/MembershipRequest.u.php' => ['contracts[]', FALSE],

		// Filtres et sélections de travail : rien n'est enregistré sur une propriété, la liste
		// postée est lue telle quelle par la page.
		'ouvretaferme/admin/ui/StatisticAi.u.php' => ['roles', FALSE],
		'base/structure/ui/Member.u.php' => ['sections[]', FALSE],
		'base/structure/ui/Section.u.php' => [NULL, FALSE],
		'accounting/financialYear/ui/Archive.u.php' => ['documents[]', FALSE],
		'commercialisation/catalog/ui/Offer.u.php' => ['variantsList[]', FALSE],
		'production/series/ui/Series.u.php' => ['actions[]', FALSE],
		'production/work/ui/Action.u.php' => ['categories[]', FALSE],
		'production/plant/ui/VarietyRatingAi.u.php' => ['tags[]', FALSE],

		// Même propriété, trois écrans sans rapport : aucun ne s'est plaint de ne pas pouvoir se
		// vider, et `qualities` y porte le même défaut après cast que sur la structure.
		'base/structure/ui/Survey.u.php' => ['software[]', FALSE],
		'commercialisation/selling/ui/Item.u.php' => ['qualities[]', FALSE],
		'base/product/ui/Product.u.php' => ['qualities[]', FALSE],

		// Une tentative de quiz, une question à plusieurs réponses : chaque envoi est une tentative
		// neuve, rien n'est gardé en base qu'il faudrait vider, et une question sans case cochée
		// reçoit d'office la réponse vide (`ResponseLib::buildAnswers()`).
		'ouvretaferme/learning/ui/Quiz.u.php' => ['answer[<question>][]', FALSE],

		// Les engagements d'une inscription à une date : rien n'est enregistré par case, la liste
		// postée ne pose que l'heure de l'engagement, et « rien de coché » est refusé
		// (`DateEnrollment::committedAt.commitment`). Il n'y a donc rien à vider.
		'ouvretaferme/learning/ui/DateEnrollment.u.php' => ['commitment[]', FALSE],

		// Les financeurs d'une formation et d'une date, `funding`, un `set` nullable : n'en ont pas
		// besoin. Une propriété absente de l'entrée traverse quand même `build()` et s'y construit à
		// NULL, donc tout décocher vide déjà la colonne ; sur une date, NULL veut dire « reprend ceux
		// de la formation », ce que le décochage doit justement rendre. Le champ caché poserait un
		// ensemble vide, que l'étape `funding.none` ramènerait à NULL : il ne changerait rien.
		// Vérifié par `ouvretaferme/learning/test/Pricing.t.php`, `testFundingEmptied()`, le
		// 2026-10-03 : un financeur coché, puis rien de posté, rend NULL en base.
		'ouvretaferme/learning/ui/Course.u.php' => ['funding[]', FALSE],
		'ouvretaferme/learning/ui/Date.u.php' => ['funding[]', FALSE],

		// Une note de l'équipe se rédige et part, elle ne se modifie jamais : aucune valeur en base
		// qu'il faudrait vider. ⚠️ `structureTypes[]` est rendu par trois appels, un par famille
		// de types, et « rien de coché » est refusé (`Note::structureTypes.check`) ; `features[]`
		// vide est une réponse valable, que le défaut posé par la page couvre déjà.
		'internal/communication/ui/Note.u.php' => ['structureTypes[]', FALSE],

		// Les distributions tenues par un producteur : une liste de travail lue par
		// DistributionHolderLib::prepareDistributions(), pas une propriété. Le champ caché y
		// ajouterait une valeur vide que l'étape `designable` refuserait.
		'commercialisation/amap/ui/Distribution.u.php' => ['distributions[]', FALSE],

		// `paymentMethods`, colonne `json` non nulle. Une propriété absente de l'entrée traverse quand
		// même `build()`, qui parcourt les propriétés demandées et non les clés postées, et le cast
		// `array` rend `[]` d'un NULL sur une colonne non nullable (`Module::cast()`) : tout décocher
		// vide déjà la colonne. Le champ caché y poserait `['']`, que l'étape `available` refuserait.
		'commercialisation/amap/ui/Contract.u.php' => ['paymentMethods[]', FALSE],

		// `aid\Aid.productions`, colonne `json` non nulle : pour la même raison que `paymentMethods`,
		// tout décocher rend `[]`, qui veut dire toutes les productions. Le champ caché y poserait
		// `['']`, que l'étape `productions.unknown` refuserait. `labels[]` et `labelsBonus[]`, du même
		// fichier, sont des `?set` : rien de posté rend NULL, aucun label, sans champ caché.
		'production/aid/ui/Aid.u.php' => ['productions[]', FALSE],

		// Les tags d'une aide dans l'administration : une liste de travail lue par la page, relue par
		// `TagLib::getByIds()` puis alignée par `AidTagLib::synchronize()`. Rien de posté rend une liste
		// vide, qui retire tous les tags : il n'y a rien à vider.
		'production/aid/ui/Admin.u.php' => ['tags[]', FALSE],

		// Deux groupes dans ce fichier, et le régime d'un fichier est le OU de ses appels : il se
		// déclare donc par celui qui porte postEmpty.
		// - `absenceBy[]` le porte. Aucune case cochée veut dire « le producteur seul » (G3-case,
		//   validé le 05/10), et le `set` non nul ne se viderait pas sans lui ; l'étape
		//   `postEmpty` de `amap\Producer::properties()` écarte la chaîne vide avant le cast.
		// - `points[]` ne le porte pas, pour la raison de `paymentMethods` ci-dessus : le cast `array`
		//   vide déjà la colonne (vérifié en dev le 2026-09-27), et le champ caché y poserait `['']`,
		//   que l'étape `owned` refuserait.
		'commercialisation/amap/ui/Producer.u.php' => ['absenceBy[]', TRUE],

		// Les trois listes du périmètre d'une collecte de la mercuriale, un appel chacune
		// (`sectors[]`, `sections[]`, `departments[]`). Aucune n'a de valeur à vider : le périmètre
		// ne s'écrit qu'à l'ouverture, `CollectLib::getPropertiesUpdate()` ne rendant que `name` et
		// `description`. Et sur les filières, aucune case cochée est refusé (`Collect::sectors`,
		// `CollectLib::buildSectors()`).
		'commercialisation/quotation/ui/Collect.u.php' => ['sectors[]', FALSE],

		// Les accès d'une application tierce ne se choisissent qu'à sa création, aucune valeur en base
		// qu'il faudrait vider, et aucune case cochée est refusé (`Client::cScope.empty`).
		'framework/api/ui/Client.u.php' => ['cScope[]', FALSE],

	];

	public function testEveryCheckboxesCallIsDeclared(): void {

		$trouves = $this->scan();

		$this->assertTrue($trouves !== [], 'l\'inventaire ne doit jamais être vide : sans appel trouvé, ce test ne prouve rien');

		$declares = array_keys(self::APPELS);

		// Les deux sens : un fichier qui appelle sans déclarer, et une déclaration qui ne
		// correspond plus à aucun appel.
		$nonDeclares = array_values(array_diff(array_keys($trouves), $declares));
		$perimes = array_values(array_diff($declares, array_keys($trouves)));

		$this->assertEquals([], $nonDeclares, 'appels à checkboxes() sans régime déclaré');
		$this->assertEquals([], $perimes, 'régimes déclarés pour un fichier qui n\'appelle plus checkboxes()');

	}

	/** Ce que la déclaration dit de postEmpty doit être ce que le code fait. */
	public function testDeclaredRegimeMatchesTheCode(): void {

		$trouves = $this->scan();
		$ecarts = [];

		foreach($trouves as $fichier => $postEmpty) {

			if(array_key_exists($fichier, self::APPELS) === FALSE) {
				continue;
			}

			[, $attendu] = self::APPELS[$fichier];

			if($postEmpty !== $attendu) {
				$ecarts[] = $fichier.' : code '.($postEmpty ? 'postEmpty' : 'sans postEmpty').', déclaré '.($attendu ? 'postEmpty' : 'sans postEmpty');
			}

		}

		$this->assertEquals([], $ecarts, 'la déclaration et le code divergent');

	}

	/**
	 * Les fichiers qui appellent `checkboxes()`, et si l'un de leurs appels porte `postEmpty`.
	 *
	 * @return array<string, bool>
	 */
	private function scan(): array {

		$trouves = [];

		foreach($this->getFiles() as $chemin) {

			$code = file_get_contents($chemin);

			if(str_contains($code, 'checkboxes') === FALSE) {
				continue;
			}

			$tokens = token_get_all($code);

			for($i = 0; $i < count($tokens); $i++) {

				// Un appel, et non une mention : `->checkboxes(` — la flèche écarte les
				// commentaires, que `token_get_all()` range en T_COMMENT et ne découpe pas.
				if(
					is_array($tokens[$i]) === FALSE or
					$tokens[$i][0] !== T_STRING or
					$tokens[$i][1] !== 'checkboxes'
				) {
					continue;
				}

				$avant = $i - 1;
				while($avant >= 0 and is_array($tokens[$avant]) and $tokens[$avant][0] === T_WHITESPACE) {
					$avant--;
				}

				if(is_array($tokens[$avant]) === FALSE or $tokens[$avant][0] !== T_OBJECT_OPERATOR) {
					continue;
				}

				$relatif = substr($chemin, strlen(LIME_DIRECTORY) + 1);

				// ⚠️ Parenthèses obligatoires : `or` a une précédence plus faible que `=`, donc
				// `$x = $a or $b` s'évalue `($x = $a) or $b` — la seconde moitié est calculée puis
				// jetée, sans aucune erreur PHP. Ce test l'a attrapé sur lui-même.
				$trouves[$relatif] = (($trouves[$relatif] ?? FALSE) or $this->hasPostEmpty($tokens, $i));

			}

		}

		return $trouves;

	}

	/** L'appel qui commence au jeton $depart porte-t-il `postEmpty` avant sa parenthèse fermante ? */
	private function hasPostEmpty(array $tokens, int $depart): bool {

		$profondeur = 0;

		for($i = $depart; $i < count($tokens); $i++) {

			$token = $tokens[$i];

			if($token === '(') {
				$profondeur++;
			} else if($token === ')') {
				$profondeur--;
				if($profondeur === 0) {
					return FALSE;
				}
			} else if(is_array($token) and $token[0] === T_CONSTANT_ENCAPSED_STRING and trim($token[1], "'\"") === 'postEmpty') {
				return TRUE;
			}

		}

		return FALSE;

	}

	/** Tous les `.php` du dépôt, hors dépendances. */
	private function getFiles(): array {

		$fichiers = [];

		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator(LIME_DIRECTORY, \FilesystemIterator::SKIP_DOTS),
				// ⚠️ `.claude/` est exclu, et ce n'est pas un raccourci : il porte les maquettes et les
				// bancs des chantiers, du PHP qui imite le produit sans en faire partie. Un `mocks.php`
				// de planche y appelle `checkboxes()` pour dessiner un écran, et l'exiger déclaré
				// ferait échouer la suite sur un fichier que personne ne sert et que git ignore.
				fn($courant) => (
					str_contains($courant->getPathname(), '/node_modules') === FALSE and
					str_contains($courant->getPathname(), '/.git') === FALSE and
					str_contains($courant->getPathname(), '/.claude') === FALSE
				)
			)
		);

		foreach($iterator as $fichier) {
			if($fichier->isFile() and $fichier->getExtension() === 'php') {
				$fichiers[] = $fichier->getPathname();
			}
		}

		return $fichiers;

	}

}
?>
