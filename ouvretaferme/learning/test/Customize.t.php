<?php
namespace learning;

require_once __DIR__.'/LearningNetworkFixture.t.php';

/**
 * La personnalisation des pages de formations d'un réseau (lot A1) : l'enregistrement des quatre
 * réglages et son droit, le thème posé sur les pages, l'aperçu porté par l'adresse et la barre,
 * réservée au gérant et jamais rendue en intégration.
 *
 * Le décor est celui de `LearningNetworkFixtureTest`, réseau G3 du décor commun. L'utilisateur 1 y
 * est conseiller : il en devient administrateur le temps des tests qui demandent le gérant.
 *
 * Le lot A2 porte le même thème sur la fiche et les leçons d'une formation du réseau.
 *
 * Lancement : dev/test package=learning file=Customize
 */
class CustomizeTest extends LearningNetworkFixtureTest {

	const GREEN = '#2f6b4f';
	const YELLOW = '#f2c14e';
	const PALE_YELLOW = '#fff3b0';
	// Une couleur que la bande porte, pour les essais de la barre.
	const BROWN = '#8b4513';
	// Le jaune foncé jusqu'à 4,5:1 sur le fond le plus sombre où il se pose
	// (`ColorUi::getInkOnBackground()`) : 5,22:1 sur le fond du produit, 5,06:1 sur Lin adouci.
	const YELLOW_INK = '#80662A';
	// Deux teintes de la palette du socle : sur Lin le gris des annotations tient 4,55:1, sur Lilas
	// franc il tombe à 3,28:1 et les pages le remplacent par `INK_MUTED_DARK`.
	const CREAM = '#f3ede4';
	const LILAC = '#d1c6ec';
	// Les mêmes mêlées de moitié de blanc (`CommunityLib::BACKGROUND_SOFTENING`), telles que les pages
	// les posent. Lilas franc adouci garde le gris assombri, Lin adouci le gris du produit.
	const CREAM_SOFTENED = '#f9f6f2';
	const LILAC_SOFTENED = '#e8e3f6';
	// Abricot franc, de la seconde rangée de la palette, et le même adouci.
	const APRICOT = '#f2d0b2';
	const APRICOT_SOFTENED = '#f9e8d9';

	public function testUpdateWritesTheFourSettings(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$fw = new \FailWatch();

		CommunityLib::updateCustomize($this->eStructureNetwork, [
			'color' => self::GREEN,
			'customBackground' => self::CREAM,
			'customTitleFont' => "'Lobster', cursive",
			'customFont' => "'Lato', sans-serif"
		]);

		$this->assertTrue($fw->ok(), 'les quatre réglages s\'enregistrent');

		$eCommunity = $this->getNetworkCommunity();

		$this->assertEquals([self::GREEN, self::CREAM, "'Lobster', cursive", "'Lato', sans-serif"], [$eCommunity['color'], $eCommunity['customBackground'], $eCommunity['customTitleFont'], $eCommunity['customFont']]);

	}

	/**
	 * Un réglage refusé fait échouer l'ensemble : rien n'est écrit, pas même les réglages valides.
	 */
	public function testRefusedSettingWritesNothing(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCommunityBefore = $this->getNetworkCommunity();

		try {

			CommunityLib::updateCustomize($this->eStructureNetwork, [
				'color' => self::GREEN,
				'customBackground' => '#c9c9c9',
				'customTitleFont' => '',
				'customFont' => ''
			]);

			$this->assertFail('le fond refusé lève');

		} catch(\FailAction) {
		}

		$eCommunity = $this->getNetworkCommunity();

		$this->assertEquals($eCommunityBefore['color'], $eCommunity['color'], 'la couleur n\'est pas écrite');
		$this->assertEquals($eCommunityBefore['customBackground'], $eCommunity['customBackground'], 'le fond non plus');

	}

	public function testSaveIsReservedToTheManager(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$input = ['color' => self::GREEN, 'customBackground' => '', 'customTitleFont' => '', 'customFont' => ''];

		$this->actAs(new \user\User());
		$this->assertTrue(in_array($this->post($input), [\NotAllowedAction::class, \NotAllowedLoggedAction::class], TRUE), 'refusé sans compte');

		$this->actAs($this->eUserAdmin);
		$this->assertEquals(\NotAllowedAction::class, $this->post($input), 'refusé au conseiller du réseau');

		$this->assertTrue($this->getNetworkCommunity()['color'] !== self::GREEN, 'rien n\'est écrit');

		$this->becomeManager();

		$this->assertEquals(\ReloadAction::class, $this->post($input), 'enregistré par le gérant');
		$this->assertEquals(self::GREEN, $this->getNetworkCommunity()['color']);

	}

	/**
	 * Le thème sur la liste : la couleur pure pour les aplats, la couleur en texte quand elle se lit,
	 * foncée jusqu'à se lire sinon.
	 */
	public function testThemeOnThePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs(new \user\User());

		$this->save(['color' => self::GREEN, 'customBackground' => self::CREAM]);

		$settings = $this->executeView($this->getPath().'/formations', [])->settings;

		$this->assertEquals(self::GREEN, $settings['color'], 'l\'aplat prend la couleur');
		$this->assertEquals(self::GREEN, $settings['textInk'], 'le texte aussi, qui se lit');
		$this->assertEquals(\structure\Community::INK_LIGHT, $settings['ink'], 'l\'encre blanche sur le vert');
		$this->assertEquals([self::CREAM_SOFTENED, \structure\Community::INK_MUTED], [$settings['backgroundSoftened'], $settings['mutedInk']], 'le fond est posé adouci, le gris des annotations y tient');

		$this->save(['customBackground' => self::LILAC]);

		$settings = $this->executeView($this->getPath().'/formations', [])->settings;

		$this->assertEquals([self::LILAC_SOFTENED, \structure\Community::INK_MUTED_DARK], [$settings['backgroundSoftened'], $settings['mutedInk']], 'sur Lilas franc, même adouci, le gris des annotations s\'assombrit');

		$this->save(['customBackground' => '']);
		$this->storeColor(self::YELLOW);

		$settings = $this->executeView($this->getPath().'/formations', [])->settings;

		$this->assertEquals(self::YELLOW, $settings['color'], 'l\'aplat prend le jaune');
		$this->assertEquals(self::YELLOW_INK, $settings['textInk'], 'le texte prend le jaune foncé');

	}

	/**
	 * Le fond choisi dans « Arrière-plan » se pose mêlé de blanc sur les pages : la teinte de la
	 * palette, pure, est trop soutenue pour couvrir une page entière. Le réglage garde la teinte
	 * choisie, et les encres se calculent sur le fond posé, celui que le lecteur voit.
	 */
	public function testBackgroundSoftened(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['color' => self::GREEN, 'customBackground' => self::APRICOT, 'customDesign' => '']);

		$settings = CommunityLib::getSettings($this->getNetworkCommunity());

		$this->assertEquals(self::APRICOT, $settings['background'], 'le réglage reste la teinte choisie');
		$this->assertEquals(self::APRICOT_SOFTENED, $settings['backgroundSoftened'], 'le fond posé est mêlé de moitié de blanc');
		$this->assertEquals(\structure\Community::getTextInk(self::GREEN, self::APRICOT_SOFTENED, \structure\Community::INK_LIGHT), $settings['textInk'], 'le texte se juge sur le fond posé');
		$this->assertEquals(\structure\Community::getMutedInk(self::APRICOT_SOFTENED), $settings['mutedInk'], 'le gris des annotations aussi');

		$this->save(['customDesign' => 'bucolique']);

		$settings = CommunityLib::getSettings($this->getNetworkCommunity());

		$this->assertEquals(self::APRICOT_SOFTENED, $settings['backgroundSoftened'], 'sous un gabarit, la page et ses mélanges partent du fond posé');
		$this->assertEquals(\structure\Community::getTextInk(self::GREEN, self::APRICOT_SOFTENED, \structure\Community::INK_LIGHT), $settings['inks']['doc']['textInk'], 'et ses encres se calculent sur lui et le blanc des cartes');

		$this->save(['customBackground' => '', 'customDesign' => '']);

		$this->assertEquals(CommunityLib::BACKGROUND_DEFAULT, CommunityLib::getSettings($this->getNetworkCommunity())['backgroundSoftened'], 'sans fond choisi, celui du produit, tel quel');

	}

	/**
	 * La teinte des badges, jugée contre le seul blanc où leur pastille se mêle : un jaune très clair
	 * (2,59:1 relevé dans Chrome avant ces jetons) retombe sur l'encre sombre, sans gabarit comme sous
	 * un gabarit.
	 */
	public function testBadgeInkOnWhite(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		foreach(['', 'kraft', 'futur'] as $design) {

			$this->save(['customBackground' => '', 'customDesign' => $design]);
			$this->storeColor(self::PALE_YELLOW);

			$this->assertEquals(\structure\Community::INK_DARK, CommunityLib::getSettings($this->getNetworkCommunity())['whiteInk'], 'les badges prennent l\'encre sombre ('.($design ?: 'classique').')');

		}

		$this->save(['color' => '', 'customDesign' => 'kraft']);

		$this->assertEquals(NULL, CommunityLib::getSettings($this->getNetworkCommunity())['whiteInk'], 'sans couleur, les badges gardent celle du produit');

	}

	/**
	 * Sans couleur ni police, rien ne change : ni jeton de couleur, ni police posée.
	 */
	public function testNoSettingsNoTheme(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->actAs(new \user\User());

		$this->save(['color' => '', 'customBackground' => '', 'customTitleFont' => '', 'customFont' => '']);

		$html = $this->renderRoute($this->getPath().'/formations');

		$settings = CommunityLib::getSettings($this->getNetworkCommunity());

		$this->assertEquals([NULL, NULL], [$settings['color'], $settings['ink']], 'aucune couleur');
		$this->assertEquals(['title' => NULL, 'text' => NULL], $settings['fonts'], 'aucune police');
		$this->assertFalse(str_contains($html, 'fonts.googleapis.com'), 'aucune police à charger');

	}

	/**
	 * L'aperçu porté par l'adresse : seulement pour le gérant qui a demandé la barre, jamais en
	 * intégration, et une adresse qui porte une valeur que l'enregistrement refuserait renvoie à la
	 * barre sans aperçu.
	 */
	public function testPreview(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['color' => self::GREEN, 'customBackground' => '']);

		$preview = ['customize' => '1', 'color' => self::BROWN, 'customFont' => "'Lato', sans-serif"];

		$this->actAs(new \user\User());

		$data = $this->executeView($this->getPath().'/formations', $preview);

		$this->assertFalse($data->customize, 'pas de barre sans compte');
		$this->assertEquals(self::GREEN, $data->settings['color'], 'ni d\'aperçu');

		$this->actAs($this->eUserAdmin);

		$data = $this->executeView($this->getPath().'/formations', $preview);

		$this->assertFalse($data->customize, 'pas de barre pour le conseiller');

		$this->becomeManager();

		$data = $this->executeView($this->getPath().'/formations', $preview);

		$this->assertTrue($data->customize, 'la barre pour le gérant');
		$this->assertEquals(self::BROWN, $data->settings['color'], 'l\'aperçu de la couleur');
		$this->assertEquals("'Lato', sans-serif", $data->settings['customFont'], 'et de la police');

		$html = $this->renderRoute($this->getPath().'/formations', $preview);

		$this->assertTrue(str_contains($html, 'learning-customize-bar'), 'la barre est rendue');

		$data = $this->executeView($this->getPath().'/formations', $preview + ['embed' => '1']);

		$this->assertFalse($data->customize, 'jamais en intégration');
		$this->assertEquals(self::GREEN, $data->settings['color'], 'ni l\'aperçu');

		$html = $this->renderRoute($this->getPath().'/formations', $preview + ['embed' => '1']);

		$this->assertFalse(str_contains($html, 'learning-customize-bar'), 'la barre n\'est pas rendue en intégration');

		foreach([
			'couleur illisible' => ['color' => '#7A7A7A'],
			'couleur malformée' => ['color' => 'rouge'],
			'fond hors palette' => ['customBackground' => '#c9c9c9'],
			'police hors liste' => ['customFont' => "'Comic Sans MS', cursive"],
		] as $label => $invalid) {

			$this->assertEquals(\RedirectAction::class, $this->executeView($this->getPath().'/formations', $invalid + ['customize' => '1']), $label.' dans une adresse écrite à la main : retour à la barre');

		}

		$this->save(['customBackground' => self::CREAM]);

		$data = $this->executeView($this->getPath().'/formations', ['customize' => '1', 'customBackground' => '']);

		$this->assertEquals(NULL, $data->settings['customBackground'], 'un fond vide dans l\'adresse remet le fond par défaut');

	}

	/**
	 * L'essai d'un réglage dans la barre passe par `:doPreview` : refusé, il remonte par la chaîne
	 * d'erreur avec la clé de son message ; accepté, il renvoie à l'aperçu qui porte les quatre
	 * réglages. Rien n'est écrit dans un cas comme dans l'autre.
	 */
	public function testPreviewAction(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['color' => self::GREEN, 'customBackground' => '']);

		$settings = ['color' => self::GREEN, 'customBackground' => '', 'customTitleFont' => '', 'customFont' => ''];

		$this->actAs($this->eUserAdmin);

		$this->assertEquals(\NotAllowedAction::class, get_class($this->run(':doPreview', ['customBackground' => self::CREAM] + $settings)), 'refusé au conseiller');

		$this->becomeManager();

		foreach([
			['Community::customBackground.check', ['customBackground' => '#000000']],
			['Community::customBackground.check', ['customBackground' => self::YELLOW]],
			['Community::customBackground.check', ['customBackground' => '#f5f0e6']],
			['Community::color.contrast', ['color' => '#7A7A7A']],
		] as [$fail, $refused]) {

			$fw = new \FailWatch();

			$action = $this->run(':doPreview', $refused + $settings);

			$this->assertEquals(\FailAction::class, get_class($action), current($refused).' refusé');
			$this->assertTrue($fw->has($fail), current($refused).' remonte '.$fail);

		}

		$action = $this->run(':doPreview', ['color' => self::BROWN, 'customBackground' => self::CREAM] + $settings);

		$this->assertEquals(\RedirectAction::class, get_class($action), 'un réglage accepté renvoie à l\'aperçu');

		$url = new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);

		$this->assertTrue(str_ends_with($url, '/formations?customize=1&color='.urlencode(self::BROWN).'&customBackground='.urlencode(self::CREAM).'&customTitleFont=&customFont='), 'qui porte les quatre réglages : '.$url);

		$this->forgetMemoization();

		$this->assertEquals([self::GREEN, NULL], array_values($this->getNetworkCommunity()->extracts(['color', 'customBackground'])), 'rien n\'est écrit');

	}

	/**
	 * Le gabarit (lot A3a-2) : la page porte son nom sur la portée et charge sa feuille ; choisi sans
	 * couleur ni fond, il reçoit quand même le fond, le gris, les trois encres et la teinte qu'il lit
	 * sans repli, calculés sur la couleur du produit, que la page garde. Le gabarit classique ne porte
	 * rien.
	 */
	public function testDesignOnThePage(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['customDesign' => 'kraft', 'color' => '', 'customBackground' => '']);

		$this->assertEquals('kraft', $this->getNetworkCommunity()['customDesign'], 'le gabarit s\'enregistre');

		$this->actAs(new \user\User());

		$html = $this->renderRoute($this->getPath().'/formations');

		$this->assertTrue(str_contains($html, 'data-learning-design="kraft"'), 'la portée porte le gabarit');
		$this->assertTrue(str_contains($html, 'design-kraft.css'), 'et sa feuille est chargée');

		$settings = CommunityLib::getSettings($this->getNetworkCommunity());

		$this->assertEquals(CommunityLib::BACKGROUND_DEFAULT, $settings['backgroundSoftened'], 'le fond, sans couleur');
		$this->assertEquals(\structure\Community::getInk(CommunityLib::COLOR_DEFAULT), $settings['inks']['public']['ink'], 'les encres, calculées sur la couleur du produit');
		$this->assertEquals(CommunityLib::getTint(CommunityLib::COLOR_DEFAULT, CommunityLib::BACKGROUND_DEFAULT), $settings['tint'], 'et la teinte');
		$this->assertEquals(NULL, $settings['color'], 'la page garde la couleur du produit');

		$this->save(['customDesign' => '']);

		$html = $this->renderRoute($this->getPath().'/formations');

		$this->assertFalse(str_contains($html, 'data-learning-design'), 'classique, sans gabarit');
		$this->assertFalse(str_contains($html, 'design-kraft.css'), 'ni feuille');

	}

	/**
	 * La teinte d'un gabarit est la couleur mêlée à 18 % au fond posé, et elle reste claire : sous
	 * l'encre du texte, sous toutes les couleurs et sur les fonds de la palette, le texte plein tient
	 * 4,5:1 et le gris des annotations aussi, sur la teinte comme sur le fond où le bandeau peut se
	 * fondre. Un gabarit la reçoit toujours, le classique jamais.
	 */
	public function testTint(): void {

		$backgrounds = [CommunityLib::BACKGROUND_DEFAULT, self::APRICOT_SOFTENED, CommunityLib::getBackgroundSoftened('#d1c6ec')];

		foreach(['#000000', '#ffffff', '#2f7d55', '#1f7a8c', '#b5532a', '#7a4fb0', '#c0392b', '#2f6b4f', '#f2c14e', CommunityLib::COLOR_DEFAULT] as $color) {

			foreach($backgrounds as $background) {

				$tint = CommunityLib::getTint($color, $background);

				$this->assertEquals(self::mix($background, $color, CommunityLib::TINT_PART), $tint['background'], 'la teinte ('.$color.' sur '.$background.')');
				$this->assertTrue(\util\ColorUi::getContrastWith($tint['background'], '#ffffff') <= 2.0, 'elle reste claire ('.$color.' sur '.$background.')');
				$this->assertTrue(\util\ColorUi::getContrastWith($tint['background'], '#212529') >= 4.5, 'l\'encre du texte y tient 4,5:1 ('.$color.' sur '.$background.')');

				foreach([$tint['background'], $background] as $matter) {
					$this->assertTrue(\util\ColorUi::getContrastWith($matter, $tint['muted']) >= 4.5, 'le gris aussi, sur la teinte et sur le fond ('.$color.' sur '.$background.')');
				}

			}

		}

		$eCommunity = new \structure\Community([
			'color' => '#b5532a',
			'customBackground' => NULL,
			'customDesign' => 'kraft',
			'customTitleFont' => NULL,
			'customFont' => NULL,
		]);

		$this->assertEquals(CommunityLib::getTint('#b5532a', CommunityLib::BACKGROUND_DEFAULT), CommunityLib::getSettings($eCommunity)['tint'], 'la teinte est calculée sur le fond posé');

		$eCommunity['customDesign'] = NULL;

		$this->assertEquals(NULL, CommunityLib::getSettings($eCommunity)['tint'], 'aucune teinte pour le gabarit classique');

	}

	private static function mix(string $color, string $other, float $part): string {

		$a = sscanf($color, '#%02x%02x%02x');
		$b = sscanf($other, '#%02x%02x%02x');

		return vsprintf('#%02x%02x%02x', array_map(fn(int $x, int $y): int => (int)round($x * (1 - $part) + $y * $part), $a, $b));

	}

	public function testDesignPreview(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->becomeManager();

		$fw = new \FailWatch();

		$action = $this->run(':doPreview', ['customDesign' => 'inconnu']);

		$this->assertEquals(\FailAction::class, get_class($action), 'un gabarit inconnu est refusé');
		$this->assertTrue($fw->has('Community::customDesign.check'));

		$action = $this->run(':doPreview', ['customDesign' => 'futur']);
		$url = new \ReflectionProperty(\RedirectAction::class, 'url')->getValue($action);

		$this->assertTrue(str_ends_with($url, '/formations?customize=1&customDesign=futur'), 'l\'aperçu porte le gabarit : '.$url);

		$html = $this->renderRoute($this->getPath().'/formations', ['customize' => '1', 'customDesign' => 'futur']);

		$this->assertTrue(str_contains($html, 'data-learning-design="futur"'), 'l\'aperçu montre le gabarit');
		$this->assertEquals('futur', $this->executeView($this->getPath().'/formations', ['customize' => '1', 'customDesign' => 'futur'])->settings['customDesign'], 'la barre le sélectionne');
		$this->assertEquals(NULL, $this->getNetworkCommunity()['customDesign'], 'rien n\'est écrit');

	}

	/**
	 * Chaque police de gabarit est une entrée des listes de la barre : `getStyles()` et l'option vide
	 * la retrouvent par sa valeur, une faute de frappe la ferait tomber sans bruit.
	 */
	public function testDesignFontsAreListed(): void {

		foreach(LearningSetting::DESIGN_FONTS as $design => $fonts) {

			$this->assertTrue(in_array($design, LearningSetting::DESIGNS, TRUE), $design.' est un gabarit');
			$this->assertTrue(CustomizeUi::getTitleFont($fonts['title']) !== NULL, $design.' : '.$fonts['title'].' est une police de titre de la barre');
			$this->assertTrue(CustomizeUi::getFont($fonts['text']) !== NULL, $design.' : '.$fonts['text'].' est une police de texte de la barre');

		}

	}

	/**
	 * Sans couleur posée, la pastille de la barre et son sélecteur lisent le jeton du bandeau du
	 * groupe, celui que `StructureUi::getCommunityColorStyle()` laisse à sa valeur par défaut :
	 * la barre montre la couleur que la page porte déjà, et non une seconde teinte du produit.
	 */
	public function testColorDefault(): void {

		$getColors = new \ReflectionMethod(CustomizeUi::class, 'getColors');

		$settings = CommunityLib::getSettings(new \structure\Community(array_fill_keys(CommunityLib::getPropertiesCustomize(), NULL)));

		$html = $getColors->invoke(new CustomizeUi(), $settings);

		$this->assertEquals(2, substr_count($html, 'data-customize-default="--community"'), 'la pastille et le sélecteur lisent le jeton du bandeau');
		$this->assertFalse(str_contains($html, 'data-customize-default="--secondary"'), 'aucun ne lit la seconde couleur du produit');

		$settings = CommunityLib::getSettings(new \structure\Community(['color' => self::GREEN] + array_fill_keys(CommunityLib::getPropertiesCustomize(), NULL)));

		$html = $getColors->invoke(new CustomizeUi(), $settings);

		$this->assertFalse(str_contains($html, 'data-customize-default'), 'une couleur posée se montre telle quelle');

	}

	/**
	 * Une police de gabarit absente des listes de la barre ne nomme rien, sans avertissement. Appelé en direct, aucun gabarit livré n'étant dans ce cas,
	 * ce que `testDesignFontsAreListed()` garantit.
	 */
	public function testDesignFontMissing(): void {

		$getFonts = new \ReflectionMethod(\main\CustomizeUi::class, 'getFonts');

		$html = $getFonts->invoke(new CustomizeUi(), ['customTitleFont' => NULL, 'customFont' => NULL], ['title' => "'Absente', serif", 'text' => "'Lora', serif"]);

		$this->assertEquals(1, substr_count($html, '<option value="" data-family='), 'la police de titre absente ne nomme rien');
		$this->assertTrue(str_contains($html, '<option value="" data-family="Lora:wght@400;700">'), 'la police de texte présente reste nommée');

	}

	/**
	 * Un gabarit à police la pose et la charge tant que le réseau n'en a pas choisi, et la barre la
	 * nomme dans son option vide ; la police choisie dans la barre l'emporte. Un gabarit sans police
	 * ne pose rien.
	 */
	public function testDesignFonts(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['customDesign' => 'diplome', 'color' => '', 'customBackground' => '', 'customTitleFont' => '', 'customFont' => '']);

		$this->actAs(new \user\User());

		$html = $this->renderRoute($this->getPath().'/formations');

		$this->assertEquals(['title' => "'Kurale', serif", 'text' => "'Lora', serif"], CommunityLib::getSettings($this->getNetworkCommunity())['fonts'], 'le titre et le texte prennent les polices du gabarit');
		$this->assertTrue(str_contains($html, 'css2?family=Lora:wght@400;700&family=Kurale&display=swap'), 'les deux sont chargées, Lora avec son gras');

		$this->becomeManager();

		$html = $this->renderRoute($this->getPath().'/formations', ['customize' => '1']);

		$this->assertTrue(str_contains($html, '<option value="" data-family="Kurale">'), 'l\'option vide nomme la police de titre du gabarit');
		$this->assertTrue(str_contains($html, '<option value="" data-family="Lora:wght@400;700">'), 'et celle du texte');

		$this->save(['customFont' => "'Lato', sans-serif"]);

		$html = $this->renderRoute($this->getPath().'/formations');

		$this->assertEquals(['title' => "'Kurale', serif", 'text' => "'Lato', sans-serif"], CommunityLib::getSettings($this->getNetworkCommunity())['fonts'], 'la police choisie l\'emporte sur celle du gabarit, le titre garde celle du gabarit');
		$this->assertFalse(str_contains($html, 'Lora'), 'qui n\'est plus chargée');

		$this->save(['customDesign' => 'ami', 'customFont' => '']);

		$html = $this->renderRoute($this->getPath().'/formations', ['customize' => '1']);

		$this->assertEquals(['title' => NULL, 'text' => NULL], CommunityLib::getSettings($this->getNetworkCommunity())['fonts'], 'un gabarit sans police n\'en pose pas');
		$this->assertFalse(str_contains($html, 'fonts.googleapis.com/css2'), 'ni n\'en charge');
		$this->assertFalse(str_contains($html, '<option value="" data-family='), 'et son option vide ne nomme rien');

	}

	/**
	 * Le thème vaut aussi pour les étapes d'inscription et en intégration.
	 */
	public function testThemeOnStepsAndEmbed(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed(Course::ONSITE);
		$this->shareNetwork($eCourse);

		$eDate = new Date(['course' => $eCourse]);
		$eDate->build(DateLib::getPropertiesCreate(), $this->postSequences([
			[$this->day(10), '09:00', '17:00', $eCourse->getDefaultSequence()['mode'], 'Salle des fêtes, Crest'],
		]) + ['link' => 'https://visio.example.org/zztest', 'seats' => ''], new \PropertyList('create'));
		DateLib::create($eDate);

		$this->save(['color' => self::GREEN]);

		$this->actAs(new \user\User());

		$this->assertEquals(self::GREEN, $this->executeView($this->getPath().'/formations/'.$eDate['id'], [])->settings['color'], 'l\'étape 1 prend le thème');
		$this->assertEquals(self::GREEN, $this->executeView($this->getPath().'/formations', ['embed' => '1'])->settings['color'], 'l\'intégration aussi');

	}

	/**
	 * La fiche et les leçons d'une formation du réseau (lot A2) reçoivent le thème du réseau : le
	 * fond adouci, et le texte en couleur foncée quand la couleur ne se lit pas.
	 */
	public function testThemeOnCourseAndLesson(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$eCourse = $this->seed();
		$eLesson = $this->createLesson($this->createChapter($eCourse, 'Module'), 'Leçon', 5);

		$this->save(['customBackground' => self::CREAM]);
		$this->storeColor(self::YELLOW);

		$this->actAs($this->eUserAdmin);

		foreach([
			'la fiche' => 'formation/'.$eCourse['id'],
			'la leçon' => 'formation/lecon/'.$eLesson['id']
		] as $page => $path) {

			$data = $this->executeView($path, []);

			$this->assertEquals(self::YELLOW, $data->settings['color'], $page.' reçoit le thème du réseau');
			$this->assertEquals(self::YELLOW_INK, $data->settings['textInk'], $page.' avec le jaune foncé pour le texte');

		}

		$this->assertEquals([self::CREAM_SOFTENED, \structure\Community::INK_MUTED], [$data->settings['backgroundSoftened'], $data->settings['mutedInk']], 'le fond adouci, avec le gris des annotations');

		$this->save(['color' => '', 'customBackground' => '']);

		$settings = $this->executeView('formation/lecon/'.$eLesson['id'], [])->settings;

		$this->assertEquals([NULL, NULL], [$settings['color'], $settings['customBackground']], 'sans réglage, ni couleur ni fond');

	}

	/**
	 * Une formation de l'équipe ou d'un groupe de formation reste aux couleurs du catalogue.
	 */
	public function testNoThemeOutsideNetwork(): void {

		if($this->assertFixtures() === FALSE) {
			return;
		}

		$this->save(['color' => self::GREEN]);

		$this->actAs($this->eUserAdmin);

		$eCourseTeam = $this->createCourse(new \structure\Structure(), Course::PUBLISHED, Course::ACCEPTED);
		$eCourseEducation = $this->createCourse($this->getCommunity(self::COMMUNITY_EDUCATION), Course::PUBLISHED, Course::PRIVATE);

		$this->assertEquals(NULL, CommunityLib::getSettingsByCourse($eCourseTeam), 'aucun thème pour l\'équipe');
		$this->assertEquals(NULL, CommunityLib::getSettingsByCourse($eCourseEducation), 'ni pour un groupe de formation');

		$data = $this->executeView('formation/'.$eCourseTeam['id'], []);

		$this->assertEquals(NULL, $data->settings, 'la fiche de l\'équipe ne reçoit aucun thème');

	}

	private function getPath(): string {
		return 'reseau/'.\structure\CommunityLib::getByStructure($this->eStructureNetwork)['fqn'];
	}

	private function getNetworkCommunity(): \structure\Community {
		return \structure\CommunityLib::getByStructure($this->eStructureNetwork);
	}

	/**
	 * Les réglages écrits par le Lib, ceux qui ne sont pas donnés gardant leur valeur.
	 */
	private function save(array $input): void {

		$eCommunity = $this->getNetworkCommunity();

		$values = [];

		foreach(CommunityLib::getPropertiesCustomize() as $property) {
			$values[$property] = array_key_exists($property, $input) ? $input[$property] : ($eCommunity[$property] ?? '');
		}

		$fw = new \FailWatch();

		CommunityLib::updateCustomize($this->eStructureNetwork, $values);

		$fw->validate(onKo: fn(\FailWatch $fw) => throw new \Exception('réglages refusés : '.json_encode($fw->get())));

		$this->forgetMemoization();

	}

	/**
	 * L'utilisateur 1, conseiller du réseau dans le décor, en devient administrateur dans la
	 * transaction du test.
	 */
	private function becomeManager(): void {

		$eMember = \structure\MemberLib::getByUserAndStructure($this->eUserAdmin, $this->eStructureNetwork);
		$eMember['role'] = \structure\RoleLib::getOne($this->eStructureNetwork, 'admin');

		\structure\MemberLib::update($eMember, ['role']);

		$this->actAs($this->eUserAdmin);

	}

	/**
	 * L'enregistrement de la barre joué par son adresse : la classe de l'action levée.
	 */
	private function post(array $input): string {

		$action = $this->run(':doCustomize', $input);

		return ($action === NULL) ? 'aucune action' : get_class($action);

	}

	/**
	 * Une action de la barre jouée par son adresse : l'action levée.
	 */
	private function run(string $action, array $input): ?\Action {

		if(defined('LIME_URL') === FALSE) {
			define('LIME_URL', \Lime::getUrl());
		}

		$requestMethod = new \ReflectionProperty(\Route::class, 'requestMethod');
		$requestMethodInitial = $requestMethod->getValue();
		$requestMethod->setValue(NULL, 'POST');

		[$getInitial, $postInitial, $requestInitial] = [$_GET, $_POST, $_REQUEST];
		[$_GET, $_POST, $_REQUEST] = [[], $input, $input];

		$data = new \stdClass();
		$data->eUserOnline = \user\ConnectionLib::getOnline();

		try {
			\Page::execute(\Route::getCompatiblePages($this->getPath().'/'.$action), $data);
			return NULL;
		} catch(\Action $action) {
			return $action;
		} finally {
			[$_GET, $_POST, $_REQUEST] = [$getInitial, $postInitial, $requestInitial];
			$requestMethod->setValue(NULL, $requestMethodInitial);
		}

	}

}
?>
