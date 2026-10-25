<?php
namespace gallery;

class PhotoLib extends PhotoCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['takenAt', 'structure', 'sequence', 'series', 'task', 'title' /* Le titre est vérifié à la fin car il dépend d'autres paramètres */];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['title', 'takenAt'];
	}

	public static function getBySequence(\sequence\Sequence $eSequence): \Collection {

		return Photo::model()
			->select(Photo::model()->getProperties())
			->whereSequence($eSequence)
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	public static function getBySeries(\series\Series $eSeries): \Collection {

		return Photo::model()
			->select(Photo::model()->getProperties())
			->whereSeries($eSeries)
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	public static function getByTask(\series\Task $eTask): \Collection {

		return Photo::model()
			->select(Photo::model()->getProperties())
			->whereTask($eTask)
			->sort(['createdAt' => SORT_DESC])
			->getCollection();

	}

	public static function canCreate(string $hash): string {

		$eUser = \user\ConnectionLib::getOnline();

		return \media\Media::model()
			->select('hash')
			->whereUser($eUser)
			->whereType('gallery-photo')
			->whereHash($hash)
			->exists();

	}

	public static function create(Photo $e): void {

		$e->add([
			'sequence' => new \sequence\Sequence(),
			'series' => new \series\Series(),
			'task' => new \series\Task()
		]);

		$path = \storage\DriverLib::directory().'/'.new \media\GalleryUi()->getBasenameByHash($e['hash']);

		[$e['width'], $e['height']] = getimagesize($path);

		if($e['series']->notEmpty()) {
			$e['structure'] = $e['series']['structure'];
		} else if($e['task']->notEmpty()) {
			$e['structure'] = $e['task']['structure'];
			$e['series'] = $e['task']['series'];
		} else if($e['sequence']->notEmpty()) {
			$e['structure'] = $e['sequence']['structure'];
		} else {
			throw new \Exception('Missing series, sequence or structure in Photo');
		}

		Photo::model()->insert($e);

	}

}
?>
