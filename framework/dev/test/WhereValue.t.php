<?php
namespace dev;

/**
 * Une égalité ou un `IN` sur une propriété entière écarte une valeur que son type refuse
 * (`ModuleModel::acceptWhereValue()`).
 *
 * MySQL compare une chaîne à une colonne entière par son début numérique, donc sans cette garde
 * `whereId('12abc')` rend la ligne 12 : une API qui passe le corps de la requête à `whereId()`
 * répond sur une ressource que l'appelant n'a pas nommée.
 *
 * Le test ne lit que `user`, par l'identifiant d'une ligne qu'il relève lui-même.
 *
 * Lancement : dev/test package=dev file=WhereValue
 */
class WhereValueTest extends \Test {

	private function id(): int {

		$eUser = \user\User::model()
			->select(['id'])
			->sort(['id' => SORT_DESC])
			->get();

		$this->assertTrue($eUser->notEmpty(), "la base de dev porte au moins un utilisateur");

		return $eUser['id'];

	}

	private function find(mixed $value): bool {

		return new \user\UserModel()
			->select(['id'])
			->whereId($value)
			->get()
			->notEmpty();

	}

	public function testEqualAcceptsAnInteger() {

		$id = $this->id();

		$this->assertTrue($this->find($id), "un entier trouve sa ligne");
		$this->assertTrue($this->find((string)$id), "une chaîne de chiffres trouve sa ligne");
		$this->assertTrue($this->find(new \user\User(['id' => $id])), "un élément trouve sa ligne");

	}

	public function testEqualRefusesAMalformedValue() {

		$id = $this->id();

		$this->assertFalse($this->find($id.'abc'), "des lettres après l'identifiant ne trouvent rien");
		$this->assertFalse($this->find(' '.$id), "une espace devant l'identifiant ne trouve rien");
		$this->assertFalse($this->find($id.'e0'), "une notation scientifique ne trouve rien");
		$this->assertFalse($this->find($id."\n"), "un saut de ligne après l'identifiant ne trouve rien");
		$this->assertFalse($this->find([$id]), "un tableau ne trouve rien, et ne lève pas");

	}

	public function testInDropsMalformedValues() {

		$id = $this->id();

		$cUser = new \user\UserModel()
			->select(['id'])
			->whereId('IN', [$id, $id.'abc', ' '.$id])
			->getCollection();

		$this->assertTrue($cUser->count() === 1, "seule la valeur bien formée désigne une ligne");

		$cUser = new \user\UserModel()
			->select(['id'])
			->whereId('IN', [$id.'abc'])
			->getCollection();

		$this->assertTrue($cUser->empty(), "une liste sans valeur bien formée ne trouve rien");

	}

	public function testNotInWithoutWellFormedValueExcludesNothing() {

		$id = $this->id();

		$cUser = new \user\UserModel()
			->select(['id'])
			->whereId($id)
			->whereId('NOT IN', [''])
			->getCollection();

		$this->assertTrue($cUser->count() === 1, "une liste postée vide, explode(',', '') = [''], n'exclut aucune ligne");

		$cUser = new \user\UserModel()
			->select(['id'])
			->whereId($id)
			->whereId('NOT IN', [$id, $id.'abc'])
			->getCollection();

		$this->assertTrue($cUser->empty(), "la valeur bien formée exclut toujours sa ligne");

		$cUser = new \user\UserModel()
			->select(['id'])
			->whereId($id)
			->whereId('NOT IN', [])
			->getCollection();

		$this->assertTrue($cUser->empty(), "une liste vide dès l'appel écarte toutes les lignes, l'appelant la garde par if:");

	}

	public function testStringPropertyIsUntouched() {

		$this->assertTrue(
			\user\User::model()->whereEmail('12abc')->count() === \user\User::model()->where(new \Sql('email = "12abc"'))->count(),
			"une propriété de texte compare la chaîne telle quelle"
		);

	}

}

?>
