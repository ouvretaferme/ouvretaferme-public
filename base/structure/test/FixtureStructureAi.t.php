<?php
namespace structure;

/**
 * Le décor des tests est invisible du produit (chantier tech, lot 6.2a) : une structure posée sur
 * un identifiant de `StructureSetting::FIXTURE_STRUCTURES` sort des fermes installées, entre dans ce
 * que les chiffres écartent, et n'entre pas dans les comptes de l'admin.
 *
 * Le décor lui-même est monté par `FixtureAiLib`. Ce fichier n'en dépend pas : il occupe dans sa
 * transaction chaque identifiant réservé encore libre, et prend en place ceux qui ne le sont plus.
 * L'écriture est directe, et c'est voulu : `StructureLib::create()` émet un `CREATE DATABASE`, qui
 * validerait la transaction et laisserait en base ce que le test pose. Le précédent est
 * `SchoolAiLib::createSchool()`, qui insère de la même façon à un identifiant réservé.
 *
 * Chaque contrôle a son témoin : une ferme installée posée dans la même transaction sur un
 * identifiant libre et non réservé, écrite à l'identique, que le produit doit voir. Sans lui, un
 * filtre qui écarterait tout le monde passerait pour juste.
 *
 * Les comptes portent sur tout le parc, que ce fichier ne maîtrise pas : ils se comparent au compte
 * brut de la même requête, diminué des identifiants réservés, jamais à une valeur absolue.
 *
 * Lancement : dev/test package=structure file=FixtureStructureAi
 */
class FixtureStructureAiTest extends \dev\TransactionTest {

	/** Le préfixe `zzTest` range les deux noms parmi les structures de test, si une transaction venait à être validée. */
	const NAME = 'zzTest identifiant réservé';
	const NAME_WITNESS = 'zzTest identifiant libre';

	/** Un domaine réservé, où rien n'est jamais délivré. */
	const EMAIL = 'zztest.identifiant.reserve@example.org';

	/** La seule colonne sans défaut qu'une ligne de structure exige en plus de son nom. */
	const REQUIRED = [
		'legalEmail' => self::EMAIL,
	];

	/** L'état le plus visible qu'une structure puisse avoir : installée, active, adhérente, qui partage ses données. */
	const VISIBLE = [
		'type' => Structure::PRODUCER,
		'producerType' => Structure::REAL,
		'communityType' => NULL,
		'consentData' => TRUE,
		'membership' => TRUE,
		'status' => Structure::ACTIVE,
	];

	protected function getTransactionModel(): \ModuleModel {
		return Structure::model();
	}

	/**
	 * Occupe chaque identifiant réservé par une structure dans l'état le plus visible. Une structure
	 * déjà en place reçoit le même état, dans la transaction.
	 */
	private function placeFixtures(): void {

		foreach(StructureSetting::FIXTURE_STRUCTURES as $fixture) {

			$eStructure = new Structure([
				'id' => $fixture,
				'name' => self::NAME.' '.$fixture,
			] + self::VISIBLE + self::REQUIRED);

			if(Structure::model()->whereId($fixture)->exists()) {

				Structure::model()
					->select(['name', ...array_keys(self::VISIBLE)])
					->update($eStructure);

			} else {
				Structure::model()->insert($eStructure);
			}

		}

	}

	/**
	 * Le témoin : la même ferme, sur l'identifiant que la base lui donne.
	 */
	private function placeWitness(): Structure {

		$eStructure = new Structure([
			'name' => self::NAME_WITNESS,
		] + self::VISIBLE + self::REQUIRED);

		Structure::model()->insert($eStructure);

		return $eStructure;

	}

	private function isIn(\Sql $sql, int $structure): bool {

		return Structure::model()
			->whereId($structure)
			->whereId('IN', $sql)
			->exists();

	}

	/**
	 * 1. La méthode d'élément lit l'identifiant dans la constante, et rien d'autre : une ferme
	 * installée sur un identifiant réservé est du décor, la même sur un autre ne l'est pas.
	 */
	public function testIsFixture(): void {

		foreach(StructureSetting::FIXTURE_STRUCTURES as $fixture) {
			$this->assertTrue(new Structure(['id' => $fixture, 'producerType' => Structure::REAL])->isFixture(), 'la structure '.$fixture.' est du décor');
		}

		$eStructureWitness = $this->placeWitness();

		$this->assertTrue(in_array($eStructureWitness['id'], StructureSetting::FIXTURE_STRUCTURES) === FALSE, 'le témoin n\'a pas reçu un identifiant réservé ('.$eStructureWitness['id'].')');
		$this->assertTrue($eStructureWitness->isFixture() === FALSE, 'le témoin '.$eStructureWitness['id'].' n\'est pas du décor');

	}

	/**
	 * 2. Une ferme du décor est installée pour les gardes, et ne l'est pas pour les agrégats : elle
	 * sort de `getInstalledSql()` et entre dans `getNotInstalledSql()`. Le témoin fait l'inverse.
	 */
	public function testInstalledSql(): void {

		$this->placeFixtures();
		$eStructureWitness = $this->placeWitness();

		foreach(StructureSetting::FIXTURE_STRUCTURES as $fixture) {

			$this->assertTrue(new Structure(['id' => $fixture, 'type' => Structure::PRODUCER, 'producerType' => Structure::REAL])->isInstalled(), 'la ferme '.$fixture.' franchit les gardes d\'une ferme installée');
			$this->assertTrue($this->isIn(StructureLib::getInstalledSql(), $fixture) === FALSE, 'la ferme '.$fixture.' sort des fermes installées');
			$this->assertTrue($this->isIn(StructureLib::getNotInstalledSql(), $fixture), 'la ferme '.$fixture.' entre dans ce que les chiffres écartent');

		}

		$this->assertTrue($this->isIn(StructureLib::getInstalledSql(), $eStructureWitness['id']), 'le témoin est une ferme installée');
		$this->assertTrue($this->isIn(StructureLib::getNotInstalledSql(), $eStructureWitness['id']) === FALSE, 'le témoin n\'est pas écarté des chiffres');

	}

	/**
	 * 3. Ce que les chiffres écartent nomme le décor quel que soit son type : une boutique, qui ne
	 * porte aucune catégorie de ferme, y entre par son seul identifiant. La boutique témoin n'y
	 * entre pas.
	 */
	public function testNotInstalledSqlWhateverType(): void {

		$this->placeFixtures();
		$eStructureWitness = $this->placeWitness();

		Structure::model()
			->whereId('IN', [...StructureSetting::FIXTURE_STRUCTURES, $eStructureWitness['id']])
			->update(['type' => Structure::SHOP, 'producerType' => NULL]);

		foreach(StructureSetting::FIXTURE_STRUCTURES as $fixture) {
			$this->assertTrue($this->isIn(StructureLib::getNotInstalledSql(), $fixture), 'la boutique '.$fixture.' entre dans ce que les chiffres écartent');
		}

		$this->assertTrue($this->isIn(StructureLib::getNotInstalledSql(), $eStructureWitness['id']) === FALSE, 'la boutique témoin n\'est pas écartée des chiffres');

	}

	/**
	 * 4. Les comptes de l'admin et le compte du consentement valent le compte brut diminué du
	 * décor. Le décor compte au moins une ligne dans chaque compte brut, sans quoi les deux côtés
	 * seraient égaux pour une mauvaise raison.
	 */
	public function testCounts(): void {

		$this->placeFixtures();
		$this->placeWitness();

		$fixtures = count(StructureSetting::FIXTURE_STRUCTURES);

		$this->assertEquals($fixtures, Structure::model()->whereId('IN', StructureSetting::FIXTURE_STRUCTURES)->count(), 'chaque identifiant réservé porte une structure');

		$this->assertEquals(
			Structure::model()->count() - $fixtures,
			AdminLib::countStructures(),
			'le total de l\'admin écarte le décor'
		);

		$this->assertEquals(
			Structure::model()->whereConsentData(TRUE)->whereStatus(Structure::ACTIVE)->count() - $fixtures,
			StructureLib::countConsentData(),
			'le compte des structures qui partagent leurs données écarte le décor'
		);

	}

	/**
	 * 5. Le témoin compte : posé après une première lecture, il ajoute un à chaque compte. Le
	 * décor, posé entre les deux, n'ajoute rien.
	 */
	public function testCountsByDifference(): void {

		$before = [AdminLib::countStructures(), StructureLib::countConsentData()];

		// Un décor déjà en place peut avoir été clos ou ne pas partager ses données : l'état posé ici
		// ne doit rien changer aux comptes, qu'il soit inséré ou réécrit.
		$this->placeFixtures();

		$between = [AdminLib::countStructures(), StructureLib::countConsentData()];

		$this->assertEquals($before, $between, 'le décor posé, aucun compte ne bouge');

		$this->placeWitness();

		$after = [AdminLib::countStructures(), StructureLib::countConsentData()];

		$this->assertEquals(array_map(fn(int $count) => $count + 1, $before), $after, 'le témoin posé, chaque compte gagne un');

	}

}
?>
