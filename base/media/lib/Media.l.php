<?php
namespace media;

abstract class MediaLib {

	use \Notifiable;

	protected $settings = [];
	protected $field = NULL;

	protected $type = NULL;
	protected $regenerate = FALSE;

	protected $output;

	public function __construct() {

		// Une classe dont le nom ne donne pas le type le porte elle-même
		$this->type ??= preg_replace_callback('/([A-Z])/', function($data) { return '-'.strtolower($data[1]); }, lcfirst(substr(get_class($this), 6, -3)));

		$this->settings = MediaSetting::$types[$this->type];
		$this->field = $this->settings['field'];

	}

	/**
	 * Get an MediaLib instance for the given structure
	 */
	public static function getInstance(string $type): ?MediaLib {

		$class = MediaSetting::getClass($type);

		if($class) {
			return new ('\\'.$class.'Lib')();
		} else {
			return NULL;
		}

	}

	/**
	 * Les types de la boutique, de l'AMAP, de la vente et de l'association, que leurs packages déclarent.
	 */
	public static function getObserverTypes(): array {
		return array_merge(...self::notify('getTypes'));
	}

	/**
	 * Les contenus d'éditeur que les packages de la vente écrivent dans leurs tables, pour que le
	 * nettoyage garde les images qu'ils citent.
	 */
	public static function getObserverContents(): array {

		if(\structure\StructureSetting::hasCommercialisationApp() === FALSE) {
			return [];
		}

		return array_merge([], ...self::notify('getEditorContents'));

	}

	/**
	 * Get module for this type of image
	 *
	 * @return \Module
	 */
	public function getModel(): ?\ModuleModel {

		$element = $this->settings['element'];

		if($element) {
			return $element::model();
		} else {
			return NULL;
		}

	}

	/**
	 * Get field for this type of image
	 */
	public function getField() {
		return $this->field;
	}

	/**
	 * Build elements from input
	 */
	public function buildElement(): \Element {
		return new \AnonymousElement();
	}

	/**
	 * Puts a media on the server after cropping it if necessary
	 *
	 */
	public function putBasename(\Element $eElement, &$hash, string $basename, array $bounds, ?int $fileType = NULL): bool {

		\storage\ServerLib::validateBasename($basename);

		try {
			$resource = new \Imagick($basename);
		} catch (\ImagickException $e) {
			return FALSE;
		}

		return $this->putImagick($eElement, $hash, $resource, $bounds, $fileType);

	}

	/**
	 * Puts a media from a file
	 *
	 */
	public function putFile(\Element $eElement, &$hash, string $file, array $bounds, ?int $fileType = NULL): bool {

		// Un SVG n'est pas stockable tel quel : il est rendu en PNG, qui prend sa place
		// pour la suite du pipeline
		$fileRasterized = NULL;
		$typePreferred = NULL;

		if(SvgLib::isSvg($file)) {

			$fileRasterized = SvgLib::rasterize($file, $this->settings['imageMaxLength'] ?? 1024);

			if($fileRasterized === NULL) {
				return FALSE;
			}

			$file = $fileRasterized;

			// Le rendu d'un vecteur n'a pas de format d'origine à préserver : on vise le
			// JPEG, bien plus léger. Le PNG ne sert que si le type refuse le JPEG.
			$typePreferred = IMAGETYPE_JPEG;

		} else if(in_array(exif_imagetype($file), MediaSetting::IMAGES_INPUT_TYPE, TRUE) === FALSE) {
			// Imagick devine le format sur le contenu et décode bien plus que des images, dont un SVG
			// que isSvg() n'a pas reconnu et dont il suivrait les ressources externes
			return FALSE;
		}

		try {
			$resource = new \Imagick($file);
		} catch (\ImagickException $e) {
			return FALSE;
		} finally {
			// Imagick a déjà décodé le fichier : le temporaire ne sert plus
			if($fileRasterized !== NULL) {
				unlink($fileRasterized);
			}
		}

		return $this->putImagick($eElement, $hash, $resource, $bounds, $fileType, $typePreferred);

	}

	/**
	 * Puts a media on the server after cropping it if necessary
	 *
	 */
	public function putImagick(\Element $eElement, &$hash, \Imagick $resource, array $bounds, ?int $fileType = NULL, ?int $typePreferred = NULL): bool {

		if($this->regenerate) {
			$hash = NULL;
			$this->regenerate = FALSE;
		}

		// L'extension vient de la lettre du hash, l'encodage vient de putImage() : les deux
		// doivent sortir du même arbitrage, sinon le contenu et l'extension divergent
		$fileType = \storage\ServerLib::getOutputType($this->type, $resource, $typePreferred) ?? $fileType;

		$this->addMedia($eElement, $hash, $fileType);

		$toBasename = $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash);

		if($bounds) {

			if(count($bounds) !== 4) {
				throw new \NotExpectedAction('Bounds parameter must be an array of 4 elements');
			}

			$toType = $this->type;

			// We extract a portion
			$apply = function(\Imagick $resource, &$result) use($bounds, $toType) {

				if(\storage\ImageLib::extractImagePortion($bounds, $resource, $toType)) {
					$result['crop'] = $bounds;
				}

			};

			$result = \storage\ServerLib::putImage($this->type, $toBasename, $resource, $apply, $typePreferred);

		} else {

			$result = \storage\ServerLib::putImage($this->type, $toBasename, $resource, typePreferred: $typePreferred);

		}

		if($result) {

			$this->output = $result;
			$this->saveElement($eElement);

			return TRUE;

		} else {
			return FALSE;
		}

	}

	public function crop(\Element $eElement, string $basename, array $bounds): void {

		\storage\ServerLib::validateBasename($basename);

		if(count($bounds) !== 4) {
			throw new \NotExpectedAction('Bounds parameter must be an array of 4 elements');
		}

		try {
			array_expects($bounds, ['top', 'left', 'width', 'height']);
		} catch(Exception $e) {
			throw new \NotExistsAction('Missing top, left, width, height properties in bounds parameter');
		}

		if($this->regenerate) {

			$hash = NULL;
			$this->putBasename($eElement, $hash, $basename, $bounds);

			return;

		}

		$bounds['top'] = (float)$bounds['top'];
		$bounds['left'] = (float)$bounds['left'];
		$bounds['width'] = (float)$bounds['width'];
		$bounds['height'] = (float)$bounds['height'];

		// We extract a portion
		$type = $this->type;
		$apply = function(\Imagick $resource, &$metadata) use($bounds, $type) {

			if(\storage\ImageLib::extractImagePortion($bounds, $resource, $type)) {

				$metadata['crop'] = $bounds;

			}

		};

		\storage\ServerLib::reput($type, $basename, $apply);

		if($eElement->notEmpty()) {

			$this->incVersion($eElement);

			$this->saveElement($eElement);
		}

	}

	public function copy(\Element $eElement, &$hash, string $toType, string $basenameFrom): void {

		\storage\ServerLib::validateBasename($basenameFrom);

		// Le média d'arrivée reprend le format de la source, sans quoi ServerLib::copy()
		// recopierait les octets sous une extension qui ne leur correspond pas ;
		// addMedia() le ramène aux formats autorisés du type d'arrivée s'il n'y figure pas
		$this->addMedia($eElement, $hash, self::getFileType(\storage\ServerLib::parseBasename($basenameFrom)['hash']));

		$basenameTo = MediaUi::getInstance($toType)->getBasenameByHash($hash);

		\storage\ServerLib::copy($basenameTo, $basenameFrom);

		if($eElement->notEmpty()) {
			$this->output = $this->getMetadata($hash);
			$this->saveElement($eElement);
		}

	}

	/**
	 * Rotates a media
	 *
	 *
	 */
	public function rotate(\Element $eElement, &$hash, int $angle): bool {

		if($hash === NULL) {
			throw new \NotExpectedAction('Cannot rotate a missing media');
		}

		// Une rotation ne change pas le format : sans lui passer celui du média existant,
		// addMedia() réattribue first(imageOutputType) et le basename qui suit désigne un
		// fichier qui n'existe pas
		$this->addMedia($eElement, $hash, self::getFileType($hash));

		$basename = $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash);
		$result = \storage\ServerLib::rotate($this->type, $basename, $angle);

		if($result) {

			$this->output = $result;
			$this->saveElement($eElement);

			return TRUE;

		} else {
			return FALSE;
		}

	}

	/**
	 * Send a media
	 *
	 */
	public function send(\Element $eElement, &$hash, string $binary, int|string|null $fileType = NULL): bool {

		$this->addMedia($eElement, $hash, $fileType);

		$path = $this->type.'/'.$hash.'.'.MediaUi::getExtension($hash);

		if(MediaUi::isImage($hash) === FALSE) {

			MediaSetting::$mediaDriver->sendBinary($binary, $path);

		} else {

			$binaryFile = tmpfile();
			fwrite($binaryFile, $binary);
			fclose($binaryFile);

			$resource = new \Imagick($binaryFile);

			MediaSetting::$mediaDriver->sendResource($resource, $path);

			$this->output = \storage\ServerLib::extractMetadata($resource);

		}

		$this->saveElement($eElement);

		return TRUE;

	}

	protected function saveElement(\Element $eElement): void {

		if($eElement->notEmpty()) {

			$eElement->expects([$this->field]);

			$color = $this->getColor();

			if($color !== NULL) {
				$eElement[$this->field] = substr($eElement[$this->field], 0, 20).$this->getColor().substr($eElement[$this->field], -3);
			}

			$eElement->model()
				->select($this->field)
				->update($eElement);

		}

	}

	/**
	 * Returns main color of last image
	 */
	public function getColor() {
		return $this->output['color'] ?? NULL;
	}

	/**
	 * Returns crop info about the last image
	 */
	public function getCrop() {
		return $this->output['crop'] ?? NULL;
	}

	/**

	 * Returns width of the last image
	 */
	public function getWidth() {
		return $this->output['width'] ?? NULL;
	}

	/**
	 * Returns height of the last image
	 */
	public function getHeight() {
		return $this->output['height'] ?? NULL;
	}

	/**
	 * Returns date of the last image
	 */
	public function getTakenAt() {
		return $this->output['takenAt'] ?? NULL;
	}

	/**
	 * Increment version
	 */
	public function incVersion(\Element $eElement): void {

		$eElement[$this->field] = substr($eElement[$this->field], 0, 27).sprintf('%03d', ((int)substr($eElement[$this->field], -3) + 1) % 1000);

	}

	/**
	 * Get metadata for the given hash
	 *
	 * @param string $hash
	 * @return string
	 */
	public function getMetadata(string $hash): ?array {

		$result = MediaSetting::$mediaDriver->getMetadata($this->type.'/'.$hash.'.'.MediaUi::getExtension($hash));

		if($result) {
			return $result;
		} else {
			return NULL;
		}

	}

	/**
	 * Type de fichier d'un média existant, tel que la lettre de son hash le code
	 *
	 * Inverse de la lettre attribuée par addMedia()
	 */
	protected static function getFileType(string $hash): int|string {

		return match($hash[19]) {
			'j' => IMAGETYPE_JPEG,
			'p' => IMAGETYPE_PNG,
			'g' => IMAGETYPE_GIF,
			'f' => 'pdf',
			'b' => 'bin'
		};

	}

	protected function addMedia(\Element $eElement, ?string &$hash, int|string|null $fileType = NULL) {

		if($eElement->notEmpty()) {
			$eElement->expects(['id', $this->field]);
		}

		// Check the fileType if authorized or not
		if(isset($this->settings['imageFormat'])) {

			$types = (array)$this->settings['imageOutputType'];

			if(
				$fileType === NULL or
				in_array($fileType, $types) === FALSE
			) {
				$fileType = first($types);
			}

		}

		if($fileType === IMAGETYPE_JPEG) {
			$letter = 'j';
		} elseif($fileType === IMAGETYPE_PNG) {
			$letter = 'p';
		} elseif($fileType === IMAGETYPE_GIF) {
			$letter = 'g';
		} elseif($fileType === 'pdf') {
			$letter = 'f';
		} elseif($fileType === 'bin') {
			$letter = 'b';
		} else {
			throw new \Exception('Bad file type ('.$fileType.')');
		}

		$eUser = \user\ConnectionLib::getOnline();

		// Match hash validity
		if($hash === NULL) {

			$hashGenerated = \media\UtilLib::generateHash().$letter;

		} else {

			// Check letter
			if($letter !== $hash[19]) { // Image format has changed (ie: image switched from jpeg to png)
				$hashGenerated = substr($hash, 0, 19).$letter;
			} else {
				$hashGenerated = NULL;
			}

		}

		// Hash needs to be generated
		if($hashGenerated !== NULL) {

			$hash = $hashGenerated;

			$eMedia = new \media\Media([
				'user' => $eUser,
				'type' => $this->type,
				'status' => \media\Media::ACTIVE,
				'updatedAt' => new \Sql('NOW()'),
				'hash' => $hash
			]);

			\media\Media::model()
				->option('add-replace')
				->insert($eMedia);

			if($eElement->notEmpty()) {

				if(MediaUi::isImage($hash)) {
					$color = $this->getColor() ?? '#000000';
					$eElement[$this->field] = $hash.$color.'001';
				} else {
					$eElement[$this->field] = $hash;
				}

			}

		} else {

			// Checks hash consistency
			if(
				$hash[19] !== 'p' and
				$hash[19] !== 'j' and
				$hash[19] !== 'g' and
				$hash[19] !== 'f' and
				$hash[19] !== 'b'
			) {
				throw new \Exception('Hash '.$hash.' must end with j (for jpeg files), p (for png files), g (for gif files), f (for pdf files) or b (for binary files)');
			}

			if($eElement->notEmpty()) {

				if(substr($eElement[$this->field], 0, 20) !== $hash) {
					throw new \Exception('Hash inconsistency in Media::addMedia()');
				}

			}

			$eMedia = new \media\Media([
				'user' => $eUser,
				'type' => $this->type,
				'status' => \media\Media::ACTIVE,
				'updatedAt' => new \Sql('NOW()'),
				'hash' => $hash
			]);

			\media\Media::model()
				->option('add-replace')
				->insert($eMedia);

			if($eElement->notEmpty()) {
				$this->incVersion($eElement);
			}

		}

	}

	/**
	 * Duplicates the media entry and the physical medias
	 */
	public function duplicate(\Element $eElement) {

		if($eElement[$this->field] === NULL) {
			return;
		}

		// Element étend ArrayObject : garder la valeur et non l'élément, que duplicateMedia()
		// modifie juste après
		$fieldFrom = $eElement[$this->field];

		$this->duplicateMedia($eElement);

		$uiMedia = MediaUi::getInstance($this->type);

		$basenameFrom = $uiMedia->getBasenameByHash($fieldFrom);
		$basenameTo = $uiMedia->getBasenameByHash($eElement[$this->field]);

		\storage\ServerLib::copy($basenameTo, $basenameFrom);

	}

	protected function duplicateMedia(\Element $eElement): void {

		// La lettre de format est en position 19, pas en fin de champ : celui d'une image
		// porte encore la couleur dominante et la version derrière le hash
		$hashFrom = substr($eElement[$this->field], 0, 20);

		$eUser = \user\ConnectionLib::getOnline();
		$hash = \media\UtilLib::generateHash().$hashFrom[19];

		$eMedia = new \media\Media([
			'user' => $eUser,
			'type' => $this->type,
			'status' => \media\Media::ACTIVE,
			'updatedAt' => new \Sql('NOW()'),
			'hash' => $hash
		]);

		\media\Media::model()->insert($eMedia);

		if($eElement->notEmpty()) {

			if(MediaUi::isImage($hash)) {
				// Même image que l'original : elle en garde la couleur dominante
				$eElement[$this->field] = $hash.substr($eElement[$this->field], 20, 7).'001';
			} else {
				$eElement[$this->field] = $hash;
			}

		}

	}

	/**
	 * Sets the \media\Media to DELETED
	 * Asks for the \media\Media to be DELETED too
	 * (will be deleted later)
	 */
	public function delete(\Element $eElement): void {

		if($eElement->empty()) {
			throw new \Exception('Element is missing');
		}

		$hash = $eElement[$this->field];

		// Media already deleted
		if($hash === NULL) {
			return;
		}

		$eElement[$this->field] = NULL;

		$this->getModel($eElement)
			->select($this->field)
			->update($eElement);

		// Le champ d'une image porte encore la couleur dominante et la version derrière le hash
		$this->deleteMedia(substr($hash, 0, 20));

	}

	protected function deleteMedia(string $hash): void {

		\media\Media::model()
			->whereHash('IN', $hash)
			->whereType($this->type)
			->update([
				'updatedAt' => new \Sql('NOW()'),
				'status' => \media\Media::DELETED
			]);

	}

	private static array $hashesPending = [];

	/**
	 * Pour un type dont le fichier ne doit pas rester servi par l'adresse publique du stockage jusqu'au
	 * passage de `CleanLib::delete()`, dix jours plus tard : le fichier quitte le disque à `deleteFiles()`.
	 */
	public function deleteByHash(string $hash): void {

		$this->deleteMedia($hash);

		self::$hashesPending[$this->type][$hash] = TRUE;

	}

	// Un fichier effacé avant le COMMIT manquerait à la ligne qu'un rollback remettrait en ACTIVE
	public static function deleteFiles(): void {

		if(Media::model()->inTransaction()) {
			return;
		}

		self::deleteFilesPending();

	}

	// Le statut se relit avant d'effacer : un rollback a pu remettre le média en ACTIVE
	public static function deleteFilesPending(): void {

		$hashesPending = self::$hashesPending;
		self::$hashesPending = [];

		foreach($hashesPending as $type => $hashes) {

			$cMedia = Media::model()
				->select('hash')
				->whereType($type)
				->whereHash('IN', array_keys($hashes))
				->whereStatus(Media::DELETED)
				->getCollection();

			foreach($cMedia as $eMedia) {
				MediaSetting::$mediaDriver->delete($type.'/'.$eMedia['hash'].'.'.MediaUi::getExtension($eMedia['hash']));
			}

		}

	}

	/**
	 * Set unused medias of the type at DELETED
	 *
	 */
	public function clean(): bool {

		if($this->field !== NULL) {

			$active = [];

			$cElement = $this->getModel()
				->select('id', $this->field)
				->where($this->field, '!=', NULL)
				->recordset()
				->getCollection();

			foreach($cElement as $eElement) {

				$active[] = substr($eElement[$this->field], 0, 20);

				if(count($active) > 100) {
					$this->activeMedias($active);
					$active = [];
				}

			}

			if($active) {
				$this->activeMedias($active);
			}

		} else {

			\media\Media::model()
				->whereType($this->type)
				->update([
					'status' => \media\Media::ACTIVE
				]);

		}

		return TRUE;

	}

	protected function activeMedias(array $hashes): void {

		$hashes = array_map(function($value) {
			return substr($value, 0, 20);
		}, $hashes);

		\media\Media::model()
			->whereHash('IN', $hashes)
			->whereType($this->type)
			->update([
				'status' => \media\Media::ACTIVE
			]);

	}

}
?>
