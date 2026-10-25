<?php
namespace storage;

/**
 * Image handling
 */
class ServerLib {

	/**
	 * Saves a temporary image
	 *
	 * @param string $basename
	 * @param string $file file contents (binary)
	 * @param string $type
	 */
	public static function saveTemporaryImage(string $basename, string $data, string $type) {


		file_put_contents('/tmp/'.$basename, $data);
		$isImage = ImageLib::isImage('/tmp/'.$basename);
		unlink('/tmp/'.$basename);

		if($isImage === FALSE) {
			return NULL;
		}

		$path = 'tmp/'.$basename;
		\media\MediaSetting::$mediaDriver->sendBinary($data, $path);

		return $path;

	}

	/**
	 * Reputs an existing file
	 *
	 * @param string $type
	 * @param string $basename
	 * @param callable|NULL $callback
	 * @return array
	 */
	public static function reput(string $type, string $basename, ?callable $callback = NULL): array {

		$resource = \media\MediaSetting::$mediaDriver->getFileResource($basename);
		$metadata = \media\MediaSetting::$mediaDriver->getMetadata($basename);

		// Save original file
		self::buildImage($type, NULL, $resource, $basename, self::getTypeFromResource($resource));

		return self::buildFormats($type, $basename, $metadata, $resource, $callback);

	}

	/**
	 * Rotates an existing file
	 */
	public static function rotate(string $type, string $basename, int $angle): array {

		$resource = \media\MediaSetting::$mediaDriver->getFileResource($basename);
		$typeSource = self::getTypeFromResource($resource);

		if($typeSource !== IMAGETYPE_JPEG and $typeSource !== IMAGETYPE_PNG) {
			throw new \NotExpectedAction('Can only rotate PNG and JPG files');
		}

		$resource->rotateImage('white', $angle);

		$metadata = \media\MediaSetting::$mediaDriver->getMetadata($basename);
		$metadata['crop'] = ['top' => 0, 'left' => 0, 'width' => 100, 'height' => 100];

		self::buildImage($type, NULL, $resource, $basename, $typeSource);

		return self::buildFormats($type, $basename, $metadata, $resource);

	}

	protected static function getTypeFromResource(\Imagick $resource): int {

		switch(strtolower($resource->identifyFormat('%m'))) {

			case 'jpg':
			case 'jpeg':
				return IMAGETYPE_JPEG;

			case 'png':
				return IMAGETYPE_PNG;

			case 'gif':
				return IMAGETYPE_GIF;

			case 'webp':
				return IMAGETYPE_WEBP;

		}

		return 0;

	}

	/**
	 * Returns the type an image will be stored as
	 *
	 * Sole authority on that choice: MediaLib::addMedia() derives the file extension from
	 * it while putImage() encodes with it. If the two ever disagree, the payload and the
	 * extension no longer match.
	 *
	 * @param string $type type of the image
	 * @param Imagick $resource
	 * @param int|NULL $typePreferred type wanted by the caller, honoured when the type allows it
	 * @return int|NULL NULL when the type stores raw binaries
	 */
	public static function getOutputType(string $type, \Imagick $resource, ?int $typePreferred = NULL): ?int {

		$typeDestination = StorageSetting::$types[$type]['imageOutputType'] ?? NULL;

		if(is_array($typeDestination) === FALSE) {
			return $typeDestination;
		}

		// A rasterized vector has no original format worth keeping: the caller asks for JPEG
		if(
			$typePreferred !== NULL and
			in_array($typePreferred, $typeDestination)
		) {
			return $typePreferred;
		}

		$typeSource = self::getTypeFromResource($resource);

		if(in_array($typeSource, $typeDestination)) {
			return $typeSource;
		}

		// A source that must be converted keeps its transparency if PNG is allowed,
		// otherwise first() would flatten a transparent logo onto white
		if(
			$resource->getImageAlphaChannel() and
			in_array(IMAGETYPE_PNG, $typeDestination)
		) {
			return IMAGETYPE_PNG;
		}

		return first($typeDestination);

	}

	/**
	 * Flattens a source that would otherwise lose its transparency to black
	 *
	 * Keyed on the alpha channel rather than on a list of source formats, which would need
	 * extending every time an input format is accepted
	 */
	private static function flattenForDestination(\Imagick $resource, ?int $typeDestination): void {

		if(
			$typeDestination === IMAGETYPE_JPEG and
			$resource->getImageAlphaChannel()
		) {
			ImageLib::flatten($resource);
		}

	}

	/**
	 * Puts a file into a file storage system
	 *
	 * @param string $type type of the image
	 * @param string $basename
	 * @param Imagick $resource
	 * @param callable $callback
	 */
	public static function putImage(string $type, string $basename, \Imagick $resource, ?callable $callback = NULL, ?int $typePreferred = NULL): array {

		$typeSource = self::getTypeFromResource($resource);

		// Même préférence qu'au moment où MediaLib::addMedia() a fixé l'extension, sans quoi
		// l'encodage et l'extension divergeraient
		$typeDestination = self::getOutputType($type, $resource, $typePreferred);

		self::flattenForDestination($resource, $typeDestination);

		if($typeSource === IMAGETYPE_JPEG) {
			ImageLib::rotateImage($resource);
		}

		if($typeDestination !== IMAGETYPE_GIF) {

			// We resize the original file
			$maxSize = StorageSetting::$types[$type]['imageMaxLength'];

			if($maxSize !== NULL) {

				$minPixels = StorageSetting::$types[$type]['imageMinPixels'] ?? NULL;
				ImageLib::resize($maxSize, $resource, StorageSetting::$types[$type]['imageFormatConstraint'] ?? NULL, $minPixels);

			}

		}

		// Extract metadata before dropping them from file
		$metadata = self::extractMetadata($resource);

		// Save original file
		self::buildImage($type, NULL, $resource, $basename, $typeDestination);

		return self::buildFormats($type, $basename, $metadata, $resource, $callback);

	}

	public static function extractMetadata(\Imagick $resource): array {

		\dev\ErrorPhpLib::$doNothingFromError = TRUE;

		if(empty($resource->identifyFormat('%[exif:*]'))) {
			$metadata = [
				'make' => NULL,
				'model' => NULL,
				'exposureTime' => NULL,
				'aperture' => NULL,
				'iso' => NULL,
				'focalLength' => NULL,
				'takenAt' => NULL,
				'crop' => NULL,
			];
		} else {
			$metadata = [
				'make' => $resource->identifyFormat('%[exif:Make]') ?? NULL,
				'model' => $resource->identifyFormat('%[exif:Model]') ?? NULL,
				'exposureTime' => $resource->identifyFormat('%[exif:ExposureTime]') ?? NULL,
				'aperture' => $resource->identifyFormat('%[exif:FNumber]') ?? NULL,
				'iso' => $resource->identifyFormat('%[exif:ISOSpeedRatings]') ?? NULL,
				'focalLength' => $resource->identifyFormat('%[exif:FocalLength]') ?? NULL,
				'takenAt' => $resource->identifyFormat('%[exif:DateTimeOriginal]') ? date('Y-m-d H:i:s', strtotime($resource->identifyFormat('%[exif:DateTimeOriginal]'))) : NULL,
				'crop' => NULL,
			];
		}


		\dev\ErrorPhpLib::$doNothingFromError = FALSE;

		return $metadata;

	}

	/**
	 * Physically copies the images from a basename to another basename
	 *
	 * if types are differents, evaluates the thumbnails
	 * if types are the same, copies without treatment
	 *
	 */
	public static function copy(string $basenameTo, string $basenameFrom) {

		$elementsTo = self::parseBasename($basenameTo);
		$elementsFrom = self::parseBasename($basenameFrom);

		if($elementsFrom['type'] === $elementsTo['type']) {

			// All formats (+ original)
			$formats = StorageSetting::$types[$elementsTo['type']]['imageFormat'];
			$formats[] = NULL;

			foreach($formats as $format) {

				$from = self::getPath($elementsFrom['type'], $format, $elementsFrom['hash'].'.'.$elementsFrom['extension']);
				$to = self::getPath($elementsTo['type'], $format, $elementsTo['hash'].'.'.$elementsTo['extension']);
				\media\MediaSetting::$mediaDriver->copy($from, $to);

			}

			self::copyMetadata($elementsTo['type'], $basenameFrom, $basenameTo);

		} else {

			$metadata = \media\MediaSetting::$mediaDriver->getMetadata($basenameFrom);
			$resource = \media\MediaSetting::$mediaDriver->getFileResource($basenameFrom);

			// The destination type has its own allowed formats: encoding with the source
			// type would not match the extension carried by the destination hash
			$typeDestination = self::getOutputType($elementsTo['type'], $resource) ?? self::getTypeFromResource($resource);

			self::flattenForDestination($resource, $typeDestination);

			self::buildImage($elementsTo['type'], NULL, $resource, $basenameTo, $typeDestination);
			self::buildFormats($elementsTo['type'], $basenameTo, $metadata, $resource);

		}


		return TRUE;
	}

	/**
	 * Create formats of a file
	 *
	 */
	public static function buildFormats(string $type, string $basename, array $metadata, \Imagick $resource, ?callable $callback = NULL): array {

		$metadata['width'] = $resource->getImageWidth();
		$metadata['height'] = $resource->getImageHeight();

		// Apply given callback
		if($callback) {
			$callback($resource, $metadata);
		}

		// Save required formats
		$formats = array_reverse(StorageSetting::$types[$type]['imageFormat'], TRUE);
		$resizeReference = StorageSetting::$types[$type]['imageResizeReference'] ?? [];

		$typeDestination = self::getOutputType($type, $resource);

		$splitBasename = self::parseBasename($basename);

		if($formats) {

			$resourceWidth = $metadata['width'];
			$resourceHeight = $metadata['height'];

			foreach($formats as $name => $format) {

				$resourceDestination = clone $resource;

				$fileDestination = self::getPath($splitBasename['type'], $format, $splitBasename['hash'].'.'.$splitBasename['extension']);

				if(is_int($format)) {
					$resizeFactor = max($resourceWidth, $resourceHeight) / $format;
				} else {
					$resizeFactor = min($resourceWidth / $format[0], $resourceHeight / $format[1]);
				}

				if($resizeFactor >= StorageSetting::IMAGE_RESIZE_REQUIRED_FACTOR) {
					ImageLib::resize($format, $resourceDestination, StorageSetting::$types[$type]['imageFormatConstraint'] ?? NULL);
				}

				self::buildImage($type, $name, $resourceDestination, $fileDestination, $typeDestination);

				if(in_array($name, $resizeReference)) {

					$resource->clear();
					$resource = $resourceDestination;

					$resourceWidth = $resource->getImageWidth();
					$resourceHeight = $resource->getImageHeight();

				}

			}

			$metadata['color'] = self::getColor($resourceDestination);

		}

		// Add image to buffer
		$eBuffer = new Buffer([
			'type' => $type,
			'basename' => $splitBasename['hash'].'.'.$splitBasename['extension']
		]);

		Buffer::model()
			->option('add-ignore')
			->insert($eBuffer);

		// Saves metadata file
		\media\MediaSetting::$mediaDriver->saveMetadata($basename, $metadata);

		return $metadata;

	}

	private static function getColor(\Imagick $resource) {

		require_once \Lime::getPath('framework').'/storage/lib/ColorExtractor/Color.php';
		require_once \Lime::getPath('framework').'/storage/lib/ColorExtractor/Palette.php';
		require_once \Lime::getPath('framework').'/storage/lib/ColorExtractor/ColorExtractor.php';

		$palette = \League\ColorExtractor\Palette::fromImagick($resource);
		$extractor = new \League\ColorExtractor\ColorExtractor($palette);

		$color = $extractor->extract(1)[0];

		return \League\ColorExtractor\Color::fromIntToHex($color);

	}

	/**
	 * Build an image
	 */
	public static function buildImage(string $type, ?string $format, \Imagick $resource, string $filePath, int $fileType) {

		if($fileType !== IMAGETYPE_GIF or $format === NULL) {

			switch($fileType) {

				case IMAGETYPE_JPEG :
					$resource->setImageFormat('jpeg');
					break;

				case IMAGETYPE_PNG:
					$resource->setImageFormat('png');
					break;

				case IMAGETYPE_GIF:
					$resource->setImageFormat('gif');
					break;

			}

			$profiles = $resource->getImageProfiles("icc", true);

			$resource->stripImage();

			if($profiles) {
				$resource->profileImage("icc", $profiles['icc']);
			}

			$compression = ImageLib::getCompression($type, $fileType, $format);

			if($compression !== NULL) {
				$resource->setImageCompressionQuality($compression);
			}


		}

		\media\MediaSetting::$mediaDriver->sendResource($resource, $filePath);

	}

	/**
	 * Browse images for the given type and apply callback
	 *
	 * @param string $type
	 * @param callable $callback
	 */
	public static function browse(string $type, callable $callback): bool {

		$directory = self::getDirectory($type);

		if(is_dir($directory) === FALSE) {
			return FALSE;
		}

		$files = new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS);

		foreach($files as $file) {

			if($file->isFile() and in_array(substr($file->getFileName(), -4), ['.jpg', '.png'])) {

				$callback($file);

			}

		}

		return TRUE;

	}

	/**
	 * Get path for the given type, format and file name
	 *
	 * @param string $type
	 * @param mixed $format
	 * @param string $basename
	 */
	public static function getPath(string $type, $format, string $basename): string {

		if($format === NULL) {
			return $type.'/'.$basename;
		} else {
			return $type.'/'.(is_int($format) ? $format : implode('x', $format)).'/'.$basename;
		}

	}

	public static function getAbsolutePath(string $type, $format, string $basename): string {
		return DriverLib::directory().'/'.self::getPath($type, $format, $basename);
	}

	/**
	 * Returns directory for a given type
	 *
	 * @param string $type
	 * @return string
	 */
	public static function getDirectory(string $type): string {
		return  DriverLib::directory().'/'.$type;
	}

	public static function validateBasename(string $basename): void {

		// Le hash fait exactement 20 caractères (textFixed(20, 20)), dont la lettre de format
		// en dernière position : les appelants la lisent, elle doit être là
		if(preg_match('#^[a-z][a-z0-9-]*/[A-Za-z0-9]{20}\.[a-z0-9]+$#', $basename) !== 1) {
			throw new \NotExpectedAction('Invalid basename '.$basename);
		}

	}

	public static function parseBasename(string $basename): array {

		list($type, $hash, $extension) = preg_split("/[\/\.]/", $basename);

		return [
			'type' => $type,
			'hash' => $hash,
			'extension' => $extension
		];

	}

	private static function copyMetadata(string $type, string $basenameFrom, string $basenameTo): void {

		$metadataFrom = \media\MediaSetting::$mediaDriver->getMetadata($basenameFrom);
		\media\MediaSetting::$mediaDriver->saveMetadata($basenameTo, $metadataFrom);

	}

}
?>
