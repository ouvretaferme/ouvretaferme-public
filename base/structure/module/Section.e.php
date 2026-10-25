<?php
namespace structure;

class Section extends SectionElement {

	/**
	 * Lire un sous-groupe, c'est lire le groupe : le sélecteur qui range la liste des membres
	 * s'affiche à tous ceux qui voient cette liste, et non aux seuls administrateurs.
	 */
	public function canRead(): bool {

		$this->expects(['structure']);
		return $this['structure']->canRead();

	}

	public function canWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->canManage();

	}

	/**
	 * Un sous-groupe n'a de sens que dans un groupe qui accepte des fermes. La règle est un état,
	 * donc un accept* : rangée dans canWrite(), l'API ne la verrait pas, ModuleApi n'appelant que
	 * les accept* (framework/core/Api.php).
	 */
	public function acceptWrite(): bool {

		$this->expects(['structure']);
		return $this['structure']->acceptGroupStructure();

	}

	/**
	 * Le POST est contournable : une case forgée rangerait dans ce sous-groupe quelqu'un qui n'est
	 * pas du groupe, et aucune contrainte de la base ne l'attraperait, les ids vivant dans une
	 * colonne json. Le contrôle est ici et non dans la page pour que l'API l'emprunte aussi,
	 * build() étant le point de passage des deux.
	 *
	 * `getColumn('user')` rend une Collection et non un tableau, `user` étant une propriété
	 * déléguée : c'est `getIds()` qui en tire les ids (cf. les conventions ORM).
	 *
	 * Sous le suffixe `.prepare` et non `.check` : un callback nommé `<propriété>.check` remplace
	 * celui du cadriciel au lieu de s'y ajouter (framework/core/Data.php).
	 */
	public static function properties(\PropertyList $l): void {

		$l->property('users')
			->addAfterCast('members', function(mixed &$value): bool {

				$this->expects(['structure']);

				$users = Member::model()
					->whereStructure($this['structure'])
					->whereStatus(Member::IN)
					->getColumn('user')
					->getIds();

				$value = array_values(array_intersect(array_map('intval', (array)$value), $users));

				return TRUE;

			});

	}

	/**
	 * Le fragment d'URL qui pose ce sous-groupe comme destination d'une invitation, ou rien.
	 *
	 * Sur l'élément et non dans l'UI qui compose le lien : c'est le nom du paramètre attendu par
	 * `page/invite.p.php`, et les deux doivent bouger ensemble.
	 */
	public function getInviteArgument(): string {
		return $this->empty() ? '' : '&memberSection='.$this['id'];
	}

	public function isClosed(): bool {
		return $this['status'] === Section::CLOSED;
	}

}
?>
