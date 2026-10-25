<?php
namespace aid;

class SourceLib extends SourceCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['url', 'kind'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['url', 'kind'];
	}

	// Les colonnes que tient la veille (aid\WatchLib), seules qu'elle écrit.
	public static function getPropertiesWatch(): array {
		return ['status', 'statusAt', 'movedUrl', 'fingerprint', 'failures', 'failedAt', 'unreadSince', 'unreadReason', 'checkedAt', 'changedAt'];
	}

	// Une source avec l'état de son aide, que lisent acceptDelete() et l'étape `kind.published`.
	public static function getSelectionWrite(): array {
		return array_diff(Source::getSelection(), ['aid']) + ['aid' => ['status']];
	}

	/**
	 * Une source qui cesse d'être officielle peut retirer à son aide publiée la dernière qu'elle avait :
	 * la question se repose sous verrou (acceptDeleteLocked()). Une adresse changée repart de zéro pour
	 * la veille (prepareWatchReset()). Les deux se tiennent dans la transaction, que la veille ne peut
	 * pas couper : elle n'écrit que sur l'adresse qu'elle a lue (updateWatch()).
	 */
	public static function update(Source $e, array $properties): void {

		Source::model()->beginTransaction();

		if(
			in_array('kind', $properties) and
			$e['kind'] !== Source::PRIMARY and
			self::acceptDeleteLocked($e) === FALSE
		) {
			Source::model()->commit();
			Source::fail('kind.published');
			return;
		}

		if(in_array('url', $properties)) {
			$properties = array_merge($properties, self::prepareWatchReset($e));
		}

		parent::update($e, $properties);

		Source::model()->commit();

	}

	/**
	 * La veille ne garde pas pour une nouvelle page ce qu'elle a vu de l'ancienne (cadrage de L5, R16) :
	 * une source dont l'adresse change en base redevient une source jamais lue. L'adresse se relit sous
	 * verrou, pour qu'une lecture de nuit qui écrit sur l'ancienne adresse passe avant ou ne trouve plus
	 * la ligne. Rend les propriétés remises à zéro, aucune quand l'adresse ne change pas.
	 */
	private static function prepareWatchReset(Source $e): array {

		$url = Source::model()
			->whereId($e)
			->option('for-update')
			->getValue('url');

		if($url === $e['url']) {
			return [];
		}

		$e->merge([
			'status' => Source::ALIVE,
			'statusAt' => NULL,
			'movedUrl' => NULL,
			'fingerprint' => NULL,
			'failures' => 0,
			'failedAt' => NULL,
			'unreadSince' => NULL,
			'unreadReason' => NULL,
			'checkedAt' => NULL,
			'changedAt' => NULL,
		]);

		return self::getPropertiesWatch();

	}

	/**
	 * Ce que la veille a lu d'une source, écrit sur la seule ligne qui porte encore l'adresse lue : une
	 * source dont l'équipe a changé l'adresse ou qu'elle a supprimée pendant la lecture ne reçoit rien
	 * (cadrage de L5, CL-11 et CL-12).
	 */
	public static function updateWatch(Source $e, string $urlRead, array $properties): void {

		Source::model()
			->select($properties)
			->whereUrl($urlRead)
			->update($e);

	}

	public static function delete(Source $e): void {

		Source::model()->beginTransaction();

		if(self::acceptDeleteLocked($e) === FALSE) {
			Source::model()->commit();
			Source::fail('deletePrimary');
			return;
		}

		parent::delete($e);

		Source::model()->commit();

	}

	/**
	 * Source::acceptDelete() reposée dans la transaction qui écrit, l'aide verrouillée et la nature relue
	 * en base : deux sources officielles retirées ou changées en même temps laisseraient l'aide publiée
	 * sans aucune, chacune voyant encore l'autre.
	 */
	private static function acceptDeleteLocked(Source $e): bool {

		$e->expects(['id', 'aid']);

		$eSource = new Source([
			'id' => $e['id'],
			'aid' => new Aid(['id' => $e['aid']['id']]),
		]);

		Aid::model()
			->select('status')
			->whereId($eSource['aid'])
			->option('for-update')
			->get($eSource['aid']);

		Source::model()
			->select('kind')
			->whereId($eSource)
			->get($eSource);

		return $eSource->acceptDelete();

	}

	// Une source que la reprise remplace : la fiche apporte les siennes dans la même transaction, et une
	// aide publiée que la fiche laisse sans source officielle y repasse en brouillon (ImportLib).
	public static function deleteReplaced(Source $e): void {
		parent::delete($e);
	}

	// Les natures qu'une fiche de recherche connaît (ImportLib::getSources()), seules que la reprise
	// remplace : une liste des pièces est saisie par l'équipe, et aucune fiche ne la porte.
	public static function getKindsSeed(): array {
		return [Source::PRIMARY, Source::RELAY, Source::AGGREGATOR];
	}

	/**
	 * Les sources d'une aide alignées sur celles d'une reprise, par adresse comme existsByUrl() : une
	 * source gardée garde ce que la veille en a relevé (état, échecs, empreinte, dates), seule sa nature
	 * suit la fiche. Comme deleteReplaced(), sans la garde de la source officielle : la reprise rend
	 * l'état de publication dans la même transaction (ImportLib::seedAid()). L'appariement lit toutes
	 * les natures, puisqu'une adresse ne se cite qu'une fois par aide et que create() ne le vérifie pas
	 * (Source::properties()) : une liste des pièces que la fiche cite prend la nature de la fiche. Une
	 * source qu'aucune fiche ne connaît reste (getKindsSeed()).
	 */
	public static function synchronize(Aid $eAid, array $sources): void {

		$cSource = Source::model()
			->select(['id', 'url', 'kind'])
			->whereAid($eAid)
			->getCollection();

		$sourcesByUrl = [];

		foreach($cSource as $eSource) {
			$sourcesByUrl[rtrim($eSource['url'], '/')] = $eSource;
		}

		foreach($sources as $eSourceSeed) {

			$url = rtrim($eSourceSeed['url'], '/');

			if(array_key_exists($url, $sourcesByUrl)) {

				$eSource = $sourcesByUrl[$url];
				unset($sourcesByUrl[$url]);

				if($eSource['kind'] !== $eSourceSeed['kind']) {
					$eSource['kind'] = $eSourceSeed['kind'];
					parent::update($eSource, ['kind']);
				}

			} else {
				$eSourceSeed['aid'] = $eAid;
				self::create($eSourceSeed);
			}

		}

		foreach($sourcesByUrl as $eSource) {
			if(in_array($eSource['kind'], self::getKindsSeed(), TRUE)) {
				self::deleteReplaced($eSource);
			}
		}

	}

	// Une autre source officielle de la même aide.
	public static function existsPrimaryOther(Source $e): bool {

		return Source::model()
			->whereAid($e['aid'])
			->whereKind(Source::PRIMARY)
			->whereId('!=', $e)
			->exists();

	}

	// Une autre source de l'aide à cette adresse, hors celle qu'on enregistre. La barre finale ne change
	// pas la page : https://example.org/aide et https://example.org/aide/ sont une seule source.
	public static function existsByUrl(Aid $eAid, string $url, Source $eSourceExclude): bool {

		$urlBare = rtrim($url, '/');

		return Source::model()
			->whereAid($eAid)
			->whereUrl('IN', [$urlBare, $urlBare.'/'])
			->whereId('!=', $eSourceExclude, if: $eSourceExclude->exists())
			->exists();

	}

	public static function getByAid(Aid $eAid): \Collection {

		return Source::model()
			->select(Source::getSelection())
			->whereAid($eAid)
			->getCollection();

	}

}
?>
