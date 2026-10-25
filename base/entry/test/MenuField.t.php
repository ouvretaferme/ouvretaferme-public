<?php
namespace entry;

/**
 * Le champ de saisie du menu (lot S9, planche G3) prend la place du « + » en tête de la colonne.
 *
 * Quatre promesses :
 * - le champ n'existe que sous le drapeau `FEATURE_ENTRY` et pour un membre qui peut écrire au moins
 *   une intention (`EntryLib::getAllowed()`, le droit de `entry.p.php`) ; sinon le « + » reste ;
 * - vide, il propose les gestes du « + », tous univers compris, et chacun porte son univers, que
 *   `entry.js` lit pour ne garder que celui de l'écran ;
 * - il ne coûte aucune requête : l'en-tête, rendu avec le drapeau ouvert ou fermé, lit autant ;
 * - un écran qui le rend lui-même le prend au menu, sans en doubler l'id.
 *
 * Le compte des requêtes lit `Com_select` de la session MySQL, comme `dashboard/test/Create.t.php`.
 * Il est pris sur un second rendu : le premier remplit les mémoïsations des accesseurs.
 *
 * Aucune écriture : comptes et structures viennent du décor (`FixtureAiLib`).
 *
 * Lancement : dev/test package=entry file=MenuField
 */
class MenuFieldTest extends \Test {

	/** Administratrice de F1. */
	const F1_ADMIN = 'decor.f1.admin@example.org';

	/** Observateur de F1, qui ne saisit pas au planning. */
	const F1_OBSERVER = 'decor.f1.observateur@example.org';

	private \user\User $eUserInitial;
	private \Closure $enabledInitial;

	public function init(): void {

		$this->eUserInitial = \user\ConnectionLib::getOnline();
		$this->enabledInitial = EntrySetting::$enabled;

	}

	public function finalize(): void {

		\user\ConnectionLib::setOnline($this->eUserInitial);
		EntrySetting::$enabled = $this->enabledInitial;

	}

	public function testFieldUnderFlagAndRight(): void {

		$this->setEnabled(TRUE);

		$template = $this->getTemplate(self::F1_ADMIN);
		$this->assertTrue($this->invoke($template, 'hasEntry'), "drapeau ouvert, l'administratrice a le champ");
		$this->assertTrue($this->buildsEntryToken($template), "et le jeton du gabarit qui lui fait sa place");

		$template = $this->getTemplate(self::F1_OBSERVER);
		$this->assertFalse($template->getData()->eStructure->canPlanning(), "le décor rend l'observateur sans droit de saisie");
		$this->assertFalse($this->invoke($template, 'hasEntry'), "l'observateur garde le « + »");

		$this->setEnabled(FALSE);

		$template = $this->getTemplate(self::F1_ADMIN);
		$this->assertFalse($this->invoke($template, 'hasEntry'), "drapeau fermé, le « + » reste");
		$this->assertEquals('', $this->invoke($template, 'getStructureEntry'), "et le champ n'est pas rendu");
		$this->assertFalse($this->buildsEntryToken($template), "ni son jeton");

	}

	/**
	 * La liste du champ vide est celle des gestes du « + », dans le même ordre, et chaque geste porte
	 * l'univers et son libellé : `entry.js` la filtre sur l'univers de l'écran. Un geste que le champ
	 * sait faire n'a pas d'adresse : il pose son verbe dans le champ, la récolte (17/10) comme
	 * la vente, que le code fermé nomme (23/10), et l'intervention (HN-B16). Les autres gardent
	 * l'adresse du « + ».
	 */
	public function testIdleListIsTheGestures(): void {

		$this->setEnabled(TRUE);

		$template = $this->getTemplate(self::F1_ADMIN);

		$gestures = $this->invoke($template, 'getGestures');
		$sections = $this->invoke($template, 'getSections');

		$this->assertEquals(['production', 'commercialisation', 'accounting'], array_keys($gestures), "le décor a des gestes en production, en vente et en comptabilité");

		$intentions = array_filter(array_map(fn(array $entries) => array_values(array_filter(array_column($entries, 3))), $gestures));

		$this->assertEquals(['production' => ['intervention', 'harvest'], 'commercialisation' => ['sale']], $intentions, "l'intervention, la récolte et la vente nomment leur intention, et seules elles");

		$expected = [];

		foreach($gestures as $section => $entries) {
			foreach($entries as $gesture) {

				[, $label, $url, $intention] = $gesture + [3 => NULL];

				$expected[] = [$section, $sections[$section][1], $label] + (
					($intention === NULL) ?
						[3 => ['url' => $url]] :
						[3 => ['value' => 'intention:'.$intention]]
				);

			}
		}

		$idle = $this->getIdle($this->invoke($template, 'getStructureEntry'));

		$actual = array_map(fn(array $suggestion) => [
			$suggestion['section'],
			$suggestion['group'],
			$suggestion['label'],
			array_intersect_key($suggestion, array_flip(['url', 'value']))
		], $idle);

		$this->assertEquals($expected, $actual, "le champ vide propose les gestes du « + », rangés par univers, le verbe pour ceux qu'il sait faire, l'adresse pour les autres");

	}

	/**
	 * Un écran qui rend le champ lui-même (`StructureTemplate::$entryInPage`, l'accueil de S6) le prend
	 * au menu : la page n'a jamais deux `#entry-omnibox`, l'id du champ du menu, et le champ
	 * de l'écran propose les mêmes gestes que celui du menu. Il est dans le flux, donc le gabarit ne
	 * lui réserve pas de hauteur en bas de l'écran.
	 */
	public function testFieldInPage(): void {

		$this->setEnabled(TRUE);

		$template = $this->getTemplate(self::F1_ADMIN);
		$menu = $this->invoke($template, 'getStructureEntry');

		$template = $this->getTemplate(self::F1_ADMIN);
		$template->entryInPage = TRUE;

		$header = $this->invoke($template, 'getStructureEntry');
		$page = $template->getEntryInPage();

		$this->assertEquals(0, substr_count($header, 'id="entry-omnibox"'), "le menu cède le champ à l'écran");
		$this->assertEquals(1, substr_count($page, 'id="entry-omnibox"'), "l'écran le rend une fois");
		$this->assertEquals($this->getIdle($menu), $this->getIdle($page), "avec les gestes du menu");
		$this->assertFalse($this->buildsEntryToken($template), "sans le jeton qui réserve sa hauteur");

		$template = $this->getTemplate(self::F1_OBSERVER);
		$template->entryInPage = TRUE;

		$this->assertEquals('', $template->getEntryInPage(), "un membre sans le champ ne l'a pas non plus dans l'écran");

	}

	/** Le champ ne lit rien que le menu ne lisait déjà pour son « + ». */
	public function testFieldCostsNoQuery(): void {

		$this->setEnabled(FALSE);
		$closed = $this->countHeader();

		$this->setEnabled(TRUE);
		$open = $this->countHeader();

		$this->assertEquals($closed, $open, "l'en-tête lit autant avec le champ qu'avec le « + »");

	}

	private function countHeader(): int {

		$this->invoke($this->getTemplate(self::F1_ADMIN), 'getHeader');

		$template = $this->getTemplate(self::F1_ADMIN);

		$before = $this->countSelects();
		$this->invoke($template, 'getHeader');

		return $this->countSelects() - $before;

	}

	/** La liste que `data-suggestions-idle` porte au JS (`UtilOmnibox.getSuggestionsIdle()`). */
	private function getIdle(string $html): array {

		if(preg_match('/data-suggestions-idle="([^"]*)"/', $html, $match) !== 1) {
			throw new \Exception('Liste du champ vide introuvable');
		}

		return json_decode(html_entity_decode($match[1], ENT_QUOTES), TRUE);

	}

	private function buildsEntryToken(\StructureTemplate $template): bool {

		$this->invoke($template, 'buildSections');

		return str_contains($template->template, ' structure-entry ');

	}

	private function setEnabled(bool $enabled): void {
		EntrySetting::$enabled = fn(\structure\Structure $eStructure): bool => $enabled;
	}

	private function countSelects(): int {
		return (int)\structure\Structure::model()->pdo()->query('SHOW SESSION STATUS LIKE "Com_select"')->fetch()['Value'];
	}

	/** Un gabarit garni de ce que `Page.l.php` charge pour le compte, sur F1. */
	private function getTemplate(string $email): \StructureTemplate {

		$eUser = \user\User::model()
			->select(\user\User::getSelection())
			->whereEmail($email)
			->get();

		if($eUser->empty()) {
			throw new \Exception('Compte '.$email.' introuvable : monte le décor par dev/test package=structure file=FixtureAi');
		}

		\user\ConnectionLib::setOnline($eUser);

		$data = new \stdClass();
		$data->isLogged = TRUE;
		$data->eUserOnline = $eUser;
		$data->ccStructureUser = \structure\StructureLib::getByUser($eUser, withFeature: TRUE, index: ['type', 'id']);
		$data->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();
		$data->nStructureUser = $data->ccStructureUser->countByDepth();
		$data->hasLearning = FALSE;
		$data->notificationsUnseen = [];

		$template = new \StructureTemplate();
		$template->setData($data);

		return $template;

	}

	/** Les méthodes du gabarit sont `protected`, comme dans `dashboard/test/Create.t.php`. */
	private function invoke(\StructureTemplate $template, string $method, mixed ...$arguments): mixed {
		return new \ReflectionMethod($template, $method)->invoke($template, ...$arguments);
	}

}
?>
