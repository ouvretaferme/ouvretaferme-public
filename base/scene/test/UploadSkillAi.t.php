<?php
namespace scene;

/**
 * Les skills Claude de téléversement, celui de l'avatar (`upload/avatar/`, téléchargé par `/user/avatarUpload:skill`)
 * et celui du décor (`upload/decor/`) : leurs modèles se régénèrent à l'identique depuis l'avatar livré et le cadre des
 * bâtiments, l'archive porte les fichiers du package octet pour octet, le serveur juge chaque exemple comme
 * `attendu.json` dit que le validateur du skill le juge, et les scripts de l'archive tournent dans sa disposition, hors
 * du dépôt.
 *
 * Aucune écriture en base ni dans le dépôt : la régénération se fait en mémoire, l'archive s'extrait dans un dossier
 * temporaire retiré à la fin.
 *
 * Lancement : dev/test package=scene file=UploadSkillAi
 */
class UploadSkillAiTest extends \Test {

	const string UPLOAD = LIME_DIRECTORY.'/base/scene/upload/';

	public function testSamples(): void {

		$process = proc_open(['node', self::UPLOAD.'samples.mjs', '--verifier'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, LIME_DIRECTORY, ['PATH' => getenv('PATH')]);

		if($this->assertTrue(is_resource($process), "node ne se lance pas ici : ce test régénère les modèles du skill sous node, l'installer sur la machine qui lance les tests") === FALSE) {
			return;
		}

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);
		$code = proc_close($process);

		$this->assertEquals(0, $code, "les modèles du skill ne sont plus ceux que l'avatar livré et les règles produisent, ils se refont par `node base/scene/upload/samples.mjs` : ".$output.$errors);

	}

	public function testArchive(): void {

		$entries = $this->readArchive('avatar');

		$expected = [
			'avatar-ouvretaferme/SKILL.md' => 'avatar/SKILL.md',
			'avatar-ouvretaferme/avatar.json' => 'avatar.json',
			'avatar-ouvretaferme/composer.mjs' => 'avatar/composer.mjs',
			'avatar-ouvretaferme/corps.glb' => 'avatar/corps.glb',
			'avatar-ouvretaferme/exemples/attendu.json' => 'avatar/exemples/attendu.json',
			'avatar-ouvretaferme/exemples/conforme.glb' => 'avatar/exemples/conforme.glb',
			'avatar-ouvretaferme/exemples/refus-armature.glb' => 'avatar/exemples/refus-armature.glb',
			'avatar-ouvretaferme/exemples/refus-os.glb' => 'avatar/exemples/refus-os.glb',
			'avatar-ouvretaferme/exemples/refus-sol.glb' => 'avatar/exemples/refus-sol.glb',
			'avatar-ouvretaferme/exporter.py' => 'exporter.py',
			'avatar-ouvretaferme/glb.mjs' => 'glb.mjs',
			'avatar-ouvretaferme/lib/avatar.mjs' => '../static/js/avatar.js',
			'avatar-ouvretaferme/lib/catalog.json' => '../static/avatar/catalog.json',
			'avatar-ouvretaferme/lib/model.mjs' => '../static/js/model.js',
			'avatar-ouvretaferme/lib/three.LICENSE' => '../static/lib/three-0.170.0.LICENSE',
			'avatar-ouvretaferme/lib/three.mjs' => '../static/lib/three-0.170.0.module.min.js',
			'avatar-ouvretaferme/poser.py' => 'avatar/poser.py',
			'avatar-ouvretaferme/reference.md' => 'avatar/reference.md',
			'avatar-ouvretaferme/rendre.mjs' => 'avatar/rendre.mjs',
			'avatar-ouvretaferme/rendu.mjs' => 'rendu.mjs',
			'avatar-ouvretaferme/resolve.mjs' => 'resolve.mjs',
			'avatar-ouvretaferme/upload.mjs' => 'upload.mjs',
		];

		$this->assertEquals(array_keys($expected), array_keys($entries), "l'archive du skill ne porte pas exactement ses fichiers, sous le dossier qui le nomme");

		foreach($expected as $path => $source) {
			if(isset($entries[$path]) and $path !== 'avatar-ouvretaferme/lib/catalog.json') {
				$this->assertTrue($entries[$path] === file_get_contents(self::UPLOAD.$source), $path." diffère de base/scene/upload/".$source." dans l'archive");
			}
		}

		$catalog = json_decode(file_get_contents(self::UPLOAD.'../static/avatar/catalog.json'), TRUE, flags: JSON_THROW_ON_ERROR);
		$catalog['labels'] = json_decode(file_get_contents(self::UPLOAD.'avatar.labels.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		$this->assertTrue(json_decode($entries['avatar-ouvretaferme/lib/catalog.json'] ?? 'null', TRUE) === $catalog, "le catalogue de l'archive n'est pas celui de l'éditeur augmenté des libellés de upload/avatar.labels.json");

	}

	public function testArchiveDecor(): void {

		$entries = $this->readArchive('decor');

		$expected = [
			'decor-ouvretaferme/SKILL.md' => 'decor/SKILL.md',
			'decor-ouvretaferme/composer.mjs' => 'decor/composer.mjs',
			'decor-ouvretaferme/decor.json' => 'decor.json',
			'decor-ouvretaferme/exemples/attendu.json' => 'decor/exemples/attendu.json',
			'decor-ouvretaferme/exemples/conforme-eau.glb' => 'decor/exemples/conforme-eau.glb',
			'decor-ouvretaferme/exemples/conforme-materiel.glb' => 'decor/exemples/conforme-materiel.glb',
			'decor-ouvretaferme/exemples/conforme-nature.glb' => 'decor/exemples/conforme-nature.glb',
			'decor-ouvretaferme/exemples/conforme.glb' => 'decor/exemples/conforme.glb',
			'decor-ouvretaferme/exemples/fiche.json' => 'decor/exemples/fiche.json',
			'decor-ouvretaferme/exemples/refus-piece.glb' => 'decor/exemples/refus-piece.glb',
			'decor-ouvretaferme/exemples/refus-sol.glb' => 'decor/exemples/refus-sol.glb',
			'decor-ouvretaferme/exemples/refus-texture.glb' => 'decor/exemples/refus-texture.glb',
			'decor-ouvretaferme/exporter.py' => 'exporter.py',
			'decor-ouvretaferme/glb.mjs' => 'glb.mjs',
			'decor-ouvretaferme/lib/BufferGeometryUtils.mjs' => '../static/lib/three-0.170.0.BufferGeometryUtils.js',
			'decor-ouvretaferme/lib/building.mjs' => '../static/js/building.js',
			'decor-ouvretaferme/lib/choices.json' => '../static/building/choices.json',
			'decor-ouvretaferme/lib/geometry.mjs' => '../static/js/geometry.js',
			'decor-ouvretaferme/lib/model.mjs' => '../static/js/model.js',
			'decor-ouvretaferme/lib/three.LICENSE' => '../static/lib/three-0.170.0.LICENSE',
			'decor-ouvretaferme/lib/three.mjs' => '../static/lib/three-0.170.0.module.min.js',
			'decor-ouvretaferme/reference.md' => 'decor/reference.md',
			'decor-ouvretaferme/rendre.mjs' => 'decor/rendre.mjs',
			'decor-ouvretaferme/rendu.mjs' => 'rendu.mjs',
			'decor-ouvretaferme/resolve.mjs' => 'resolve.mjs',
			'decor-ouvretaferme/upload.mjs' => 'upload.mjs',
		];

		$this->assertEquals(array_keys($expected), array_keys($entries), "l'archive du skill du décor ne porte pas exactement ses fichiers, sous le dossier qui le nomme");

		foreach($expected as $path => $source) {
			if(isset($entries[$path])) {
				$this->assertTrue($entries[$path] === file_get_contents(self::UPLOAD.$source), $path." diffère de base/scene/upload/".$source." dans l'archive");
			}
		}

	}

	// Une entrée du catalogue sans libellé se choisit à l'aveugle, sur sa seule clé
	public function testLabels(): void {

		$catalog = json_decode(file_get_contents(self::UPLOAD.'../static/avatar/catalog.json'), TRUE, flags: JSON_THROW_ON_ERROR);
		$labels = json_decode(file_get_contents(self::UPLOAD.'avatar.labels.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		$entries = array_merge(...array_column($catalog['families'], 'entries'));

		$this->assertEquals([], array_values(array_diff($entries, array_keys($labels))), "entrées du catalogue sans libellé dans base/scene/upload/avatar.labels.json, à décrire");
		$this->assertEquals([], array_values(array_diff(array_keys($labels), $entries)), "libellés de base/scene/upload/avatar.labels.json que le catalogue ne porte plus, à retirer");

	}

	/**
	 * Ce que fait l'utilisateur qui n'a pas Blender, dans le dossier téléchargé : composer un avatar du catalogue, le juger,
	 * puis le rendre. La fiche est la composition par défaut de l'éditeur, coiffée de la première entrée de `couvre-chef`.
	 */
	public function testRun(): void {

		$directory = sys_get_temp_dir().'/skill-'.bin2hex(random_bytes(6));
		$file = $directory.'.zip';

		mkdir($directory);
		file_put_contents($file, UploadSkillLib::getArchive('avatar'));

		try {

			new \PharData($file)->extractTo($directory);

			$skill = $directory.'/avatar-ouvretaferme/';
			$catalog = json_decode(file_get_contents($skill.'lib/catalog.json'), TRUE, flags: JSON_THROW_ON_ERROR);

			$elements = [];

			foreach($catalog['families'] as $family) {

				$entry = ($family['key'] === 'couvre-chef') ? $family['entries'][0] : $catalog['defaults']['entries'][$family['key']];

				if($entry !== NULL) {
					$elements[] = ['catalogue' => $entry, 'reglages' => array_key_exists($family['key'], $catalog['defaults']['tints']) ? ['couleur' => $catalog['defaults']['tints'][$family['key']]] : []];
				}

			}

			file_put_contents($directory.'/fiche.json', json_encode(['corps' => ['peau' => $catalog['defaults']['skin']], 'elements' => $elements], JSON_THROW_ON_ERROR));

			[$code, $output, $errors] = $this->runNode($skill, ['composer.mjs', $directory.'/fiche.json', $directory.'/avatar.glb']);
			$this->assertEquals(0, $code, "composer.mjs n'écrit pas la composition par défaut coiffée : ".$output);
			$this->assertEquals('', $errors, "composer.mjs avertit sur la composition par défaut, que l'import rend telle quelle");

			$glb = file_get_contents($directory.'/avatar.glb');
			$meshes = count(json_decode(substr($glb, 20, unpack('V', $glb, 12)[1]), TRUE)['meshes']);

			$this->assertEquals($meshes, preg_match_all('/^[a-zA-Z]+\.[0-9]{3} #[0-9a-f]{6} [0-9]+ triangles \(.+\)$/m', $output), "composer.mjs ne liste pas une ligne par pièce écrite");

			// Un motif et un aspect de peau ne font que teindre des pièces déjà là : l'import les perd, composer le dit
			$fiche = json_decode(file_get_contents($directory.'/fiche.json'), TRUE, flags: JSON_THROW_ON_ERROR);
			$fiche['elements'][] = ['catalogue' => 'motif-carreaux', 'reglages' => ['couleur' => $catalog['palettes']['tint'][0]]];
			$fiche['elements'] = array_map(fn(array $element) => ($element['catalogue'] === $catalog['defaults']['entries']['aspect']) ? ['catalogue' => 'peau-rosee', 'reglages' => []] : $element, $fiche['elements']);
			file_put_contents($directory.'/fiche-perdue.json', json_encode($fiche, JSON_THROW_ON_ERROR));

			[$code, , $errors] = $this->runNode($skill, ['composer.mjs', $directory.'/fiche-perdue.json', $directory.'/perdue.glb']);
			$this->assertEquals(0, $code, "composer.mjs refuse une fiche que l'éditeur monte");
			$this->assertEquals(2, preg_match_all('/^Avertissement, (motif-carreaux|peau-rosee) /m', $errors), "composer.mjs n'avertit pas que l'import perd le motif et l'aspect de peau : ".$errors);

			[$code, $output] = $this->runNode($skill, ['upload.mjs', '--check', '--rules='.$skill.'avatar.json', '--in='.$directory.'/avatar.glb']);
			$this->assertTrue($code === 0 and array_key_exists('triangles', json_decode($output, TRUE) ?? []), "le validateur du skill refuse le modèle que composer.mjs écrit : ".$output);

			[$code, $output] = $this->runNode($skill, ['rendre.mjs', $directory.'/avatar.glb', $directory.'/rendus']);
			$this->assertEquals(0, $code, "rendre.mjs ne rend pas le modèle composé : ".$output);

			$rules = UploadLib::getRules('avatar');
			$images = glob($directory.'/rendus/*.png');

			$this->assertEquals(3 + count($rules['poses']), count($images), "rendre.mjs ne rend pas les trois vues au repos et une vue par pose");
			$this->assertEquals([], array_filter($images, fn(string $image) => file_get_contents($image, length: 8) !== "\x89PNG\r\n\x1a\n"), "rendre.mjs écrit des images qui ne sont pas des PNG");

			[$code, $output] = $this->runNode($skill, ['rendre.mjs', $directory.'/avatar.glb', $directory.'/repos', '--pose=repos']);
			$this->assertEquals(0, $code, "rendre.mjs refuse la pose repos que SKILL.md cite : ".$output);
			$this->assertEquals(['dos.png', 'face.png', 'trois-quarts.png'], array_map('basename', glob($directory.'/repos/*.png')), "rendre.mjs --pose=repos ne rend pas les trois vues du repos seules");

		} finally {
			exec('rm -rf '.escapeshellarg($directory));
			unlink($file);
		}

	}

	/**
	 * Ce que fait l'utilisateur qui n'a pas Blender, dans le dossier téléchargé du décor : composer la fiche d'exemple, puis
	 * une fiche du cadre, les juger, puis les rendre. Une fiche que l'éditeur refuserait est refusée avec ses écarts.
	 */
	public function testRunDecor(): void {

		$directory = sys_get_temp_dir().'/skill-'.bin2hex(random_bytes(6));
		$file = $directory.'.zip';

		mkdir($directory);
		file_put_contents($file, UploadSkillLib::getArchive('decor'));

		try {

			new \PharData($file)->extractTo($directory);

			$skill = $directory.'/decor-ouvretaferme/';

			[$code, $output, $errors] = $this->runNode($skill, ['composer.mjs', $skill.'exemples/fiche.json', $directory.'/batiment.glb']);
			$this->assertEquals(0, $code, "composer.mjs n'écrit pas la fiche d'exemple : ".$output);
			$this->assertEquals('', $errors, "composer.mjs écrit sur sa sortie d'erreur pour la fiche d'exemple : ".$errors);
			$this->assertTrue(file_get_contents($directory.'/batiment.glb') === file_get_contents($skill.'exemples/conforme.glb'), "composer.mjs ne rend pas, de la fiche d'exemple, le modèle conforme de l'archive");

			$glb = file_get_contents($directory.'/batiment.glb');
			$meshes = count(json_decode(substr($glb, 20, unpack('V', $glb, 12)[1]), TRUE)['meshes']);

			$this->assertEquals($meshes, preg_match_all('/^[a-zA-Z]+\.[0-9]{3} #[0-9a-f]{6} [0-9]+ triangles$/m', $output), "composer.mjs ne liste pas une ligne par pièce écrite");

			// Une fiche du cadre, celle de reference.md : une porte, une fenêtre et une descente sur un mur de pierre
			$frame = [
				'nom' => 'grange',
				'corps' => ['toit' => 'deux-pans', 'largeur' => 8, 'profondeur' => 16, 'egout' => 4, 'faitage' => 7, 'socle' => 0],
				'mur' => ['catalogue' => 'pierre'],
				'couverture' => ['catalogue' => 'tuile', 'reglages' => ['couleur' => 'tuile']],
				'ouvertures' => [
					['catalogue' => 'porte-pleine', 'facade' => 'avant', 'u' => 0, 'reglages' => ['largeur' => 2.4, 'hauteur' => 2.6]],
					['catalogue' => 'fenetre', 'facade' => 'droite', 'u' => -3],
				],
				'elements' => [['catalogue' => 'descente', 'reglages' => ['couleur' => 'gris']]],
			];
			file_put_contents($directory.'/cadre.json', json_encode($frame, JSON_THROW_ON_ERROR));

			[$code, $output] = $this->runNode($skill, ['composer.mjs', $directory.'/cadre.json', $directory.'/cadre.glb']);
			$this->assertEquals(0, $code, "composer.mjs refuse la fiche du cadre de reference.md : ".$output);

			[$code, $output] = $this->runNode($skill, ['upload.mjs', '--check', '--rules='.$skill.'decor.json', '--family=building', '--in='.$directory.'/cadre.glb']);
			$this->assertTrue($code === 0 and array_key_exists('triangles', json_decode($output, TRUE) ?? []), "le validateur du skill refuse le modèle que composer.mjs écrit d'une fiche du cadre : ".$output);

			// Une fiche de l'éditeur hors de ses choix : chaque écart nommé, rien d'écrit
			file_put_contents($directory.'/hors.json', json_encode(['type' => 'building', 'length' => 2, 'width' => 9, 'height' => 4, 'settings' => ['roof' => 'mansart']], JSON_THROW_ON_ERROR));

			[$code, , $errors] = $this->runNode($skill, ['composer.mjs', $directory.'/hors.json', $directory.'/hors.glb']);
			$this->assertEquals(1, $code, "composer.mjs accepte une fiche que l'éditeur refuse");
			$this->assertTrue(str_contains($errors, 'length vaut 2') and str_contains($errors, 'settings.roof vaut mansart'), "composer.mjs ne nomme pas chaque écart de la fiche refusée : ".$errors);
			$this->assertFalse(is_file($directory.'/hors.glb'), "composer.mjs écrit le modèle d'une fiche refusée");

			[$code, $output] = $this->runNode($skill, ['rendre.mjs', $directory.'/cadre.glb', $directory.'/rendus']);
			$this->assertEquals(0, $code, "rendre.mjs ne rend pas le modèle composé : ".$output);
			$this->assertEquals(['dessus.png', 'dos.png', 'face.png', 'trois-quarts.png'], array_map('basename', glob($directory.'/rendus/*.png')), "rendre.mjs ne rend pas les quatre vues");
			$this->assertEquals(0, preg_match_all('/\(hors palette\)$/m', $output), "rendre.mjs tient pour hors palette une couleur du cadre : ".$output);

			// Un élément de Nature se rend sous les règles de sa famille, chaque pièce sous son nom
			[$code, $output] = $this->runNode($skill, ['rendre.mjs', '--famille=tree', $skill.'exemples/conforme-nature.glb', $directory.'/nature']);
			$this->assertEquals(0, $code, "rendre.mjs ne rend pas l'élément de Nature d'exemple : ".$output);
			$this->assertTrue(str_contains($output, 'arbre #') and str_contains($output, 'feuillage #'), "rendre.mjs ne nomme pas les pièces d'un élément de Nature : ".$output);
			$this->assertEquals(4, count(glob($directory.'/nature/*.png')), "rendre.mjs ne rend pas les quatre vues d'un élément de Nature");

		} finally {
			exec('rm -rf '.escapeshellarg($directory));
			unlink($file);
		}

	}

	// Le nom du profil devient un chemin du disque : un nom qui ne désigne pas des règles du package ne sert rien
	public function testProfile(): void {

		try {
			UploadSkillLib::getArchive('../avatar');
			$refused = FALSE;
		} catch(\Exception) {
			$refused = TRUE;
		}

		$this->assertTrue($refused, "un profil qui sort du répertoire des règles bâtit une archive");

	}

	public function testVerdicts(): void {

		$expected = json_decode(file_get_contents(self::UPLOAD.'avatar/exemples/attendu.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		$this->assertEquals(['conforme.glb', 'refus-os.glb', 'refus-armature.glb', 'refus-sol.glb'], array_keys($expected));

		foreach($expected as $sample => $verdict) {

			$result = UploadLib::run('avatar', self::UPLOAD.'avatar/exemples/'.$sample);

			if(isset($verdict['refusal'])) {
				$this->assertEquals($verdict, ['refusal' => $result['refusal'] ?? NULL, 'values' => $result['values'] ?? NULL], $sample." n'est pas jugé par le serveur comme le validateur du skill le juge");
			} else {
				$this->assertEquals($verdict['triangles'], $result['triangles'] ?? NULL, $sample." n'est pas converti par le serveur avec les triangles que le validateur du skill compte");
			}

		}

		$this->assertEquals(NULL, UploadLib::run('avatar', self::UPLOAD.'avatar/corps.glb')['refusal'] ?? NULL, "le corps nu d'où part l'utilisateur est refusé par le serveur");

	}

	public function testVerdictsDecor(): void {

		$expected = json_decode(file_get_contents(self::UPLOAD.'decor/exemples/attendu.json'), TRUE, flags: JSON_THROW_ON_ERROR);

		$this->assertEquals(['conforme.glb', 'refus-piece.glb', 'refus-texture.glb', 'refus-sol.glb', 'conforme-eau.glb', 'conforme-nature.glb', 'conforme-materiel.glb'], array_keys($expected));

		// Un élément d'une rangée se juge sous sa famille, un bâtiment sous la sienne
		$families = ['conforme-eau.glb' => 'water', 'conforme-nature.glb' => 'tree', 'conforme-materiel.glb' => 'other'];

		foreach($expected as $sample => $verdict) {

			$result = UploadLib::run('decor', self::UPLOAD.'decor/exemples/'.$sample, $families[$sample] ?? 'building');

			if(isset($verdict['refusal'])) {
				$this->assertEquals($verdict, ['refusal' => $result['refusal'] ?? NULL, 'values' => $result['values'] ?? NULL], $sample." n'est pas jugé par le serveur comme le validateur du skill le juge");
			} else {
				$this->assertEquals($verdict['triangles'], $result['triangles'] ?? NULL, $sample." n'est pas converti par le serveur avec les triangles que le validateur du skill compte");
			}

		}

	}

	// L'archive d'un profil, ses entrées rangées par chemin. Deux téléchargements rendent la même
	private function readArchive(string $profile): array {

		$archive = UploadSkillLib::getArchive($profile);

		$this->assertTrue($archive === UploadSkillLib::getArchive($profile), "deux téléchargements du skill ".$profile." ne rendent pas la même archive");

		$file = tempnam(sys_get_temp_dir(), 'skill').'.zip';
		file_put_contents($file, $archive);

		$entries = [];

		try {

			foreach(new \RecursiveIteratorIterator(new \PharData($file)) as $entry) {
				$entries[substr($entry->getPathname(), strlen('phar://'.$file.'/'))] = file_get_contents($entry->getPathname());
			}

		} finally {
			unlink($file);
			unlink(substr($file, 0, -4));
		}

		ksort($entries);

		return $entries;

	}

	// Un script du skill lancé par node depuis son dossier, comme l'utilisateur le lance : rend le code de sortie et ce qu'il
	// a écrit sur ses deux sorties, puis sur sa sortie d'erreur seule
	private function runNode(string $directory, array $arguments): array {

		$process = proc_open(['node', ...$arguments], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $directory, ['PATH' => getenv('PATH')]);

		$output = stream_get_contents($pipes[1]);
		$errors = stream_get_contents($pipes[2]);

		fclose($pipes[1]);
		fclose($pipes[2]);

		return [proc_close($process), $output.$errors, $errors];

	}

}
?>
