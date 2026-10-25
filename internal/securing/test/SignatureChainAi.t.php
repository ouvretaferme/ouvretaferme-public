<?php
namespace securing;

/**
 * Le contrôle de la chaîne (`SignatureControlLib::controlHmac()`) voit un maillon retiré (CGI, art. 286, I,
 * 3° bis, inaltérabilité). Relevé par lecture le 13/10/2026 (inventaire de conformité, A1) : chaque HMAC se
 * recalculait avec le `hmacChained` enregistré sur sa propre ligne, et un maillon retiré laissait toutes les
 * lignes cohérentes avec elles-mêmes.
 *
 * La chaîne vit dans la base de la structure, qu'aucune transaction de test ne couvre : les maillons du test
 * se retirent en fin de méthode, comme dans SignatureLockAi.
 *
 * Lancement : dev/test package=securing file=SignatureChainAi
 */
class SignatureChainAiTest extends \Test {

	/** Un entier nu : `reference` ne porte aucune clé étrangère. */
	const REFERENCE = 999999995;

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
	 * posés en dernier, rend à chaque source la fin de chaîne qu'elle avait avant lui, et ses repères d'avant
	 * le test la retrouvent.
	 */
	protected function clean(): void {

		Signature::model()
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

	public function testRemovedLinkBreaksTheChain(): void {

		$ids = [];

		foreach(['premier', 'deuxième', 'troisième'] as $state) {

			SignatureLib::sign($this->eStructure, Signature::SALE, self::REFERENCE, ['state' => $state]);

			$ids[] = Signature::model()
				->whereSource(Signature::SALE)
				->whereReference(self::REFERENCE)
				->sort(['id' => SORT_DESC])
				->getValue('id');

		}

		$anomalies = $this->control();

		$this->assertEquals([], array_intersect_key($anomalies, array_flip($ids)), 'trois maillons signés à la suite chaînent');

		// Le retrait est le geste que le contrôle doit voir : aucune Lib ne supprime un maillon, et un test qui
		// passerait par build() ne pourrait pas produire la chaîne abîmée qu'il éprouve
		Signature::model()
			->whereId($ids[1])
			->delete();

		$anomalies = $this->control();

		$this->assertTrue(array_key_exists($ids[2], $anomalies), 'le maillon qui suit un maillon retiré est signalé');
		$this->assertTrue(str_starts_with($anomalies[$ids[2]], 'chained on '), 'comme une rupture de la chaîne, son HMAC restant juste');
		$this->assertFalse(array_key_exists($ids[0], $anomalies), 'le maillon d\'avant reste juste');

	}

	/**
	 * Le retrait du dernier maillon laisse une chaîne plus courte dont chaque lien tient : seul le repère, écrit
	 * hors de la chaîne avec chaque maillon et signé avec la clé, le voit (CGI, art. 286, I, 3° bis), y compris
	 * quand on recopie dans le repère le HMAC du nouveau dernier maillon. Éprouvé sur chaque source, la chaîne de
	 * chacune se contrôlant à part.
	 */
	public function testRemovedLastLinkIsDetected(): void {

		foreach(Signature::model()->getPropertyEnum('source') as $source) {

			SignatureLib::sign($this->eStructure, $source, self::REFERENCE, ['state' => 'premier']);
			SignatureLib::sign($this->eStructure, $source, self::REFERENCE, ['state' => 'deuxième']);

			$this->assertFalse(array_key_exists(SignatureControlLib::TAIL, $this->control($source)), $source.' : la chaîne intacte finit sur son repère');

			// Le retrait est le geste que le contrôle doit voir, et aucune Lib ne supprime un maillon
			Signature::model()
				->whereSource($source)
				->whereReference(self::REFERENCE)
				->sort(['id' => SORT_DESC])
				->limit(1)
				->delete();

			$anomalies = $this->control($source);

			$this->assertTrue(array_key_exists(SignatureControlLib::TAIL, $anomalies), $source.' : le dernier maillon retiré se voit');
			$this->assertTrue(str_starts_with($anomalies[SignatureControlLib::TAIL], 'chain ends on '), $source.' : comme une fin de chaîne qui ne tombe pas sur le repère');

			// Réaligner le repère sans la clé, en y recopiant le HMAC du nouveau dernier maillon, lu dans la chaîne
			$hmacLast = Signature::model()
				->whereSource($source)
				->sort(['id' => SORT_DESC])
				->getValue('hmac');

			SignatureTail::model()
				->whereSource($source)
				->update(['hmac' => $hmacLast]);

			$this->assertTrue(array_key_exists(SignatureControlLib::TAIL, $this->control($source)), $source.' : un repère réaligné sans la clé se voit aussi');

		}

	}

	/**
	 * Une source sans maillon ni repère est une chaîne vide, pas une chaîne altérée ; une source dont tous les
	 * maillons ont disparu garde son repère, qui le dit. La structure de référence ne signe aucune archive de
	 * vente : sa chaîne est vide, et le test le vérifie avant de s'y fier.
	 */
	public function testEmptyChain(): void {

		$source = Signature::SELLING_ARCHIVE;

		$this->assertEquals(0, Signature::model()->whereSource($source)->count(), 'la chaîne éprouvée est vide');
		$this->assertTrue(SignatureTailLib::getBySource($source)->empty(), 'et sans repère');

		$this->assertEquals([], $this->control($source), 'une chaîne vide sans repère n\'est pas signalée');

		SignatureLib::sign($this->eStructure, $source, self::REFERENCE, ['state' => 'seul']);

		Signature::model()
			->whereSource($source)
			->whereReference(self::REFERENCE)
			->delete();

		$this->assertTrue(array_key_exists(SignatureControlLib::TAIL, $this->control($source)), 'une chaîne vidée garde son repère, qui le dit');

	}

	private function control(string $source = Signature::SALE): array {

		ob_start();
		$anomalies = SignatureControlLib::controlHmac($this->eStructure, $source);
		ob_end_clean();

		return $anomalies;

	}

}
?>
