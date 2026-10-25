<?php
namespace user;

/**
 * L'avatar d'un utilisateur. Quand il remplace la photo de profil (`replacesPhoto`), son image est
 * `User.vignette`, que le produit affiche déjà partout, et la photo passe sur `UserAvatar.photo`, d'où elle
 * revient dès que l'avatar cesse de la remplacer ou disparait. Sinon `User.vignette` reste la photo, et
 * l'avatar ne sert qu'aux scènes.
 */
class UserAvatarLib extends UserAvatarCrud {

	/**
	 * La famille du catalogue qui s'enregistre à part, sur `UserAvatar.accessory`, et non dans la composition.
	 */
	const ACCESSORY = 'accessoire';

	/**
	 * Le catalogue fermé de l'éditeur est écrit par l'outil de livraison du skill 3d-perso, à côté du personnage qu'il
	 * décrit, et lu par l'éditeur comme par le serveur : une entrée retirée du skill quitte les deux à la fois. Il est le
	 * même pour tous les utilisateurs et toutes les structures, d'où une seule lecture par processus.
	 */
	private static ?array $catalog = NULL;

	public static function getCatalog(): array {

		self::$catalog ??= json_decode(file_get_contents(\Package::getPath('scene').'/static/avatar/catalog.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		return self::$catalog;

	}

	/**
	 * Les familles que porte une composition, indexées par leur clé, dans l'ordre du catalogue.
	 */
	public static function getFamilies(): array {

		$families = array_column(self::getCatalog()['families'], NULL, 'key');
		unset($families[self::ACCESSORY]);

		return $families;

	}

	/**
	 * La clé de la composition qui porte la teinte d'une famille : aucune pour une famille sans palette, le nom de la palette
	 * quand plusieurs familles la partagent (`sharedPalettes` du catalogue, le métal de tous les bijoux), sa propre
	 * `<famille>-teinte` sinon.
	 */
	public static function getTintKey(string $family): ?string {

		$palette = self::getFamilies()[$family]['palette'];

		if($palette === NULL) {
			return NULL;
		} else if(in_array($palette, self::getCatalog()['sharedPalettes'], TRUE)) {
			return $palette;
		} else {
			return $family.'-teinte';
		}

	}

	/**
	 * Les clés d'une composition : la peau, chaque famille et la teinte de chaque famille qui en a une, celle d'une palette
	 * partagée une seule fois.
	 */
	public static function getCompositionKeys(): array {

		$keys = ['peau'];

		foreach(array_keys(self::getFamilies()) as $family) {

			$keys[] = $family;

			$tintKey = self::getTintKey($family);

			if($tintKey !== NULL) {
				$keys[] = $tintKey;
			}

		}

		return array_values(array_unique($keys));

	}

	/**
	 * Chaque palette partagée, avec les familles qui la prennent, la dernière d'entre elles dans l'ordre du catalogue, après
	 * laquelle l'éditeur range ses teintes, et si la composition en porte au moins une : ses teintes n'ont d'effet que sur
	 * une pièce portée.
	 */
	public static function getSharedPalettes(array $composition): array {

		$shared = [];

		foreach(self::getCatalog()['sharedPalettes'] as $palette) {

			$families = array_keys(array_filter(self::getFamilies(), fn(array $family) => $family['palette'] === $palette));

			$shared[$palette] = [
				'families' => $families,
				'after' => $families[array_key_last($families)],
				'worn' => array_filter($families, fn(string $family) => $composition[$family] !== NULL) !== [],
			];

		}

		return $shared;

	}

	/**
	 * La teinte d'où part une zone : celle que le catalogue donne à son entrée sur cette peau, sinon celle de son entrée,
	 * sinon celle de sa famille, sinon la première de sa palette. Le catalogue range par cran de peau les teintes d'une
	 * entrée qui ne se lit pas sur toute la rampe (une monture, trait fin posé sur la peau), ne donne une teinte qu'aux
	 * entrées qui ont la leur, et aucune à une famille dont chaque entrée a la sienne.
	 * La peau est une teinte de palettes.skin : un cran ne se lit que sur la rampe, c'est à l'appelant d'y ramener la sienne.
	 * Une palette partagée part de sa première teinte : elle est celle de plusieurs familles à la fois, et le catalogue ne
	 * leur en donne aucune.
	 */
	public static function getDefaultTint(string $family, ?string $entry, string $skin): string {

		$catalog = self::getCatalog();
		$defaults = $catalog['defaults'];

		$palette = self::getFamilies()[$family]['palette'];

		if(in_array($palette, $catalog['sharedPalettes'], TRUE)) {
			return $catalog['palettes'][$palette][0];
		}

		$tintsSkin = NULL;
		$tintEntry = NULL;

		// Une zone vide n'a pas d'entrée, donc pas de teinte d'entrée : la chercher par NULL reviendrait à lire la clé ''
		if($entry !== NULL) {
			$tintsSkin = $defaults['skinEntryTints'][$entry] ?? NULL;
			$tintEntry = $defaults['entryTints'][$entry] ?? NULL;
		}

		if($tintsSkin !== NULL) {
			// La peau cherchée en clé et non par array_search() : absente de la rampe, celui-ci rend FALSE, qui se lit comme
			// le cran 0 et rend une teinte, quand une clé absente lève
			return $tintsSkin[array_flip($catalog['palettes']['skin'])[$skin]];
		}

		return $tintEntry ?? $defaults['tints'][$family] ?? $catalog['palettes'][$palette][0];

	}

	/**
	 * La composition d'où part un premier avatar : la peau, l'entrée et la teinte par défaut de chaque famille, celle d'une
	 * palette partagée posée une fois, par la première famille qui la prend.
	 */
	public static function getDefault(): array {

		$defaults = self::getCatalog()['defaults'];

		$composition = ['peau' => $defaults['skin']];

		foreach(array_keys(self::getFamilies()) as $family) {

			$composition[$family] = $defaults['entries'][$family];

			$tintKey = self::getTintKey($family);

			if($tintKey !== NULL) {
				$composition += [$tintKey => self::getDefaultTint($family, $composition[$family], $composition['peau'])];
			}

		}

		return $composition;

	}

	/**
	 * Une composition enregistrée s'ouvre dans l'éditeur du catalogue du jour : une peau que sa palette ne porte plus prend
	 * son défaut, la rampe n'ayant de cran que pour une teinte de la palette, et une zone entrée au catalogue depuis prend
	 * le sien, la teinte d'une famille qui n'en avait pas étant celle de l'entrée déjà portée, sur la peau de la composition.
	 * La chemise à carreaux a quitté l'éditeur pour la chemise unie, qui prend les motifs : un avatar qui la portait
	 * s'ouvre en chemise au motif carreaux, le haut gardant sa teinte et les carreaux prenant la leur par défaut, ou la
	 * première de leur palette qui diffère de celle du haut. Les pois ou les fleurs qu'elle portait restent :
	 * un seul motif se porte, et c'est celui que la personne a choisi. Une famille qui a quitté le catalogue quitte la
	 * composition avec sa teinte, comme la forme du visage, la cicatrice, les bijoux du nez, du sourcil et de la lèvre, et
	 * l'objet en main, gants compris : la composition ne porte que les clés que `composition.check` attend. Aucune reprise
	 * en base ne l'accompagne, l'enregistrement suivant écrivant la composition ouverte.
	 */
	public static function completeComposition(array $composition): array {

		$default = self::getDefault();

		$composition = array_intersect_key($composition, array_flip(self::getCompositionKeys()));

		$composition += ['peau' => $default['peau']];

		// La peau d'abord : la teinte d'une monture se lit à son cran
		if(in_array($composition['peau'], self::getCatalog()['palettes']['skin'], TRUE) === FALSE) {
			$composition['peau'] = $default['peau'];
		}

		foreach(array_keys(self::getFamilies()) as $family) {

			$composition += [$family => $default[$family]];

			$tintKey = self::getTintKey($family);

			if($tintKey !== NULL) {
				$composition += [$tintKey => self::getDefaultTint($family, $composition[$family], $composition['peau'])];
			}

		}

		if($composition['haut'] === 'chemise-carreaux') {

			$composition['haut'] = 'chemise';

			if($composition['motif'] === NULL) {

				$tintHaut = $composition['haut-teinte'];
				$tint = self::getDefaultTint('motif', 'motif-carreaux', $composition['peau']);

				// La conversion ne doit pas faire disparaître un motif que la personne voyait : des carreaux de la teinte de
				// la chemise s'y fondraient
				if($tint === $tintHaut) {
					$palette = self::getCatalog()['palettes'][self::getFamilies()['motif']['palette']];
					$tint = array_find($palette, fn(string $tintPalette) => $tintPalette !== $tintHaut);
				}

				$composition['motif'] = 'motif-carreaux';
				$composition['motif-teinte'] = $tint;

			}

		}

		return $composition;

	}

	/**
	 * Une composition est exclue quand elle porte toutes les entrées d'un même tuple, qu'il en compte deux, trois ou
	 * quatre. Les tuples sont ceux du catalogue (`excluded`).
	 */
	public static function isExcluded(array $composition, array $excluded): bool {

		$entries = array_values(array_intersect_key($composition, self::getFamilies()));

		foreach($excluded as $tuple) {

			if(array_diff($tuple, $entries) === []) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Une teinte est une couleur de la palette de sa famille, ou une couleur libre, écrite en hexadécimal minuscule
	 * comme celles des palettes : l'éditeur et le modèle du personnage n'en lisent pas d'autre forme. Une palette partagée
	 * n'a pas de couleur libre (`$free`) : le métal des bijoux est l'acier, l'or ou le noir.
	 */
	public static function isTint(mixed $tint, array $palette, bool $free = TRUE): bool {

		return (
			is_string($tint) and
			(in_array($tint, $palette, TRUE) or ($free and preg_match('/^#[0-9a-f]{6}$/D', $tint) === 1))
		);

	}

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['composition', 'replacesPhoto'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['composition', 'replacesPhoto'];
	}

	public static function getByUser(User $eUser): UserAvatar {

		return UserAvatar::model()
			->select(UserAvatar::getSelection())
			->whereUser($eUser)
			->get();

	}

	/**
	 * Les avatars de `$cUser`, par utilisateur, en une requête, avec de quoi monter leur fiche (`getFiche()`).
	 */
	public static function getByUsers(\Collection $cUser): \Collection {

		return UserAvatar::model()
			->select(['user', 'source', 'composition'])
			->whereUser('IN', $cUser)
			->getCollection(index: 'user');

	}

	/**
	 * La fiche du personnage du skill 3d-perso, celle que l'éditeur monte (`UserAvatarEditor.getFiche()`,
	 * `base/user/static/js/avatar.js`) : la peau, puis une entrée du catalogue par famille portée, avec sa teinte.
	 * L'avatar d'une photo enregistre la fiche que l'agent a rendue, dont ne se lisent que la peau et, par élément,
	 * l'entrée du catalogue et sa teinte : la fiche part chez tous ceux qui voient la personne, et une autre clé que
	 * l'agent y poserait (une note, la description de la photo) ne doit pas partir avec.
	 * Ce qu'elle tiendrait en main ou aux mains (`held` du catalogue) part aussi : l'avatar ne porte pas d'objet, quelle
	 * que soit sa source (Vincent, 2026-10-24), et la scène qui en veut un le pose elle-même.
	 *
	 * La composition passe d'abord par `completeComposition()`, comme à l'ouverture de l'éditeur : une composition
	 * enregistrée avant une famille du catalogue se monte avec le défaut de celle-ci.
	 *
	 * Un modèle téléversé (`upload`) n'y change rien : la fiche est celle de la composition, enregistrée à côté de lui.
	 * Elle reste le repli du modèle dans les scènes, quand le navigateur ne le lit pas ou que la scène en montre déjà
	 * autant qu'elle en porte.
	 */
	public static function getFiche(UserAvatar $e): array {

		$e->expects(['source', 'composition']);

		return match($e['source']) {
			UserAvatar::PHOTO => self::getFicheByPhoto($e['composition']),
			UserAvatar::EDITOR => self::getFicheByComposition(self::completeComposition($e['composition']))
		};

	}

	private static function getFicheByPhoto(array $fiche): array {

		$held = self::getCatalog()['held'];

		$elements = [];

		foreach($fiche['elements'] as $element) {

			if(in_array($element['catalogue'], $held, TRUE)) {
				continue;
			}

			$elements[] = [
				'catalogue' => $element['catalogue'],
				'reglages' => array_intersect_key($element['reglages'], ['couleur' => TRUE])
			];

		}

		return [
			'corps' => ['peau' => $fiche['corps']['peau']],
			'elements' => $elements
		];

	}

	private static function getFicheByComposition(array $composition): array {

		$elements = [];

		foreach(self::getFamilies() as $family => ['palette' => $palette]) {

			if($composition[$family] === NULL) {
				continue;
			}

			$elements[] = [
				'catalogue' => $composition[$family],
				'reglages' => ($palette === NULL) ? [] : ['couleur' => $composition[self::getTintKey($family)]]
			];

		}

		return [
			'corps' => ['peau' => $composition['peau']],
			'elements' => $elements
		];

	}

	/**
	 * Enregistre la composition et le choix de remplacer la photo, puis place l'image de la composition rendue par
	 * l'éditeur (`placeImage()`). Un modèle téléversé en place se retire, ses fichiers effacés après le COMMIT.
	 */
	public static function save(UserAvatar $e, string $image): void {

		$e->expects(['replacesPhoto', 'user' => ['id', 'vignette']]);

		$eUser = $e['user'];

		UserAvatar::model()->beginTransaction();

		$created = ($e->exists() === FALSE);

		if($created) {

			try {
				self::create($e);
			}
			// Un second enregistrement parti de la même page a créé l'avatar entre-temps : celui-ci le réenregistre
			// sur l'état que l'autre a posé
			catch(\DuplicateException) {

				$e['id'] = UserAvatar::model()
					->whereUser($eUser)
					->getValue('id');

				$created = FALSE;

			}

		}

		if($created) {
			$replacedBefore = FALSE;
		} else {

			// L'état enregistré se relit ici : `$e` porte déjà la case postée, et l'autre enregistrement a pu le changer
			$eUserAvatarSaved = self::getByUserForUpdate($eUser);

			$replacedBefore = $eUserAvatarSaved['replacesPhoto'];
			$e['photo'] = $eUserAvatarSaved['photo'];
			$eUser['vignette'] = $eUserAvatarSaved['user']['vignette'];

		}

		// Enregistrer une composition, c'est la choisir : un modèle téléversé en place se retire avec ses fichiers
		if($created === FALSE) {

			UserAvatarUploadLib::withdrawFiles($eUser);
			$e['upload'] = new UserAvatarUpload();

			self::update($e, ['composition', 'upload']);

		}

		self::placeImage($e, $replacedBefore, $image);

		UserAvatar::model()->commit();

		\media\MediaLib::deleteFiles();

	}

	/**
	 * Pose l'image de l'avatar sur `User.vignette`, ou y rend la photo, d'après `replacesPhoto` et l'état que l'appelant a
	 * relu sous verrou dans sa transaction (`$replacedBefore`, `photo`, `user.vignette`) :
	 * - un avatar qui remplace déjà la photo garde le média de son image, qui change de version ;
	 * - un avatar qui se met à la remplacer met la photo de côté et prend un média neuf, puisque `User.vignette`
	 *   porte encore la photo ;
	 * - un avatar qui cesse de la remplacer rend la photo à `User.vignette`, et son image part au nettoyage ;
	 * - un avatar qui ne la remplace pas n'a pas d'image à placer, et `$image` n'est pas lue.
	 *
	 * L'image est celle de ce que l'avatar montre, sa composition ou son modèle téléversé, rendue par l'éditeur ou par
	 * `capture()`. Une image qui ne se place pas annule la transaction de l'appelant et lève `UserAvatar::image`.
	 */
	public static function placeImage(UserAvatar $e, bool $replacedBefore, ?string $image): void {

		$e->expects(['replacesPhoto', 'photo', 'user' => ['id', 'vignette']]);

		$eUser = $e['user'];
		$replaced = $e['replacesPhoto'];
		$photo = $e['photo'];

		if($replacedBefore === FALSE and $replaced) {
			$e['photo'] = $eUser['vignette'];
		} else if($replacedBefore and $replaced === FALSE) {
			$e['photo'] = NULL;
		}

		self::update($e, ['replacesPhoto', 'photo']);

		if($replaced) {

			// L'appelant juge l'image due sur l'état lu en début de requête : relu sous verrou, il a pu changer
			if($image === NULL) {
				UserAvatar::model()->rollBack();
				throw new \FailAction('user\UserAvatar::image');
			}

			$hash = ($replacedBefore and $eUser['vignette'] !== NULL) ? substr($eUser['vignette'], 0, 20) : NULL;

			if(new \media\UserVignetteLib()->putFile($eUser, $hash, $image, []) === FALSE) {
				UserAvatar::model()->rollBack();
				throw new \FailAction('user\UserAvatar::image');
			}

		} else if($replacedBefore) {
			self::restorePhoto($eUser, $photo);
		}

	}

	/**
	 * Change le choix de remplacer la photo d'un avatar qui montre son modèle téléversé, avec l'image de ce modèle que
	 * l'éditeur a rendue. Rend FALSE sans rien écrire quand l'avatar ne montre plus de modèle au moment du verrou : l'image
	 * serait celle d'un modèle parti.
	 */
	public static function updatePhoto(UserAvatar $e, ?string $image): bool {

		$e->expects(['user', 'replacesPhoto']);

		UserAvatar::model()->beginTransaction();

			$eUserAvatarLocked = self::getByUserForUpdate($e['user']);

			if($eUserAvatarLocked->empty() or $eUserAvatarLocked['upload']->empty()) {
				// Rien n'est écrit : valider plutôt qu'annuler, comme `UserAvatarUploadLib::withdraw()`
				UserAvatar::model()->commit();
				return FALSE;
			}

			$replacedBefore = $eUserAvatarLocked['replacesPhoto'];
			$eUserAvatarLocked['replacesPhoto'] = $e['replacesPhoto'];

			self::placeImage($eUserAvatarLocked, $replacedBefore, $image);

		UserAvatar::model()->commit();

		\media\MediaLib::deleteFiles();

		return TRUE;

	}

	/**
	 * Après le retrait par l'équipe d'un modèle qui faisait la photo de profil, l'image de la composition revenue, rendue par
	 * `capture()` ; sans elle, le rendu ayant échoué, la photo reprend sa place. L'état se relit sous verrou : un avatar
	 * qui a importé un autre modèle, cessé de remplacer la photo ou changé d'image (`$vignette`, celle que le retrait a
	 * laissée) pendant le rendu a déjà l'image qu'il faut.
	 */
	public static function recompose(User $eUser, ?string $image, ?string $vignette): void {

		UserAvatar::model()->beginTransaction();

			$eUserAvatarLocked = self::getByUserForUpdate($eUser);

			if(
				$eUserAvatarLocked->empty() or
				$eUserAvatarLocked['upload']->notEmpty() or
				$eUserAvatarLocked['replacesPhoto'] === FALSE or
				$eUserAvatarLocked['user']['vignette'] !== $vignette
			) {
				UserAvatar::model()->commit();
				return;
			}

			$eUserAvatarLocked['replacesPhoto'] = ($image !== NULL);

			self::placeImage($eUserAvatarLocked, TRUE, $image);

		UserAvatar::model()->commit();

		\media\MediaLib::deleteFiles();

	}

	/**
	 * L'état de l'avatar de `$eUser` qui décide de son image, relu sous verrou dans la transaction de l'appelant : premier
	 * verrou de celle-ci, l'avatar avant les envois, dans l'ordre de `UserAvatarUploadLib::create()`. Vide sans avatar.
	 */
	public static function getByUserForUpdate(User $eUser): UserAvatar {

		return UserAvatar::model()
			->select([
				'id', 'upload', 'replacesPhoto', 'photo',
				'user' => ['vignette']
			])
			->whereUser($eUser)
			->option('for-update')
			->get();

	}

	/**
	 * Place l'image que l'éditeur a rendue de nouveau, à son ouverture, d'un avatar dont l'image est un JPEG
	 * (`UserAvatar::acceptRerender()`), sur le média de cette image. Ni la composition ni le choix de remplacer la
	 * photo ne changent.
	 *
	 * L'état se relit sous verrou : un enregistrement ou une suppression partis d'un autre onglet peuvent avoir rendu la
	 * photo, placé un PNG ou retiré l'avatar entre l'ouverture de l'éditeur et l'envoi, et l'image ne se place alors pas.
	 */
	public static function rerender(UserAvatar $e, string $image): void {

		$e->expects(['id']);

		UserAvatar::model()->beginTransaction();

		$eUserAvatar = UserAvatar::model()
			->select([
				'replacesPhoto', 'photo',
				'user' => ['vignette']
			])
			->whereId($e['id'])
			->option('for-update')
			->get();

		if($eUserAvatar->notEmpty() and $eUserAvatar->acceptRerender()) {

			$eUser = $eUserAvatar['user'];
			$hash = substr($eUser['vignette'], 0, 20);

			if(new \media\UserVignetteLib()->putFile($eUser, $hash, $image, []) === FALSE) {
				UserAvatar::model()->rollBack();
				throw new \FailAction('user\UserAvatar::image');
			}

		}

		UserAvatar::model()->commit();

	}

	/**
	 * Les avatars dont l'image se rend de nouveau (`UserAvatar::acceptRerender()`), parmi ceux des utilisateurs
	 * nommés, ou de tous quand aucun ne l'est. Seule une composition de l'éditeur se rend dans l'éditeur, une fiche
	 * rendue d'après une photo n'en étant pas une.
	 */
	public static function getForRerender(array $users = []): \Collection {

		return UserAvatar::model()
			->select([
				'id', 'replacesPhoto', 'photo',
				'user' => ['vignette']
			])
			->whereSource(UserAvatar::EDITOR)
			->whereReplacesPhoto(TRUE)
			->whereUser('IN', $users, if: $users !== [])
			->getCollection()
			->find(fn(UserAvatar $e) => $e->acceptRerender());

	}

	/**
	 * Ce qui manque au serveur pour rendre l'image d'un avatar (`capture()`), nommé pour qui l'installe. Vide quand
	 * tout y est. Rien ne s'installe ici.
	 */
	public static function checkCapture(): array {

		$missing = [];

		exec('node --version 2>&1', $outputVersion, $codeVersion);

		if($codeVersion !== 0) {
			return ['node, introuvable dans le PATH ('.implode(' ', array_slice($outputVersion, 0, 3)).')'];
		}

		if(is_executable('/usr/bin/google-chrome') === FALSE) {
			$missing[] = 'Google Chrome, attendu en /usr/bin/google-chrome';
		}

		foreach(['puppeteer-core', 'minimist'] as $module) {
			if(is_file(LIME_DIRECTORY.'/ouvretaferme/main/nodejs/node_modules/'.$module.'/package.json') === FALSE) {
				$missing[] = 'le module node '.$module.', attendu dans ouvretaferme/main/nodejs/node_modules';
			}
		}

		try {
			\Lime::signRemote('selling', '/user/avatar:render');
		} catch(\Exception) {
			$missing[] = 'la clé distante selling, qui signe l\'adresse de l\'éditeur';
		}

		if($missing !== []) {
			return $missing;
		}

		exec('node '.LIME_DIRECTORY.'/ouvretaferme/main/nodejs/image.js --check 2>&1', $outputCheck, $codeCheck);

		if($codeCheck !== 0) {
			$missing[] = 'un Chrome sans écran qui ouvre WebGL 2 ('.implode(' ', array_slice($outputCheck, 0, 3)).')';
		}

		return $missing;

	}

	/**
	 * L'image d'un avatar enregistré, rendue par son éditeur (`/user/avatar:render`) dans un Chrome sans écran, comme
	 * à l'enregistrement : le serveur ne rend pas la 3D. Elle est écrite dans un fichier temporaire, que l'appelant
	 * efface.
	 */
	public static function capture(UserAvatar $e): string {

		$e->expects(['id']);

		$file = tempnam(sys_get_temp_dir(), 'avatar');

		$args = escapeshellarg('--url='.\Lime::getUrl().\Lime::signRemote('selling', '/user/avatar:render?id='.$e['id']));
		$args .= ' '.escapeshellarg('--destination='.$file);

		exec('node '.LIME_DIRECTORY.'/ouvretaferme/main/nodejs/image.js '.$args.' 2>&1', $outputCapture, $codeCapture);

		if($codeCapture !== 0) {
			unlink($file);
			throw new \Exception('Avatar image not rendered, image.js exited with '.$codeCapture.': '.implode("\n", array_slice($outputCapture, 0, 5)));
		}

		return $file;

	}

	/**
	 * La photo de profil n'est mise de côté que par un avatar qui la remplace : l'image d'un avatar ne doit
	 * jamais prendre sa place.
	 */
	public static function create(UserAvatar $e): void {

		$e->expects(['user' => ['vignette'], 'composition', 'replacesPhoto']);

		$e['photo'] = $e['replacesPhoto'] ? $e['user']['vignette'] : NULL;

		parent::create($e);

	}

	public static function update(UserAvatar $e, array $properties): void {

		$e['updatedAt'] = new \Sql('NOW()');
		$properties[] = 'updatedAt';

		parent::update($e, $properties);

	}

	/**
	 * Un avatar qui remplace la photo la rend à `User.vignette` en partant, et le modèle téléversé qu'il montrait quitte
	 * le disque une fois la transaction validée.
	 */
	public static function delete(UserAvatar $e): void {

		$e->expects(['id', 'replacesPhoto', 'photo', 'user' => ['id', 'vignette']]);

		UserAvatar::model()->beginTransaction();

			// L'avatar se verrouille avant les envois, dans l'ordre de `UserAvatarUploadLib::create()` et de
			// `UserAvatarUploadLib::withdraw()`
			UserAvatar::model()
				->select('id')
				->whereId($e)
				->option('for-update')
				->get();

			UserAvatarUploadLib::withdrawFiles($e['user']);

			parent::delete($e);

			if($e['replacesPhoto']) {
				self::restorePhoto($e['user'], $e['photo']);
			}

		UserAvatar::model()->commit();

		\media\MediaLib::deleteFiles();

	}

	/**
	 * La photo revient sur `User.vignette`, et l'image de l'avatar qu'elle remplace part au nettoyage des médias.
	 */
	private static function restorePhoto(User $eUser, ?string $photo): void {

		$vignette = $eUser['vignette'];

		$eUser['vignette'] = $photo;
		UserLib::update($eUser, ['vignette']);

		if($vignette !== NULL) {
			new \media\UserVignetteLib()->deleteByHash(substr($vignette, 0, 20));
		}

	}

}
?>
