<?php
namespace user;

/**
 * L'avatar d'un utilisateur (`UserAvatarLib`, `UserAvatar::properties()`, `media\UserVignetteLib`).
 *
 * Ce qui s'y prouve :
 * 1. une composition ne passe que si elle porte une valeur du catalogue fermé pour chaque famille, sa
 *    teinte quand la famille en a une, et la peau ; celle qui porte toutes les entrées d'un tuple exclu du
 *    catalogue, paire, triplet ou quadruplet, est refusée à part, sous `composition.excluded` ; une teinte est
 *    une couleur de sa palette ou une couleur libre en hexadécimal minuscule, gardée telle qu'elle est postée,
 *    toute autre est refusée sous `composition.tint`, et la peau n'a que sa palette ; une zone part de la teinte
 *    de son entrée quand le catalogue lui en donne une, sinon de celle de sa famille ; les yeux et la bouche sont
 *    des familles comme les autres, la couleur des yeux dans sa palette, la bouche sans teinte, une forme du
 *    visage n'est plus une clé de la composition, et une composition enregistrée avant les yeux et la bouche
 *    s'ouvre sur ceux par défaut ; une palette partagée, le métal des bijoux, n'a qu'une teinte, sous son nom, prise
 *    dans la palette seule et sans couleur libre, et une composition enregistrée avant elle s'ouvre sans bijou et sur
 *    son premier métal ; une chemise à carreaux enregistrée avant la chemise unie s'ouvre en chemise au motif
 *    carreaux, des carreaux toujours d'une autre teinte que la chemise ; une composition enregistrée avec un objet en
 *    main ou des gants s'ouvre sans eux, l'éditeur n'en offrant plus, et la fiche d'un avatar fait d'après une photo
 *    les perd à la lecture, comme toute clé que l'agent y aurait posée en plus de la peau, de l'entrée et de sa teinte ;
 * 2. la création met de côté la photo de profil, une mise à jour ne la touche pas, et la suppression la
 *    remet sur `User.vignette` et envoie l'image de l'avatar au nettoyage ;
 * 3. l'enregistrement de l'éditeur pose l'image de l'avatar sur `User.vignette`, la change de version au
 *    réenregistrement sans recopier l'image sur la photo, et un second enregistrement parti de la même
 *    page réenregistre l'avatar au lieu d'échouer sur le doublon ; l'image reste un PNG au fond transparent dans
 *    l'original et chacune de ses tailles ; une image d'avatar restée en JPEG se rend de nouveau une fois, sur le
 *    média de l'avatar et en PNG, sans changer la composition ni toucher à la photo, et jamais quand l'avatar ne
 *    remplace pas la photo ;
 * 4. décocher « Utiliser mon avatar comme photo de profil » rend la photo à `User.vignette` et envoie l'image
 *    de l'avatar au nettoyage, la recocher remet la photo de côté sur un média neuf, et un avatar créé décoché
 *    ne touche ni à la photo ni aux médias : la photo n'est jamais perdue ;
 * 5. un utilisateur dont l'avatar remplace la photo ne peut plus en envoyer, les autres le peuvent ;
 * 6. le nettoyage des médias garde active la photo mise de côté ;
 * 7. l'avatar n'est ouvert qu'à un membre d'une structure qui a la production, seule à avoir un plan en 3D.
 *
 * Décor : un utilisateur existant sans avatar, et un média fictif, dans une transaction annulée à la
 * fin de chaque méthode. Les fichiers d'image écrits par l'enregistrement sont effacés à la fin.
 *
 * Lancement : dev/test package=user file=UserAvatarAi
 */
class UserAvatarAiTest extends \dev\TransactionTest {

	const PHOTO = 'zztestavataraiphotoj';
	const IMAGE = 'zztestavataraiimagej';

	private User $eUserInitial;

	private array $hashes = [];

	protected function getTransactionModel(): \ModuleModel {
		return User::model();
	}

	public function setUp(): void {

		$this->eUserInitial = ConnectionLib::getOnline();

		parent::setUp();

	}

	public function tearDown(): void {

		parent::tearDown();

		ConnectionLib::setOnline($this->eUserInitial);

		// Les médias écrits par l'enregistrement disparaissent avec la transaction, pas leurs fichiers
		foreach($this->hashes as $hash) {
			array_map(unlink(...), glob(\storage\ServerLib::getDirectory('user-vignette').'/{,*/}'.$hash.'.*', GLOB_BRACE));
		}

	}

	public function testComposition(): void {

		$this->assertTrue($this->build($this->getDefault())->ok(), 'composition par défaut refusée');

		$this->assertTrue($this->build(['haut' => 'chemise-carreaux'] + $this->getDefault())->has('UserAvatar::composition.check'), 'entrée hors du catalogue acceptée');
		$this->assertTrue($this->build(['coiffure' => NULL] + $this->getDefault())->has('UserAvatar::composition.check'), 'famille obligatoire laissée vide acceptée');

		$composition = $this->getDefault();
		unset($composition['barbe']);
		$this->assertTrue($this->build($composition)->has('UserAvatar::composition.check'), 'famille manquante acceptée');

		$composition = $this->getDefault();
		unset($composition['lunettes-teinte']);
		$this->assertTrue($this->build($composition)->has('UserAvatar::composition.check'), 'famille à palette sans sa teinte acceptée');

		$fw = new \FailWatch();
		new UserAvatar()->build(['composition'], ['composition' => '5']);
		$this->assertTrue($fw->has('UserAvatar::composition.check'), 'composition qui n\'est pas un objet acceptée');

		$entriesComposed = array_merge(...array_column(UserAvatarLib::getFamilies(), 'entries'));

		// Chaque tuple du catalogue est refusé une fois complet, et accepté à une entrée près. Ceux qui nomment une entrée
		// hors de la composition relèvent de `UserAvatar.accessory`, qui ne passe pas par cette validation
		$played = 0;

		foreach(UserAvatarLib::getCatalog()['excluded'] as $tuple) {

			if(array_diff($tuple, $entriesComposed) !== []) {
				continue;
			}

			$played++;

			$composition = $this->getDefault();

			foreach($tuple as $entry) {
				$composition[$this->getFamily($entry)] = $entry;
			}

			$this->assertTrue($this->build($composition)->has('UserAvatar::composition.excluded'), 'exclusion '.implode(', ', $tuple).' acceptée');

			$family = $this->getFamily($tuple[0]);
			$composition[$family] = $this->getDefault()[$family];

			$this->assertTrue($this->build($composition)->ok(), 'exclusion '.implode(', ', $tuple).' refusée sans '.$tuple[0]);

		}

		$this->assertTrue($played > 0, 'aucun tuple du catalogue ne se compose, composition.excluded n\'est plus éprouvé');

	}

	public function testEyesAndMouth(): void {

		$catalog = UserAvatarLib::getCatalog();
		$families = UserAvatarLib::getFamilies();

		foreach(['yeux', 'bouche'] as $family) {

			foreach($families[$family]['entries'] as $entry) {
				$this->assertTrue($this->build([$family => $entry] + $this->getDefault())->ok(), $family.' '.$entry.' refusé');
			}

			$this->assertTrue($this->build([$family => NULL] + $this->getDefault())->has('UserAvatar::composition.check'), $family.' laissé vide accepté');

		}

		// La couleur des yeux est celle de la famille, d'une palette à elle, la bouche n'en a pas
		foreach($catalog['palettes'][$families['yeux']['palette']] as $color) {
			$this->assertTrue($this->build(['yeux-teinte' => $color] + $this->getDefault())->ok(), 'couleur des yeux '.$color.' refusée');
		}

		$this->assertTrue($this->build(['bouche-teinte' => $catalog['palettes']['tint'][0]] + $this->getDefault())->has('UserAvatar::composition.check'), 'teinte de la bouche acceptée');

		// La forme du visage a quitté l'éditeur : la clé ne passe plus, et une composition enregistrée avant les yeux et la
		// bouche s'ouvre sur ceux par défaut
		$this->assertTrue($this->build(['visage' => 'ronde'] + $this->getDefault())->has('UserAvatar::composition.check'), 'forme du visage acceptée');

		$composition = $this->getDefault();
		unset($composition['yeux'], $composition['yeux-teinte'], $composition['bouche']);
		$completed = UserAvatarLib::completeComposition($composition);

		$this->assertEquals($catalog['defaults']['entries']['yeux'], $completed['yeux'], 'composition sans yeux ouverte sans ceux par défaut');
		$this->assertEquals($catalog['defaults']['tints']['yeux'], $completed['yeux-teinte'], 'composition sans yeux ouverte sans la couleur par défaut');
		$this->assertEquals($catalog['defaults']['entries']['bouche'], $completed['bouche'], 'composition sans bouche ouverte sans celle par défaut');

	}

	public function testTint(): void {

		$palettes = UserAvatarLib::getCatalog()['palettes'];

		$this->assertTrue($this->build(['haut-teinte' => '#6b7a3a'] + $this->getDefault())->ok(), 'couleur libre refusée');
		$this->assertTrue($this->build(['haut-teinte' => '#000000', 'coiffure-teinte' => '#ffffff'] + $this->getDefault())->ok(), 'couleur libre aux bornes refusée');

		foreach([
			'#6B7A3A' => 'hexadécimal en majuscules',
			'#6b7' => 'hexadécimal court',
			'6b7a3a' => 'hexadécimal sans croisillon',
			'#6b7a3a80' => 'hexadécimal à quatre canaux',
			"#6b7a3a\n" => 'hexadécimal suivi d\'un saut de ligne',
			'kaki' => 'clé inconnue de la palette',
			'' => 'teinte vide',
		] as $tint => $case) {
			$this->assertTrue($this->build(['haut-teinte' => $tint] + $this->getDefault())->has('UserAvatar::composition.tint'), $case.' accepté');
		}

		foreach([NULL, 7049786, ['#6b7a3a']] as $tint) {
			$this->assertTrue($this->build(['haut-teinte' => $tint] + $this->getDefault())->has('UserAvatar::composition.tint'), 'teinte '.json_encode($tint).' qui n\'est pas un texte acceptée');
		}

		// Une couleur libre égale à une teinte de la palette, ou à celle d'une autre palette, reste la valeur postée
		foreach([$palettes['tint'][0], $palettes['hair'][0], '#6b7a3a'] as $tint) {

			$eUserAvatar = new UserAvatar();
			$eUserAvatar->build(['composition'], ['composition' => json_encode(['haut-teinte' => $tint] + $this->getDefault())]);

			$this->assertEquals($tint, $eUserAvatar['composition']['haut-teinte'] ?? NULL, 'teinte '.$tint.' réécrite par build()');

		}

		// Une teinte de palette n'est acceptée que dans la palette qu'on lui nomme. `build()` ne peut pas le montrer : les
		// palettes du catalogue ne portent que des couleurs en hexadécimal, qu'une couleur libre fait déjà passer
		$this->assertTrue(UserAvatarLib::isTint('kaki', ['kaki']), 'teinte de la palette nommée refusée');
		$this->assertFalse(UserAvatarLib::isTint('kaki', $palettes['frame']), 'teinte d\'une autre palette acceptée');

		// Le nom de palette de chaque famille se résout dans le catalogue, et aucune de ses teintes n'est refusée. Une
		// famille d'une palette partagée n'a pas de teinte à elle (testSharedPalette)
		foreach(UserAvatarLib::getFamilies() as $family => ['palette' => $palette]) {

			if(UserAvatarLib::getTintKey($family) !== $family.'-teinte') {
				continue;
			}

			foreach($palettes[$palette] as $tint) {
				$this->assertTrue($this->build([$family.'-teinte' => $tint] + $this->getDefault())->ok(), 'teinte '.$tint.' de la palette '.$palette.' refusée sur '.$family);
			}

			$this->assertTrue($this->build([$family.'-teinte' => '#6b7a3a'] + $this->getDefault())->ok(), 'couleur libre refusée sur '.$family);
			$this->assertTrue($this->build([$family.'-teinte' => 'kaki'] + $this->getDefault())->has('UserAvatar::composition.tint'), 'clé inconnue de la palette '.$palette.' acceptée sur '.$family);

		}

		// Les zones sans couleur : une famille sans palette n'a pas de teinte, et la peau ne sort pas de la sienne
		$familyPlain = array_key_first(array_filter(UserAvatarLib::getFamilies(), fn(array $family) => $family['palette'] === NULL));

		if($this->assertDecorPresent($familyPlain !== NULL, 'une famille sans palette dans catalog.json') === FALSE) {
			return;
		}

		$this->assertTrue($this->build([$familyPlain.'-teinte' => '#6b7a3a'] + $this->getDefault())->has('UserAvatar::composition.check'), 'couleur libre sur '.$familyPlain.', qui n\'a pas de palette, acceptée');
		$this->assertTrue($this->build(['peau' => '#7d4f33'] + $this->getDefault())->has('UserAvatar::composition.check'), 'peau hors de sa palette acceptée');

		if($this->assertDecorPresent(array_key_exists(UserAvatarLib::ACCESSORY, UserAvatarLib::getFamilies()) === FALSE, 'l\'accessoire hors des familles d\'une composition') === FALSE) {
			return;
		}

		$this->assertTrue($this->build([UserAvatarLib::ACCESSORY.'-teinte' => '#6b7a3a'] + $this->getDefault())->has('UserAvatar::composition.check'), 'couleur sur une zone qui n\'est pas une famille acceptée');

	}

	public function testSharedPalette(): void {

		$catalog = UserAvatarLib::getCatalog();

		if($this->assertDecorPresent($catalog['sharedPalettes'] !== [], 'une palette partagée dans catalog.json') === FALSE) {
			return;
		}

		$palette = $catalog['sharedPalettes'][0];
		$families = array_keys(array_filter(UserAvatarLib::getFamilies(), fn(array $family) => $family['palette'] === $palette));

		if($this->assertDecorPresent($families !== [], 'une famille de la palette partagée '.$palette) === FALSE) {
			return;
		}

		$family = $families[0];
		$default = $this->getDefault();

		// Une teinte, sous le nom de la palette, qui part de sa première couleur, et aucune par famille
		$this->assertEquals($catalog['palettes'][$palette][0], $default[$palette], 'métal par défaut autre que la première teinte de '.$palette);
		$this->assertEquals([], array_values(array_intersect(array_map(fn(string $family) => $family.'-teinte', $families), array_keys($default))), 'teinte par famille posée sur une palette partagée');

		foreach($catalog['palettes'][$palette] as $tint) {
			$this->assertTrue($this->build([$palette => $tint, $family => $this->getEntry($family)] + $default)->ok(), 'teinte '.$tint.' de '.$palette.' refusée');
		}

		$this->assertTrue($this->build([$palette => '#6b7a3a'] + $default)->has('UserAvatar::composition.tint'), 'couleur libre acceptée sur '.$palette);
		$this->assertTrue($this->build([$family.'-teinte' => $catalog['palettes'][$palette][0]] + $default)->has('UserAvatar::composition.check'), 'teinte par famille acceptée sur '.$family);

		$composition = $default;
		unset($composition[$palette]);
		$this->assertTrue($this->build($composition)->has('UserAvatar::composition.check'), 'composition sans '.$palette.' acceptée');

		// La rangée du métal suit la dernière famille qui le prend, et n'a d'effet qu'avec un bijou porté
		$shared = UserAvatarLib::getSharedPalettes($default)[$palette];

		$this->assertEquals($families, $shared['families'], 'familles de '.$palette.' mal relevées');
		$this->assertEquals($families[count($families) - 1], $shared['after'], 'rangée de '.$palette.' ailleurs qu\'après sa dernière famille');
		$this->assertFalse($shared['worn'], 'métal porté sans bijou');
		$this->assertTrue(UserAvatarLib::getSharedPalettes([$family => $this->getEntry($family)] + $default)[$palette]['worn'], 'métal non porté avec un bijou');

		// Une composition enregistrée avant les bijoux s'ouvre sans eux et sur le premier métal, et s'enregistre telle quelle
		$compositionBefore = $default;
		unset($compositionBefore[$palette]);

		foreach($families as $familyShared) {
			unset($compositionBefore[$familyShared]);
		}

		$completed = UserAvatarLib::completeComposition($compositionBefore);

		$this->assertEquals($catalog['palettes'][$palette][0], $completed[$palette], 'composition d\'avant les bijoux ouverte sans le premier métal');
		$this->assertEquals(NULL, $completed[$family], 'bijou posé sur une composition qui n\'en portait pas');
		$this->assertTrue($this->build($completed)->ok(), 'composition d\'avant les bijoux refusée une fois complétée');

	}

	public function testDefaultTint(): void {

		$catalog = UserAvatarLib::getCatalog();

		$skins = $catalog['palettes']['skin'];
		$skinDefault = $catalog['defaults']['skin'];

		// Une monture prend sa teinte au cran de la peau, sombre jusqu'au cran 5 et claire à partir du cran 6. Une entrée
		// sans teinte part de celle de sa famille, et une famille sans teinte de la première de sa palette, quelle que soit
		// la peau. Les valeurs sont celles de catalog.json, recopiées : les recalculer rejouerait la règle éprouvée
		foreach([
			['lunettes', 'lunettes-rondes', 5, '#23263a'],
			['lunettes', 'lunettes-rondes', 6, '#e8c872'],
			['lunettes', 'lunettes-rectangle', 5, '#23263a'],
			['lunettes', 'lunettes-rectangle', 6, '#e8c872'],
			['lunettes', 'lunettes-demi-lune', 5, '#23263a'],
			['lunettes', 'lunettes-demi-lune', 6, '#e8c872'],
			['lunettes', 'lunettes-chat', 5, '#23263a'],
			['lunettes', 'lunettes-chat', 6, '#e8c872'],
			['lunettes', 'lunettes-soleil', 5, '#23263a'],
			['lunettes', 'lunettes-soleil', 6, '#e8c872'],
			['lunettes', 'lunettes-aviateur', 5, '#23263a'],
			['lunettes', 'lunettes-aviateur', 6, '#e8c872'],
			['lunettes', 'lunettes-coeur', 5, '#23263a'],
			['lunettes', 'lunettes-coeur', 6, '#e8c872'],
			['lunettes', 'lunettes-etoile', 5, '#23263a'],
			['lunettes', 'lunettes-etoile', 6, '#e8c872'],
			['lunettes', 'lunettes-securite', 5, '#23263a'],
			['lunettes', 'lunettes-securite', 6, '#e8c872'],
			['lunettes', 'lunettes-rondes', 0, '#23263a'],
			['lunettes', 'lunettes-rectangle', 10, '#e8c872'],
			['lunettes', NULL, 5, '#9c7b4f'],
			['lunettes', NULL, 6, '#9c7b4f'],
			['haut', 'polo', 5, '#2fbb75'],
			['haut', 'polo', 6, '#2fbb75'],
			['veste', NULL, 6, '#1d4b53'],
		] as [$family, $entry, $step, $tint]) {
			$this->assertEquals($tint, UserAvatarLib::getDefaultTint($family, $entry, $skins[$step]), 'teinte de départ de '.$family.' sur '.json_encode($entry).' au cran '.$step.' de la peau');
		}

		// L'éditeur coche la teinte de départ dans la rangée de la famille : elle doit s'y trouver, sur chaque peau
		foreach(UserAvatarLib::getFamilies() as $family => ['palette' => $palette, 'entries' => $entries]) {

			if($palette === NULL) {
				continue;
			}

			foreach([NULL, ...$entries] as $entry) {
				foreach($skins as $step => $skin) {
					$this->assertTrue(in_array(UserAvatarLib::getDefaultTint($family, $entry, $skin), $catalog['palettes'][$palette], TRUE), 'teinte de départ de '.$family.' sur '.json_encode($entry).' au cran '.$step.' hors de la palette '.$palette);
				}
			}

		}

		// Une composition enregistrée sans teinte de lunettes, ce que l'éditeur écrit tant que les lunettes n'ont pas de
		// palette, se complète par la teinte de sa monture au cran de sa peau
		foreach([
			['lunettes-rondes', 5, '#23263a'],
			['lunettes-rondes', 6, '#e8c872'],
			['lunettes-rectangle', 5, '#23263a'],
			['lunettes-rectangle', 6, '#e8c872'],
			['lunettes-demi-lune', 5, '#23263a'],
			['lunettes-demi-lune', 6, '#e8c872'],
			['lunettes-chat', 5, '#23263a'],
			['lunettes-chat', 6, '#e8c872'],
			['lunettes-soleil', 5, '#23263a'],
			['lunettes-soleil', 6, '#e8c872'],
			['lunettes-aviateur', 5, '#23263a'],
			['lunettes-aviateur', 6, '#e8c872'],
			['lunettes-coeur', 5, '#23263a'],
			['lunettes-coeur', 6, '#e8c872'],
			['lunettes-etoile', 5, '#23263a'],
			['lunettes-etoile', 6, '#e8c872'],
			['lunettes-securite', 5, '#23263a'],
			['lunettes-securite', 6, '#e8c872'],
		] as [$entry, $step, $tint]) {

			$composition = ['lunettes' => $entry, 'peau' => $skins[$step]] + $this->getDefault();
			unset($composition['lunettes-teinte']);

			$completed = UserAvatarLib::completeComposition($composition);

			$this->assertEquals($tint, $completed['lunettes-teinte'], 'monture '.$entry.' complétée par une autre teinte que la sienne au cran '.$step);
			$this->assertEquals($skins[$step], $completed['peau'], 'peau de la composition changée en la complétant');
			$this->assertTrue($this->build($completed)->ok(), 'composition complétée sur '.$entry.' au cran '.$step.' refusée');

		}

		// Une peau sortie du catalogue prend son défaut avant la lecture du cran : lue telle quelle, elle n'a pas de cran et la recherche lève
		$composition = ['lunettes' => 'lunettes-rondes', 'peau' => '#7d4f33'] + $this->getDefault();
		unset($composition['lunettes-teinte']);

		$completed = UserAvatarLib::completeComposition($composition);

		$this->assertEquals($skinDefault, $completed['peau'], 'peau sortie du catalogue gardée sous une monture');
		$this->assertEquals('#23263a', $completed['lunettes-teinte'], 'monture complétée sans la teinte du cran de la peau par défaut');

		// Une teinte enregistrée ne suit pas la peau : seule une teinte absente se complète
		$composition = ['lunettes' => 'lunettes-rondes', 'lunettes-teinte' => '#c4363c', 'peau' => $skins[6]] + $this->getDefault();

		$this->assertEquals('#c4363c', UserAvatarLib::completeComposition($composition)['lunettes-teinte'], 'teinte de monture enregistrée remplacée par celle de la peau');

	}

	public function testCompleteComposition(): void {

		$default = $this->getDefault();
		$skins = UserAvatarLib::getCatalog()['palettes']['skin'];

		$skin = $skins[count($skins) - 1];

		if($this->assertDecorPresent($skin !== $default['peau'], 'une teinte de peau autre que celle par défaut') === FALSE) {
			return;
		}

		$composition = ['peau' => $skin, 'haut-teinte' => '#6b7a3a'] + $default;

		$this->assertEquals($composition, UserAvatarLib::completeComposition($composition), 'composition complète retouchée');

		$compositionPartial = $composition;
		unset($compositionPartial['haut'], $compositionPartial['haut-teinte']);

		// La zone complétée arrive en fin de composition : l'ordre des clés n'est pas ce que le cas éprouve
		$expected = ['haut' => $default['haut'], 'haut-teinte' => $default['haut-teinte']] + $composition;
		$completed = UserAvatarLib::completeComposition($compositionPartial);
		ksort($expected);
		ksort($completed);

		$this->assertEquals($expected, $completed, 'zone absente laissée sans son défaut');

		$completed = UserAvatarLib::completeComposition(['peau' => '#7d4f33'] + $composition);

		$this->assertEquals($default['peau'], $completed['peau'], 'peau sortie du catalogue gardée');
		$this->assertEquals('#6b7a3a', $completed['haut-teinte'], 'couleur libre perdue avec la peau');

		// Une composition enregistrée avant l'arrivée d'une famille facultative, celle du motif, s'ouvre sans elle et
		// s'enregistre telle quelle : aucune reprise des compositions n'accompagne une famille ajoutée
		$compositionBefore = $composition;
		unset($compositionBefore['motif'], $compositionBefore['motif-teinte']);

		$completed = UserAvatarLib::completeComposition($compositionBefore);

		$this->assertEquals(NULL, $completed['motif'], 'motif posé sur une composition qui n\'en portait pas');
		$this->assertTrue($this->build($completed)->ok(), 'composition d\'avant le motif refusée une fois complétée');

		// La chemise à carreaux enregistrée avant la chemise unie s'ouvre en chemise au motif carreaux, avec la teinte du
		// haut qu'elle portait et celle du motif par défaut, et s'enregistre telle quelle
		$compositionShirt = ['haut' => 'chemise-carreaux', 'haut-teinte' => '#c4363c'] + $composition;

		$completed = UserAvatarLib::completeComposition($compositionShirt);

		$this->assertEquals('chemise', $completed['haut'], 'chemise à carreaux gardée à l\'ouverture');
		$this->assertEquals('#c4363c', $completed['haut-teinte'], 'teinte de la chemise à carreaux perdue');
		$this->assertEquals('motif-carreaux', $completed['motif'], 'carreaux perdus avec la chemise à carreaux');
		$this->assertEquals(UserAvatarLib::getDefaultTint('motif', 'motif-carreaux', $completed['peau']), $completed['motif-teinte'], 'teinte du motif carreaux autre que la sienne par défaut');
		$this->assertTrue($this->build($completed)->ok(), 'chemise à carreaux refusée une fois complétée');

		// La chemise à carreaux à sa teinte par défaut garde des carreaux à la leur, qui en diffère déjà
		$compositionShirt = ['haut' => 'chemise-carreaux'] + $composition;
		unset($compositionShirt['haut-teinte'], $compositionShirt['motif'], $compositionShirt['motif-teinte']);

		$completed = UserAvatarLib::completeComposition($compositionShirt);

		$this->assertEquals($default['haut-teinte'], $completed['haut-teinte'], 'teinte par défaut de la chemise à carreaux perdue');
		$this->assertEquals(UserAvatarLib::getDefaultTint('motif', 'motif-carreaux', $completed['peau']), $completed['motif-teinte'], 'teinte par défaut des carreaux remplacée sur une chemise d\'une autre teinte');
		$this->assertDiffer($completed['haut-teinte'], $completed['motif-teinte'], 'carreaux fondus dans la chemise à sa teinte par défaut');

		// Une chemise à carreaux teintée comme les carreaux par défaut les garde visibles, dans une couleur de leur palette
		$tintMotif = UserAvatarLib::getDefaultTint('motif', 'motif-carreaux', $composition['peau']);
		$compositionShirt = ['haut' => 'chemise-carreaux', 'haut-teinte' => $tintMotif] + $composition;
		unset($compositionShirt['motif'], $compositionShirt['motif-teinte']);

		$completed = UserAvatarLib::completeComposition($compositionShirt);

		$this->assertEquals($tintMotif, $completed['haut-teinte'], 'teinte de la chemise à carreaux changée pour ses carreaux');
		$this->assertEquals('motif-carreaux', $completed['motif'], 'carreaux perdus avec la chemise teintée comme eux');
		$this->assertDiffer($completed['haut-teinte'], $completed['motif-teinte'], 'carreaux fondus dans la chemise teintée comme eux');
		$this->assertTrue(in_array($completed['motif-teinte'], UserAvatarLib::getCatalog()['palettes'][UserAvatarLib::getFamilies()['motif']['palette']], TRUE), 'teinte des carreaux prise hors de leur palette');
		$this->assertTrue($this->build($completed)->ok(), 'chemise teintée comme ses carreaux refusée une fois complétée');

		// Les pois choisis sur la chemise à carreaux restent, à leur teinte
		$compositionShirt = ['haut' => 'chemise-carreaux', 'motif' => 'motif-pois', 'motif-teinte' => '#e8c872'] + $composition;

		$completed = UserAvatarLib::completeComposition($compositionShirt);

		$this->assertEquals('chemise', $completed['haut'], 'chemise à carreaux aux pois gardée à l\'ouverture');
		$this->assertEquals('motif-pois', $completed['motif'], 'pois de la chemise à carreaux remplacés');
		$this->assertEquals('#e8c872', $completed['motif-teinte'], 'teinte des pois de la chemise à carreaux perdue');
		$this->assertTrue($this->build($completed)->ok(), 'chemise à carreaux aux pois refusée une fois complétée');

		// La cicatrice et les bijoux du nez, du sourcil et de la lèvre ont quitté l'éditeur (2026-10-22) : une
		// composition enregistrée qui les portait s'ouvre sans eux, le reste gardé tel quel, et s'enregistre ainsi
		$familiesRetired = [
			'cicatrice' => 'cicatrice-joue',
			'nez' => 'anneau-nez',
			'sourcil' => 'piercing-sourcil',
			'levre' => 'piercing-levre-fin',
		];

		$this->assertEquals([], array_intersect_key(UserAvatarLib::getFamilies(), $familiesRetired), 'famille retirée encore offerte par le catalogue');

		$compositionRetired = $familiesRetired + $composition;

		$this->assertTrue($this->build($compositionRetired)->has('UserAvatar::composition.check'), 'famille retirée acceptée à l\'enregistrement');

		$completed = UserAvatarLib::completeComposition($compositionRetired);

		$this->assertEquals($composition, $completed, 'composition aux familles retirées ouverte autrement que sans elles');
		$this->assertTrue($this->build($completed)->ok(), 'composition aux familles retirées refusée une fois complétée');

		// La forme du visage, retirée avant elles, quitte de même la composition ouverte
		$completed = UserAvatarLib::completeComposition(['visage' => 'ronde'] + $composition);

		$this->assertEquals($composition, $completed, 'forme du visage gardée à l\'ouverture');

		// L'objet en main a quitté l'éditeur (2026-10-24), avec les gants qu'il avait pris le 2026-10-22 :
		// une composition qui portait un objet, ou des gants d'avant cette date, seuls ou avec un objet, s'ouvre sans eux et
		// s'enregistre ainsi
		$this->assertFalse(array_key_exists('objet', UserAvatarLib::getFamilies()), 'objet en main encore offert par le catalogue');
		$this->assertFalse(array_key_exists('gants', UserAvatarLib::getFamilies()), 'gants encore offerts par le catalogue');

		foreach([
			'un objet' => ['objet' => 'arrosoir', 'objet-teinte' => '#3d7fb5'],
			'des gants comme objet' => ['objet' => 'gants', 'objet-teinte' => '#e8c872'],
			'des gants d\'avant le 2026-10-22' => ['gants' => 'gants', 'gants-teinte' => '#c4363c'],
			'des gants et un objet' => ['gants' => 'gants', 'gants-teinte' => '#c4363c', 'objet' => 'carnet', 'objet-teinte' => '#2f5d7c'],
		] as $case => $retired) {

			$compositionRetired = $retired + $composition;

			$this->assertTrue($this->build($compositionRetired)->has('UserAvatar::composition.check'), 'composition avec '.$case.' acceptée à l\'enregistrement');

			$completed = UserAvatarLib::completeComposition($compositionRetired);

			$this->assertEquals($composition, $completed, 'composition avec '.$case.' ouverte autrement que sans eux');
			$this->assertTrue($this->build($completed)->ok(), 'composition avec '.$case.' refusée une fois complétée');

		}

	}

	public function testExcludedTuples(): void {

		$composition = [
			'haut' => 'polaire-zip',
			'veste' => 'doudoune-longue',
			'couvre-chef' => 'bonnet',
			'barbe' => 'barbe-courte',
		] + $this->getDefault();

		$quadruplet = [['polaire-zip', 'doudoune-longue', 'bonnet', 'barbe-courte']];

		$this->assertTrue(UserAvatarLib::isExcluded($composition, $quadruplet), 'quadruplet complet accepté');
		$this->assertFalse(UserAvatarLib::isExcluded(['barbe' => NULL] + $composition, $quadruplet), 'quadruplet incomplet refusé');

		// Une teinte n'est pas une entrée : la valeur d'une couleur ne complète aucun tuple
		$this->assertFalse(UserAvatarLib::isExcluded(['haut-teinte' => 'bonnet', 'couvre-chef' => NULL] + $composition, [['polaire-zip', 'bonnet']]), 'teinte prise pour une entrée');

	}

	public function testFichePhoto(): void {

		$default = UserAvatarLib::getDefault();

		$eUserAvatar = new UserAvatar([
			'source' => UserAvatar::PHOTO,
			'composition' => [
				'corps' => ['peau' => $default['peau'], 'note' => "Lunettes posées sur le front"],
				'elements' => [
					['catalogue' => 'tasse', 'reglages' => ['couleur' => '#f4f1ea']],
					['catalogue' => $default['haut'], 'reglages' => ['couleur' => '#3a5f8a', 'note' => "Délavée"], 'description' => "Chemise à carreaux"],
					['catalogue' => 'gants', 'reglages' => ['couleur' => '#c4363c']],
					['catalogue' => 'rateau', 'reglages' => []],
					['catalogue' => $default['bas'], 'reglages' => []]
				],
				'photo' => "Prise devant la serre"
			]
		]);

		// Un objet du modèle que l'éditeur n'a jamais offert, le râteau, part comme la tasse et les gants
		$this->assertEquals([
			'corps' => ['peau' => $default['peau']],
			'elements' => [
				['catalogue' => $default['haut'], 'reglages' => ['couleur' => '#3a5f8a']],
				['catalogue' => $default['bas'], 'reglages' => []]
			]
		], UserAvatarLib::getFiche($eUserAvatar), 'fiche rendue d\'après une photo lue avec un objet en main ou ce qu\'elle porte d\'autre');

	}

	public function testSave(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$image = $this->getImage();

		UserAvatarLib::save(new UserAvatar(['user' => $eUser, 'composition' => $this->getDefault(), 'replacesPhoto' => TRUE]), $image);

		$vignette = $this->getVignette($eUser);
		$this->hashes[] = substr($vignette, 0, 20);

		$this->assertDiffer(self::PHOTO, substr($vignette, 0, 20), 'image de l\'avatar posée sur le média de la photo');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'photo de profil non mise de côté à la création');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;
		$eUserAvatar['composition'] = ['haut' => 'polo'] + $this->getDefault();

		UserAvatarLib::save($eUserAvatar, $image);

		$vignetteSaved = $this->getVignette($eUser);

		$this->assertEquals(substr($vignette, 0, 20), substr($vignetteSaved, 0, 20), 'média neuf au réenregistrement');
		$this->assertDiffer($vignette, $vignetteSaved, 'version inchangée au réenregistrement');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'image de l\'avatar recopiée sur la photo');
		$this->assertEquals('polo', UserAvatarLib::getByUser($eUser)['composition']['haut'], 'composition non réenregistrée');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue');

		unlink($image);

	}

	public function testSaveTransparent(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$image = $this->getImage();

		UserAvatarLib::save(new UserAvatar(['user' => $eUser, 'composition' => $this->getDefault(), 'replacesPhoto' => TRUE]), $image);

		$hash = substr($this->getVignette($eUser), 0, 20);
		$this->hashes[] = $hash;

		$this->assertEquals('p', $hash[19], 'image de l\'avatar enregistrée hors PNG');

		$files = glob(\storage\ServerLib::getDirectory('user-vignette').'/{,*/}'.$hash.'.png', GLOB_BRACE);

		// L'original et chacune des tailles s, m et l
		$this->assertEquals(count(\media\MediaSetting::$types['user-vignette']['imageFormat']) + 1, count($files), 'une taille de l\'image de l\'avatar manque');

		foreach($files as $file) {

			$resource = new \Imagick($file);

			$this->assertEquals('PNG', $resource->getImageFormat(), 'image de l\'avatar encodée hors PNG dans '.$file);
			$this->assertEquals(0.0, $resource->getImagePixelColor(0, 0)->getColorValue(\Imagick::COLOR_ALPHA), 'fond de l\'image de l\'avatar opaque dans '.$file);
			$this->assertEquals(1.0, $resource->getImagePixelColor((int)($resource->getImageWidth() / 2), (int)($resource->getImageHeight() / 2))->getColorValue(\Imagick::COLOR_ALPHA), 'personnage de l\'image de l\'avatar transparent dans '.$file);

			$resource->clear();

		}

		unlink($image);

	}

	public function testRerender(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		// Une image en JPEG, comme celles que la production enregistrait avant que la vignette d'utilisateur n'y admette le PNG
		$imageJpeg = $this->getImage('jpeg');

		UserAvatarLib::save(new UserAvatar(['user' => $eUser, 'composition' => $this->getDefault(), 'replacesPhoto' => TRUE]), $imageJpeg);

		$vignette = $this->getVignette($eUser);
		$this->hashes[] = substr($vignette, 0, 20);

		if($this->assertDecorPresent(\media\MediaUi::getExtension($vignette) === 'jpg', 'une image d\'avatar en JPEG') === FALSE) {
			return;
		}

		$eUser['vignette'] = $vignette;
		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		$this->assertTrue($eUserAvatar->acceptRerender(), 'image d\'avatar en JPEG laissée telle quelle');
		$this->assertEquals([$eUserAvatar['id']], UserAvatarLib::getForRerender([$eUser['id']])->getIds(), 'image d\'avatar en JPEG absente de la reprise');

		$composition = $eUserAvatar['composition'];

		$image = $this->getImage();

		UserAvatarLib::rerender($eUserAvatar, $image);

		$vignetteRendered = $this->getVignette($eUser);
		$hash = substr($vignetteRendered, 0, 20);
		$this->hashes[] = $hash;

		$this->assertEquals(substr($vignette, 0, 19).'p', $hash, 'image rendue de nouveau hors du média de l\'avatar ou hors PNG');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'photo mise de côté touchée par l\'image rendue de nouveau');
		$this->assertEquals($composition, UserAvatarLib::getByUser($eUser)['composition'], 'composition changée par l\'image rendue de nouveau');

		$files = glob(\storage\ServerLib::getDirectory('user-vignette').'/{,*/}'.$hash.'.png', GLOB_BRACE);

		$this->assertEquals(count(\media\MediaSetting::$types['user-vignette']['imageFormat']) + 1, count($files), 'une taille de l\'image rendue de nouveau manque');

		foreach($files as $file) {

			$resource = new \Imagick($file);
			$this->assertEquals(0.0, $resource->getImagePixelColor(0, 0)->getColorValue(\Imagick::COLOR_ALPHA), 'fond de l\'image rendue de nouveau opaque dans '.$file);
			$resource->clear();

		}

		// Une seule fois : l'image en PNG ne se rend plus, l'envoi d'un second onglet ne la remplace pas
		UserAvatarLib::rerender($eUserAvatar, $image);

		$this->assertEquals($vignetteRendered, $this->getVignette($eUser), 'image en PNG rendue de nouveau');
		$this->assertEquals([], UserAvatarLib::getForRerender([$eUser['id']])->getIds(), 'image en PNG reprise de nouveau');

		// Un avatar qui ne remplace pas la photo laisse `User.vignette` à la photo, en JPEG comme elle
		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;
		$eUserAvatar['replacesPhoto'] = FALSE;

		UserAvatarLib::save($eUserAvatar, $image);

		$eUser['vignette'] = $this->getVignette($eUser);
		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		$this->assertFalse($eUserAvatar->acceptRerender(), 'photo de profil prise pour l\'image de l\'avatar');
		$this->assertEquals([], UserAvatarLib::getForRerender([$eUser['id']])->getIds(), 'photo de profil reprise');

		UserAvatarLib::rerender($eUserAvatar, $image);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil remplacée par l\'image rendue de nouveau');

		// Une vignette sur le média de la photo n'est jamais l'image de l'avatar, quoi que dise la case
		$eUserAvatarPhoto = new UserAvatar([
			'replacesPhoto' => TRUE,
			'photo' => self::PHOTO.'#000000001',
			'user' => new User(['vignette' => self::PHOTO.'#000000002'])
		]);

		$this->assertFalse($eUserAvatarPhoto->acceptRerender(), 'média de la photo pris pour celui de l\'avatar');

		// Un avatar retiré d'un autre onglet avant l'envoi laisse la photo revenue
		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);
		UserAvatarLib::rerender($eUserAvatar, $image);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil remplacée par l\'image d\'un avatar retiré');

		unlink($imageJpeg);
		unlink($image);

	}

	public function testSaveTwice(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$image = $this->getImage();

		// Deux envois partis de la même page, qui ne connaissaient ni l'un ni l'autre d'avatar : le second porte encore la photo
		$eUserFirst = new User(['id' => $eUser['id'], 'vignette' => $eUser['vignette']]);
		$eUserSecond = new User(['id' => $eUser['id'], 'vignette' => $eUser['vignette']]);

		UserAvatarLib::save(new UserAvatar(['user' => $eUserFirst, 'composition' => $this->getDefault(), 'replacesPhoto' => TRUE]), $image);

		$vignette = $this->getVignette($eUser);
		$this->hashes[] = substr($vignette, 0, 20);

		UserAvatarLib::save(new UserAvatar(['user' => $eUserSecond, 'composition' => ['haut' => 'pull'] + $this->getDefault(), 'replacesPhoto' => TRUE]), $image);

		$this->assertEquals(1, UserAvatar::model()->whereUser($eUser)->count(), 'deux avatars pour un utilisateur');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'photo de profil écrasée par le second enregistrement');
		$this->assertEquals(substr($vignette, 0, 20), substr($this->getVignette($eUser), 0, 20), 'média neuf au second enregistrement');
		$this->assertEquals('pull', UserAvatarLib::getByUser($eUser)['composition']['haut'], 'composition du second enregistrement perdue');

		unlink($image);

	}

	public function testPhotoLifecycle(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$eUserAvatar = new UserAvatar([
			'user' => $eUser,
			'composition' => $this->getDefault(),
			'replacesPhoto' => TRUE,
		]);

		UserAvatarLib::create($eUserAvatar);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'photo de profil non mise de côté à la création');

		// L'image de l'avatar prend la place de la photo, comme le ferait UserVignetteLib::putFile()
		$eUser['vignette'] = self::IMAGE.'#000000001';
		UserLib::update($eUser, ['vignette']);

		$eUserAvatar['composition'] = ['haut' => 'polo'] + $this->getDefault();
		UserAvatarLib::update($eUserAvatar, ['composition']);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'photo de profil touchée par une mise à jour');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);

		$this->assertTrue(UserAvatarLib::getByUser($eUser)->empty(), 'avatar non supprimé');
		$this->assertEquals(self::PHOTO.'#000000001', User::model()->select('vignette')->whereId($eUser['id'])->getValue('vignette'), 'photo de profil non revenue');
		$this->assertEquals(\media\Media::DELETED, \media\Media::model()->select('status')->whereHash(self::IMAGE)->getValue('status'), 'image de l\'avatar laissée active');

	}

	/**
	 * La case de l'éditeur, cochée, décochée puis recochée sur le même avatar : la photo n'est jamais perdue, et
	 * l'image d'un avatar qui cesse de la remplacer part au nettoyage.
	 */
	public function testReplacesPhoto(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$image = $this->getImage();

		UserAvatarLib::save(new UserAvatar(['user' => $eUser, 'composition' => $this->getDefault(), 'replacesPhoto' => TRUE]), $image);

		$vignette = $this->getVignette($eUser);
		$this->hashes[] = substr($vignette, 0, 20);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'cochée, photo non mise de côté');

		UserAvatarLib::save($this->getSaved($eUser, FALSE), $image);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'décochée, photo non revenue');
		$this->assertEquals(NULL, $this->getPhoto($eUser), 'décochée, photo encore mise de côté');
		$this->assertEquals(\media\Media::DELETED, \media\Media::model()->select('status')->whereHash(substr($vignette, 0, 20))->getValue('status'), 'décochée, image de l\'avatar laissée active');

		UserAvatarLib::save($this->getSaved($eUser, FALSE), $image);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'décochée, photo remplacée au réenregistrement');

		UserAvatarLib::save($this->getSaved($eUser, TRUE), $image);

		$vignetteRechecked = $this->getVignette($eUser);
		$this->hashes[] = substr($vignetteRechecked, 0, 20);

		$this->assertDiffer(self::PHOTO, substr($vignetteRechecked, 0, 20), 'recochée, image posée sur le média de la photo');
		$this->assertDiffer(substr($vignette, 0, 20), substr($vignetteRechecked, 0, 20), 'recochée, image posée sur le média parti au nettoyage');
		$this->assertEquals(self::PHOTO.'#000000001', $this->getPhoto($eUser), 'recochée, photo non mise de côté');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'photo de profil non revenue à la suppression');

		unlink($image);

	}

	/**
	 * Un avatar créé décoché ne touche ni à la photo ni aux médias, et sa suppression non plus.
	 */
	public function testCreateUnchecked(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		$image = $this->getImage();

		$cMediaBefore = \media\Media::model()->whereUser($eUser)->count();

		UserAvatarLib::save(new UserAvatar(['user' => $eUser, 'composition' => $this->getDefault(), 'replacesPhoto' => FALSE]), $image);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'décochée, photo remplacée à la création');
		$this->assertEquals(NULL, $this->getPhoto($eUser), 'décochée, photo mise de côté à la création');
		$this->assertEquals($cMediaBefore, \media\Media::model()->whereUser($eUser)->count(), 'décochée, image de l\'avatar enregistrée');

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = $eUser;

		UserAvatarLib::delete($eUserAvatar);

		$this->assertEquals(self::PHOTO.'#000000001', $this->getVignette($eUser), 'décochée, photo touchée par la suppression');
		$this->assertEquals(\media\Media::ACTIVE, \media\Media::model()->select('status')->whereHash(self::PHOTO)->getValue('status'), 'décochée, photo envoyée au nettoyage par la suppression');

		unlink($image);

	}

	public function testUploadRefused(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		ConnectionLib::setOnline($eUser);

		$this->assertTrue(new \media\UserVignetteLib()->buildElement()->is($eUser), 'envoi de photo refusé sans avatar');

		UserAvatarLib::create(new UserAvatar([
			'user' => $eUser,
			'composition' => $this->getDefault(),
			'replacesPhoto' => TRUE,
		]));

		try {
			new \media\UserVignetteLib()->buildElement();
			$this->assertFail('envoi de photo accepté avec un avatar');
		} catch(\NotAllowedAction) {
			$this->assertPass();
		}

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['replacesPhoto'] = FALSE;
		UserAvatarLib::update($eUserAvatar, ['replacesPhoto']);

		$this->assertTrue(new \media\UserVignetteLib()->buildElement()->is($eUser), 'envoi de photo refusé avec un avatar qui ne la remplace pas');

	}

	public function testCleanKeepsPhoto(): void {

		$eUser = $this->getUser();

		if($eUser->empty()) {
			return;
		}

		UserAvatarLib::create(new UserAvatar([
			'user' => $eUser,
			'composition' => $this->getDefault(),
			'replacesPhoto' => TRUE,
		]));

		$eUser['vignette'] = self::IMAGE.'#000000001';
		UserLib::update($eUser, ['vignette']);

		// Le statut qu'un nettoyage pose avant de chercher les médias encore servis : MediaLib n'a pas d'écriture pour le poser seul
		\media\Media::model()
			->whereHash(self::PHOTO)
			->update(['status' => \media\Media::SEARCHING]);

		\media\MediaLib::getInstance('user-vignette')->clean();

		$this->assertEquals(\media\Media::ACTIVE, \media\Media::model()->select('status')->whereHash(self::PHOTO)->getValue('status'), 'photo mise de côté non réactivée');

	}

	public function testOpenByStructures(): void {

		$ccStructureOther = new \Collection([
			\structure\Structure::AMAP => new \Collection([
				4000000001 => new \structure\Structure(['id' => 4000000001, 'type' => \structure\Structure::AMAP]),
			]),
			\structure\Structure::SHOP => new \Collection([
				4000000002 => new \structure\Structure(['id' => 4000000002, 'type' => \structure\Structure::SHOP]),
			]),
		]);

		$this->assertFalse(\map\SceneLib::isOpenByStructures($ccStructureOther), 'avatar ouvert à un compte sans structure de production');

		$ccStructureProducer = clone $ccStructureOther;
		$ccStructureProducer[\structure\Structure::PRODUCER] = new \Collection([
			4000000003 => new \structure\Structure(['id' => 4000000003, 'type' => \structure\Structure::PRODUCER]),
		]);

		$this->assertTrue(\map\SceneLib::isOpenByStructures($ccStructureProducer), 'avatar fermé alors qu\'une structure du compte a la production');

		$this->assertFalse(\map\SceneLib::isOpenByStructures(new \Collection()), 'avatar ouvert à un compte sans structure');

	}

	private function getDefault(): array {
		return UserAvatarLib::getDefault();
	}

	/**
	 * La première entrée d'une famille que la composition par défaut peut porter.
	 */
	private function getEntry(string $family): string {

		foreach(UserAvatarLib::getFamilies()[$family]['entries'] as $entry) {

			if(UserAvatarLib::isExcluded([$family => $entry] + $this->getDefault(), UserAvatarLib::getCatalog()['excluded']) === FALSE) {
				return $entry;
			}

		}

		throw new \Exception('No entry of '.$family.' fits the default composition');

	}

	private function getFamily(string $entry): string {

		foreach(UserAvatarLib::getFamilies() as $family => ['entries' => $entries]) {

			if(in_array($entry, $entries, TRUE)) {
				return $family;
			}

		}

		throw new \Exception('Entry '.$entry.' not in catalog');

	}

	/**
	 * Une image comme celle que rend l'éditeur, un PNG carré à sa taille, que les tailles s et m réduisent, et au fond
	 * transparent autour d'un personnage opaque.
	 */
	private function getImage(string $format = 'png'): string {

		$file = tempnam(sys_get_temp_dir(), 'avatar');

		$draw = new \ImagickDraw();
		$draw->setFillColor('#2fbb75');
		$draw->rectangle(128, 128, 383, 383);

		$resource = new \Imagick();
		$resource->newImage(512, 512, 'transparent');
		$resource->drawImage($draw);
		$resource->setImageFormat($format);
		$resource->writeImage($format.':'.$file);

		return $file;

	}

	private function getVignette(User $eUser): ?string {

		return User::model()
			->select('vignette')
			->whereId($eUser['id'])
			->getValue('vignette');

	}

	private function build(array $composition): \FailWatch {

		$fw = new \FailWatch();

		new UserAvatar()->build(['composition'], ['composition' => json_encode($composition)]);

		return $fw;

	}

	/**
	 * Un utilisateur sans avatar, dont la photo de profil devient un média fictif : la vraie n'est ni lue ni écrite.
	 */
	private function getUser(): User {

		$eUser = User::model()
			->select('id', 'vignette')
			->join(UserAvatar::model(), 'm1.id = m2.user', 'LEFT')
			->where('m2.id IS NULL')
			->sort(['m1.id' => SORT_ASC])
			->get();

		if($this->assertTrue($eUser->notEmpty(), 'aucun utilisateur sans avatar en base') === FALSE) {
			return new User();
		}

		foreach([self::PHOTO, self::IMAGE] as $hash) {

			// Un média fictif, sans fichier : build() n'a rien à vérifier sur une ligne que seul le test lit
			$eMedia = new \media\Media([
				'user' => $eUser,
				'type' => 'user-vignette',
				'hash' => $hash,
				'status' => \media\Media::ACTIVE,
			]);

			\media\Media::model()->insert($eMedia);

		}

		$eUser['vignette'] = self::PHOTO.'#000000001';
		UserLib::update($eUser, ['vignette']);

		return $eUser;

	}

	/**
	 * L'avatar enregistré, tel que la page le relit avant de lui appliquer ce que l'éditeur poste.
	 */
	private function getSaved(User $eUser, bool $replacesPhoto): UserAvatar {

		$eUserAvatar = UserAvatarLib::getByUser($eUser);
		$eUserAvatar['user'] = new User(['id' => $eUser['id'], 'vignette' => $this->getVignette($eUser)]);
		$eUserAvatar['replacesPhoto'] = $replacesPhoto;

		return $eUserAvatar;

	}

		private function getPhoto(User $eUser): ?string {

		return UserAvatar::model()
			->select('photo')
			->whereUser($eUser)
			->getValue('photo');

	}

}
?>
