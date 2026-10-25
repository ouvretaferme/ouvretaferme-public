<?php
namespace securing;

/**
 * `SignatureLib::sign()` verrouille le dernier maillon de la source avant de chaîner dessus : sans ce
 * verrou, deux signatures simultanées liraient le même maillon et la chaîne se dédoublerait, ce que
 * `SignatureControlLib` ne verrait qu'au contrôle suivant (chantier caisse, lot L1, où plusieurs
 * appareils envoient au même instant).
 *
 * Le verrou se prouve sur une **seconde connexion**, comme `framework/dev/test/RowLock.t.php` : elle tient
 * le dernier maillon par un `UPDATE` sans effet, et la signature, qui n'attend qu'une seconde, doit buter
 * dessus. Sans le verrou, la lecture du maillon ne serait qu'une lecture cohérente, qui n'attend personne.
 *
 * La chaîne vit dans la base de la structure, qu'aucune transaction de test ne couvre : les maillons du
 * test se retirent en fin de méthode.
 *
 * Lancement : dev/test package=securing file=SignatureLockAi
 */
class SignatureLockAiTest extends \Test {

	/** Un entier nu : `reference` ne porte aucune clé étrangère. */
	const REFERENCE = 999999996;

	/** Le code MySQL de « Lock wait timeout exceeded », que `Database::handleFailure()` ne nomme pas. */
	const LOCK_WAIT_TIMEOUT = 1205;

	private \structure\Structure $eStructure;

	/** Les repères de la structure avant le test, que clean() lui rend. */
	private \Collection $cSignatureTailBefore;

	public function init(): void {

		$this->eStructure = \structure\Structure::model()
			->select(\structure\Structure::getSelection())
			->whereId(\structure\FixtureAiLib::F1)
			->get();

		if($this->eStructure->empty()) {
			throw new \Exception('Structure de référence #'.\structure\FixtureAiLib::F1.' introuvable : sème le décor par structure/configure/fixtureAi write=1');
		}

		\structure\StructureLib::connectDatabase($this->eStructure, migrate: TRUE);

		$this->cSignatureTailBefore = SignatureTail::model()
			->select(['source', 'key', 'hmac', 'updatedAt'])
			->getCollection();

	}

	public function setUp(): void {
		$this->clean();
	}

	public function finalize(): void {
		$this->clean();
	}

	/**
	 * La chaîne ne se réécrit jamais par une Lib, qui n'a pas de suppression : retirer les maillons du test,
	 * posés en dernier, rend à la source la fin de chaîne qu'elle avait avant lui, et ses repères d'avant le
	 * test la retrouvent.
	 */
	protected function clean(): void {

		Signature::model()
			->whereSource(Signature::SALE)
			->whereReference(self::REFERENCE)
			->delete();

		// Reposés tels quels, date comprise, là où SignatureTailLib::synchronize() daterait leur retour : le décor
		// rend la table qu'il a trouvée, sans rien construire qu'un build() aurait à vérifier
		SignatureTail::model()
			->where(TRUE)
			->delete();

		if($this->cSignatureTailBefore->notEmpty()) {
			SignatureTail::model()->insert($this->cSignatureTailBefore);
		}

	}

	/** La seconde connexion de tous les cas, ouverte en transaction : un verrou ne se voit que d'une autre transaction. */
	protected function openSecondConnection(): \Database {

		$database = new \Database('securing');
		$database->beginTransaction();

		return $database;

	}

	/**
	 * Tente de prendre le maillon sur la seconde connexion, qui n'attend qu'une seconde.
	 * Rend le message de l'échec, ou NULL quand le maillon était libre.
	 */
	protected function tryLock(\Database $database, int $signature): ?string {

		$mSignature = Signature::model();

		$database->exec('SET SESSION innodb_lock_wait_timeout = 1');

		try {
			$database->exec('UPDATE `'.$mSignature->getDatabase().'`.`'.$mSignature->getTable().'` SET hmac = hmac WHERE id = '.$signature);
		} catch(\Throwable $e) {
			return $e->getMessage();
		}

		return NULL;

	}

	protected function getLastLink(): int {

		return Signature::model()
			->whereSource(Signature::SALE)
			->sort(['id' => SORT_DESC])
			->getValue('id');

	}

	/** Les maillons du test, du plus ancien au plus récent. */
	protected function getLinks(): \Collection {

		return Signature::model()
			->select(['id', 'data', 'key', 'hmac', 'hmacChained'])
			->whereSource(Signature::SALE)
			->whereReference(self::REFERENCE)
			->sort(['id' => SORT_ASC])
			->getCollection();

	}

	public function testSignatureChainsOnTheLastLink(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);

		$cSignature = Signature::model()
			->select(['hmac', 'hmacChained'])
			->whereSource(Signature::SALE)
			->whereReference(self::REFERENCE)
			->sort(['id' => SORT_ASC])
			->getCollection();

		$this->assertEquals(2, $cSignature->count());
		$this->assertEquals($cSignature->first()['hmac'], $cSignature->last()['hmacChained'], 'la seconde signature chaîne sur la première');

	}

	public function testSignatureWaitsForTheLastLink(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);

		$mSignature = Signature::model();

		$signatureLast = Signature::model()
			->whereSource(Signature::SALE)
			->sort(['id' => SORT_DESC])
			->getValue('id');

		$database = $this->openSecondConnection();
		$database->exec('UPDATE `'.$mSignature->getDatabase().'`.`'.$mSignature->getTable().'` SET hmac = hmac WHERE id = '.(int)$signatureLast);

		$mSignature->pdo()->exec('SET SESSION innodb_lock_wait_timeout = 1');

		$code = NULL;

		try {
			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);
		} catch(\PDOException $e) {
			$code = (int)($e->errorInfo[1] ?? 0);
			$mSignature->rollBack();
		} finally {
			$mSignature->pdo()->exec('SET SESSION innodb_lock_wait_timeout = DEFAULT');
			$database->rollBack();
		}

		$this->assertEquals(self::LOCK_WAIT_TIMEOUT, $code, 'la signature n\'a pas attendu le dernier maillon tenu par une autre connexion');

		// Le maillon relâché, la même signature passe : l'attente venait bien du verrou
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);

		$this->assertEquals(2, Signature::model()->whereSource(Signature::SALE)->whereReference(self::REFERENCE)->count());

	}

	/** Une vente se signe à chaque recalcul : tant que ses données ne changent pas, la chaîne ne s'allonge pas. */
	public function testSameDataSignedAgainAddsNoLink(): void {

		$data = ['id' => self::REFERENCE, 'marketDevice' => 12, 'marketSequence' => 3, 'soldAt' => '2026-10-12 08:15:00'];

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, $data);
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, $data);
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, $data);

		$this->assertEquals(1, $this->getLinks()->count(), 'trois signatures des mêmes données font un maillon');

		// La séquence en chaîne de caractères n'est pas la même donnée : la sérialisation garde le type
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['marketSequence' => '3'] + $data);

		$cSignature = $this->getLinks();

		$this->assertEquals(2, $cSignature->count());
		$this->assertEquals($cSignature->first()['hmac'], $cSignature->last()['hmacChained']);

		// Revenir aux premières données est un changement de plus, pas un retour au premier maillon
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, $data);

		$this->assertEquals(3, $this->getLinks()->count());

	}

	/** Chaque maillon se recalcule de ses données et du maillon qui le précède dans la source. */
	public function testEveryLinkIsTheHmacOfItsDataAndOfThePreviousLink(): void {

		$hmacBefore = Signature::model()
			->whereSource(Signature::SALE)
			->sort(['id' => SORT_DESC])
			->getValue('hmac');

		foreach([1, 2, 3, 4, 5] as $sequence) {
			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['marketSequence' => $sequence]);
		}

		$cSignature = $this->getLinks();

		$this->assertEquals(5, $cSignature->count());

		foreach($cSignature as $eSignature) {

			$this->assertEquals($hmacBefore, $eSignature['hmacChained'], 'le maillon '.$eSignature['id'].' suit le précédent de la source');
			$this->assertEquals(SignatureLib::getHmac($eSignature), $eSignature['hmac'], 'le maillon '.$eSignature['id'].' se recalcule');

			$hmacBefore = $eSignature['hmac'];

		}

	}

	/**
	 * Une signature qui échoue ne doit pas garder le dernier maillon : un processus qui signe pour plusieurs
	 * structures ou plusieurs ventes à la suite bloquerait sinon toutes les signatures de la structure jusqu'à
	 * sa fin. L'échec est ici une référence que la colonne ne peut pas porter : l'insertion lève sous le verrou,
	 * là où une donnée qui ne se sérialise pas lèverait avant lui et ne prouverait rien.
	 */
	public function testFailedSignatureReleasesTheLastLink(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);

		$failed = FALSE;

		try {
			SignatureLib::sign($this->eStructure, Signature::SALE, Signature::model()->getPropertyRange('reference')[1] + 1, ['test' => 2]);
		} catch(\Exception) {
			$failed = TRUE;
		}

		$this->assertTrue($failed, 'une référence hors de la colonne fait échouer l\'insertion du maillon');

		$database = $this->openSecondConnection();
		$message = $this->tryLock($database, $this->getLastLink());
		$database->rollBack();

		// Sans effet si la signature a rendu sa transaction, et rend la connexion à la suite des tests sinon
		Signature::model()->rollBack();

		$this->assertEquals(NULL, $message, 'le dernier maillon est resté verrouillé par la signature qui a échoué');
		$this->assertEquals(1, $this->getLinks()->count(), 'la signature en échec n\'a rien écrit');

	}

	/**
	 * En production, `sign()` est appelé dans la transaction de celui qui écrit la vente (`SaleLib::recalculate()`).
	 * Son `commit()` ne valide alors rien : le maillon et son verrou durent jusqu'à la fin de la transaction externe.
	 */
	public function testSignatureInsideATransactionHoldsTheLastLinkUntilItsEnd(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);

		$signatureLast = $this->getLastLink();

		Signature::model()->beginTransaction();

		try {

			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);

			$database = $this->openSecondConnection();
			$messageInside = $this->tryLock($database, $signatureLast);
			$database->rollBack();

			Signature::model()->commit();

		} catch(\Throwable $e) {

			Signature::model()->rollBack();

			throw $e;

		}

		$this->assertTrue($messageInside !== NULL and str_contains($messageInside, 'Lock wait timeout'), 'le maillon est tenu tant que la transaction externe est ouverte : '.var_export($messageInside, TRUE));

		$database = $this->openSecondConnection();
		$messageAfter = $this->tryLock($database, $signatureLast);
		$database->rollBack();

		$this->assertEquals(NULL, $messageAfter, 'et rendu à son commit');
		$this->assertEquals(2, $this->getLinks()->count());

	}

	/**
	 * Valide sur la seconde connexion un maillon chaîné sur le dernier, comme le ferait un autre processus.
	 * Rend son hmac.
	 */
	protected function commitLinkElsewhere(array $data): string {

		$eSignatureLast = Signature::model()
			->select(['hmac'])
			->whereSource(Signature::SALE)
			->sort(['id' => SORT_DESC])
			->get();

		$eSignature = new Signature([
			'source' => Signature::SALE,
			'reference' => self::REFERENCE,
			'data' => serialize($data),
			'key' => count(\main\MainSetting::$hmacKeys) - 1,
			'hmacChained' => $eSignatureLast['hmac'],
		]);

		$eSignature['hmac'] = SignatureLib::getHmac($eSignature);

		$mSignature = Signature::model();

		$database = $this->openSecondConnection();
		$database->exec(
			'INSERT INTO `'.$mSignature->getDatabase().'`.`'.$mSignature->getTable().'` (source, reference, data, `key`, hmac, hmacChained, createdAt) VALUES ('.
			$mSignature->format(Signature::SALE).', '.self::REFERENCE.', '.$mSignature->format($eSignature['data']).', '.$eSignature['key'].', '.
			$mSignature->format($eSignature['hmac']).', '.$mSignature->format($eSignature['hmacChained']).', NOW())'
		);
		$database->commit();

		return $eSignature['hmac'];

	}

	/**
	 * `sign()` tourne dans la transaction de l'appelant, dont la vue date de sa première lecture. Un maillon validé
	 * ensuite par un autre processus n'y est pas : lu par une lecture simple, le dernier maillon serait celui
	 * d'avant, et la chaîne fourcherait sans erreur.
	 */
	public function testSignatureFromAStaleViewChainsOnTheNewestLink(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);

		Signature::model()->beginTransaction();

		try {

			// La première lecture fixe la vue de la transaction
			$this->assertEquals(1, $this->getLinks()->count());

			$hmacElsewhere = $this->commitLinkElsewhere(['test' => 2]);

			$this->assertEquals(1, $this->getLinks()->count(), 'la vue de la transaction ignore le maillon validé ailleurs');

			// La même donnée que le maillon validé ailleurs : rien n'a changé, rien ne se signe
			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);

			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 3]);

			Signature::model()->commit();

		} catch(\Throwable $e) {

			Signature::model()->rollBack();

			throw $e;

		}

		$cSignature = $this->getLinks();

		$this->assertEquals(3, $cSignature->count(), 'la donnée déjà signée ailleurs ne pose pas de second maillon');
		$this->assertEquals($hmacElsewhere, $cSignature->last()['hmacChained'], 'la signature chaîne sur le maillon validé ailleurs, pas sur celui de sa vue');
		$this->assertEquals($cSignature->first()['hmac'], $cSignature->find(fn(Signature $eSignature) => $eSignature['hmac'] === $hmacElsewhere)->first()['hmacChained'], 'et le maillon validé ailleurs suit celui du test : la chaîne n\'a pas de fourche');

	}

	public function testSignatureRolledBackWithItsTransactionWritesNothing(): void {

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 1]);

		Signature::model()->beginTransaction();

		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 2]);

		$this->assertEquals(2, $this->getLinks()->count(), 'dans sa transaction, le maillon se lit');

		Signature::model()->rollBack();

		$this->assertEquals(1, $this->getLinks()->count(), 'annulé avec elle, il n\'existe plus');

		$database = $this->openSecondConnection();
		$message = $this->tryLock($database, $this->getLastLink());
		$database->rollBack();

		$this->assertEquals(NULL, $message, 'et le dernier maillon est rendu');

		// La chaîne reprend sur le maillon d'avant l'annulation
		SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['test' => 3]);

		$cSignature = $this->getLinks();

		$this->assertEquals($cSignature->first()['hmac'], $cSignature->last()['hmacChained']);

	}

}
?>
