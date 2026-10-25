<?php
namespace learning;

/**
 * L'espace insécable devant « ? » et « ! » d'une question de FAQ (lot T10), posée par l'étape
 * `Faq::question.nonBreakingSpace` de `build()`, que l'écran et l'API empruntent tous deux. Le
 * passage par l'API est éprouvé dans `FaqApi.t.php`.
 *
 * Les questions sont construites en mémoire : aucune lecture ni écriture en base.
 *
 * Lancement : dev/test package=learning file=FaqNonBreakingSpace
 */
class FaqNonBreakingSpaceTest extends \Test {

	const NBSP = "\u{00A0}";

	/**
	 * L'espace devant le signe devient insécable, qu'elle soit ordinaire, absente, multiple ou fine.
	 */
	public function testQuestion(): void {

		$cases = [
			'Comment exporter mes données ?' => 'Comment exporter mes données'.self::NBSP.'?',
			'Comment exporter mes données?' => 'Comment exporter mes données'.self::NBSP.'?',
			'Comment exporter mes données   ?' => 'Comment exporter mes données'.self::NBSP.'?',
			"Comment exporter mes données\t?" => 'Comment exporter mes données'.self::NBSP.'?',
			"Comment exporter mes données\u{202F}?" => 'Comment exporter mes données'.self::NBSP.'?',
			'Ça ne marche pas !' => 'Ça ne marche pas'.self::NBSP.'!',
			'Ça ne marche pas!' => 'Ça ne marche pas'.self::NBSP.'!',
			'Vraiment ?!' => 'Vraiment'.self::NBSP.'?!',
			'Vraiment!!!' => 'Vraiment'.self::NBSP.'!!!',
			'Pourquoi ? Et comment ?' => 'Pourquoi'.self::NBSP.'? Et comment'.self::NBSP.'?',
			'Le mot « quoi ? » dans le menu ?' => 'Le mot « quoi'.self::NBSP.'? » dans le menu'.self::NBSP.'?',
			'Pourquoi (vraiment?) le rendement baisse ?' => 'Pourquoi (vraiment'.self::NBSP.'?) le rendement baisse'.self::NBSP.'?',
			'Que signifie "brouillon" ?' => 'Que signifie "brouillon"'.self::NBSP.'?',
			'Que signifie \'brouillon\'?' => 'Que signifie \'brouillon\''.self::NBSP.'?',
			'Que signifie “brouillon” ?' => 'Que signifie “brouillon”'.self::NBSP.'?',
		];

		foreach($cases as $input => $expected) {
			$this->assertEquals($expected, $this->buildQuestion($input), json_encode($input, JSON_UNESCAPED_UNICODE));
		}

	}

	/**
	 * Un signe qui ne termine pas une phrase reste collé : la requête d'une adresse, un signe ouvert
	 * par une parenthèse, une question qui commence par le signe.
	 */
	public function testUntouched(): void {

		$cases = [
			'Que veut dire /ferme/1/especes?tab=2 dans l\'adresse',
			'Que veut dire le (?) à côté du titre',
			'?',
			'Pourquoi la surface inclut-elle les passe-pieds',
		];

		foreach($cases as $input) {
			$this->assertEquals($input, $this->buildQuestion($input), json_encode($input, JSON_UNESCAPED_UNICODE));
		}

	}

	/**
	 * Une question déjà typographiée ressort à l'identique : la reprise se rejoue sans rien changer.
	 */
	public function testIdempotent(): void {

		$question = 'Pourquoi'.self::NBSP.'? Et comment'.self::NBSP.'?!';

		$this->assertEquals($question, $this->buildQuestion($question));
		$this->assertEquals($question, $this->buildQuestion($this->buildQuestion('Pourquoi? Et comment ?!')));

	}

	/**
	 * L'étape précède le contrôle du schéma : une question de 250 caractères sans espace devant son
	 * signe en compte 251, et le schéma la refuse plutôt que d'écrire une colonne trop longue.
	 */
	public function testLength(): void {

		$max = Faq::model()->getPropertyRange('question')[1];

		$accepted = str_repeat('a', $max - 2).' ?';
		$this->assertEquals(str_repeat('a', $max - 2).self::NBSP.'?', $this->buildQuestion($accepted), 'remplacer une espace ne change pas la longueur');

		$fw = new \FailWatch();

		$eFaq = new Faq();
		$eFaq->build(['question'], ['question' => str_repeat('a', $max - 1).'?']);

		$this->assertTrue($fw->has('Faq::question.check'), 'une espace ajoutée qui dépasse la borne est refusée : '.json_encode($fw->get()));

	}

	private function buildQuestion(string $question): ?string {

		$fw = new \FailWatch();

		$eFaq = new Faq();
		$eFaq->build(['question'], ['question' => $question]);

		$this->assertTrue($fw->ok(), 'question acceptée : '.json_encode($fw->get(), JSON_UNESCAPED_UNICODE));

		return $eFaq['question'];

	}

}
?>
