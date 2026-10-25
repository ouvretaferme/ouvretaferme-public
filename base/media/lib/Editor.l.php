<?php
namespace media;

/**
 * Manages photos
 *
 */
class EditorLib extends MediaLib {

	public function clean(): bool {

		// Search in webpage
		$cElement = \website\Webpage::model()
			->select('content')
			->recordset()
			->getCollection();

		foreach($cElement as $eElement) {

			if($eElement['content']) {

				$active = $this->getHashes($eElement['content']);
				$this->activeMedias($active);

			}

		}

		// Search in news
		$cElement = \website\News::model()
			->select('content')
			->recordset()
			->getCollection();

		foreach($cElement as $eElement) {

			if($eElement['content']) {

				$active = $this->getHashes($eElement['content']);
				$this->activeMedias($active);

			}

		}

		// Search in website footers
		$cElement = \website\Website::model()
			->select('footer')
			->whereFooter('!=', NULL)
			->recordset()
			->getCollection();

		foreach($cElement as $eElement) {

			$active = $this->getHashes($eElement['footer']);
			$this->activeMedias($active);

		}

		foreach(self::getObserverContents() as $content) {

			$active = $this->getHashes($content);
			$this->activeMedias($active);

		}

		$cElement = \block\Block::model()
			->select('content')
			->whereContent('!=', NULL)
			->recordset()
			->getCollection();

		foreach($cElement as $eElement) {

			$active = $this->getHashes($eElement['content']);
			$this->activeMedias($active);

		}

		// Search in notes
		$cElement = \note\Note::model()
			->select('content')
			->whereContent('!=', NULL)
			->recordset()
			->getCollection();

		foreach($cElement as $eElement) {

			$active = $this->getHashes($eElement['content']);
			$this->activeMedias($active);

		}

		return TRUE;

	}

	protected function getHashes(string $message) {

		$hashes = [];

		if(preg_match_all('/ xyz="([a-z0-9]+)"/si', $message, $matches) > 0) {
			$hashes = array_merge($hashes, $matches[1]);
		}

		return array_unique($hashes);

	}

}
?>
