<?php
namespace learning;

/**
 * Une question de la FAQ d'un réseau ou d'un groupe de formation, écrite par son équipe, à l'écran
 * ou par l'API, et lue par tous, visiteur compris, une fois publiée.
 *
 * Elle peut renvoyer à un module d'une formation de la même structure (`chapter`). Ce module n'en
 * devient pas lisible pour autant : sa formation garde ses propres droits.
 */
class Faq extends FaqElement {

	public static function getSelection(): array {

		return parent::getSelection() + [
			'community' => ['name', 'type', 'communityType'],
			'chapter' => Chapter::getSelection(),
		];

	}

	/**
	 * La FAQ est publique : ce qui ferme la lecture est l'état de la question et de sa structure
	 * (`acceptRead()`), et son équipe relit ses brouillons par `assertRead()`.
	 */
	public function canRead(): bool {
		return TRUE;
	}

	public function canWrite(): bool {

		$this->expects(['community']);

		return $this['community']->getMember()->isStaff();

	}

	public function acceptRead(): bool {

		$this->expects(['community', 'status']);

		return (
			$this['community']->acceptLearning() and
			$this['status'] === Faq::PUBLISHED
		);

	}

	/**
	 * Sans lui, l'écriture retomberait sur `acceptRead()` et fermerait les brouillons à leur équipe.
	 */
	public function acceptWrite(): bool {

		$this->expects(['community']);

		return (
			$this['community']->acceptLearning() and (
				$this['community']->isEducation() or
				$this['community']->isNetwork()
			)
		);

	}

	public function assertRead(): bool {

		return (
			$this->assertWrite() or
			parent::assertRead()
		);

	}

	/**
	 * La base ne porte que le chemin dans la démo : son hôte change d'un environnement à l'autre
	 * (`OTF_DEMO_URL`), comme pour `Structure::DEMO_URL`.
	 */
	public function getDemoUrl(): string {

		$this->expects(['demoPage']);

		return OTF_DEMO_URL.$this['demoPage'];

	}

	public static function properties(\PropertyList $l): void {

		// Typographie française : une espace insécable, et une seule, devant « ? » et « ! », pour que le
		// signe ne parte pas seul à la ligne dans le titre de la question. Le caractère est U+00A0, celui
		// du `&nbsp;` que `TextUi::beautify()` pose au même endroit. Seul un signe qui termine une phrase
		// est concerné : suivi d'une espace, d'un autre signe, d'une ponctuation fermante ou de la fin, ce
		// qui laisse collé le `?` d'une adresse (`/especes?tab=2`), et précédé d'autre chose qu'un ouvrant
		// sans ambiguïté, ce qui laisse collé « (?) ». Un guillemet droit ouvre ou ferme selon la phrase :
		// il compte comme un mot, et `"brouillon" ?` reçoit son espace. L'étape suit le cast, qui a rogné
		// la chaîne, et précède le contrôle du schéma, qui refuse une question allongée au-delà de sa borne.
		$l->property('question')
			->addAfterCast('nonBreakingSpace', function(?string &$question): bool {

				if($question !== NULL) {
					$question = preg_replace('/(?<=[^\h\v?!(\[«“])\h*(?=[?!]+(?:$|[\h\v»)\]"\'”’.,;:…]))/u', "\u{00A0}", $question);
				}

				return TRUE;

			});

		// Le module posté ne porte que son id : sa structure se lit dans `cChapter`, les modules de
		// toutes les formations de la structure de la question.
		$l->property('chapter')
			->addAfterCheck('owned', function(Chapter $eChapter): bool {

				if($eChapter->empty()) {
					return TRUE;
				}

				$this->expects(['cChapter']);

				return $this['cChapter']->offsetExists($eChapter['id']);

			});

		// `getDemoUrl()` colle le chemin derrière l'hôte de la démo : un chemin qui commencerait par
		// autre chose qu'un seul `/` (`//autre.site`, `/\autre.site`, que les navigateurs lisent comme
		// un hôte, ou `javascript:`) mènerait ailleurs que sur la démo. Le chemin finit dans un attribut
		// `href` : ni blanc, ni guillemet, ni chevron, ni octet nul, qui le couperaient.
		$l->property('demoPage')
			->addAfterCheck('relative', function(?string $demoPage): bool {

				return (
					$demoPage === NULL or
					preg_match('#^/([^/\\\\\s"<>\x00][^\s"<>\x00]*)?$#', $demoPage) === 1
				);

			});

		// L'emplacement dans le menu décrit la page de la démo, il ne se montre jamais sans son lien.
		// `getStandalonePropertiesUpdate()` de l'API construit les deux ensemble.
		$l->property('demoPath')
			->addAfterCheck('withPage', function(?string $demoPath, \PropertyList $l): bool {

				if(
					$demoPath === NULL or
					$l->isBuilt('demoPage') === FALSE
				) {
					return TRUE;
				}

				return $this['demoPage'] !== NULL;

			});

	}

}
?>
