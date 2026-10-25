<?php
namespace design;

/**
 * Chaque skill du dépôt est-il nommé dans l'onglet Dev du design system, et cet onglet ne nomme-t-il
 * que des skills qui existent ?
 *
 * Le contrat que personne ne peut rappeler à celui qui l'enfreindra : un skill vit dans
 * `.claude/skills/<nom>/SKILL.md`, que Claude Code ne charge qu'à l'invocation. Une session qui ne
 * sait pas qu'il existe ne l'invoquera jamais, et une personne qui n'écrit pas de skill n'ouvrira
 * jamais ce répertoire. L'onglet Dev (`design/ui/DevAi.u.php`, servi sur
 * design.dev-ouvretaferme.org/?dev) est le seul endroit où la liste se lit d'un trait, donc le seul
 * endroit où un skill neuf atteint quelqu'un qui ne le cherchait pas.
 *
 * Le test compare deux inventaires et échoue en nommant l'écart, dans les deux sens : un skill créé
 * sans sa ligne, et une ligne qui nomme un skill supprimé ou renommé. Il ne lit aucun rendu, la
 * page étant un fichier PHP dont il cherche les mentions `/<nom>`.
 *
 * Lancement : dev/test package=design file=SkillDocumentedSentinelAi
 */
class SkillDocumentedSentinelAiTest extends \Test {

	private const PAGE = 'ouvretaferme/design/ui/DevAi.u.php';

	private const SKILLS = '.claude/skills/*/SKILL.md';

	/**
	 * Les skills que l'onglet ne nomme pas, chacun avec la raison qui l'en dispense.
	 *
	 * La raison compte autant que l'entrée : un trou déclaré se lit, un trou tu se redécouvre le jour
	 * où quelqu'un cherche pourquoi la liste est incomplète.
	 */
	private const ABSENT = [
		// Le conseil des gardiens est un dispositif entre postes, chacun avec son compte et sa
		// machine, et non un geste du chemin d'un lot. Il ne s'invoque que depuis une session gardien,
		// que l'onglet nomme déjà comme rôle. `problematique` est une commande de ce même conseil.
		'conseil',
		'problematique',
	];

	public function testEverySkillIsOnThePage(): void {

		$page = file_get_contents(self::PAGE);

		$missing = [];

		foreach($this->getSkills() as $skill) {

			if(in_array($skill, self::ABSENT)) {
				continue;
			}

			// La page écrit parfois la commande avec son argument (`/tandem maquetteur`) : c'est la
			// mention du nom qui compte, pas la chaîne entière.
			if(preg_match('#/'.preg_quote($skill, '#').'(?![a-z-])#', $page) !== 1) {
				$missing[] = $skill;
			}

		}

		$this->assertEquals([], $missing, 'skills sans ligne dans l\'onglet Dev : '.implode(', ', $missing).$this->explainUntracked());

	}

	public function testThePageNamesNoGhost(): void {

		$page = file_get_contents(self::PAGE);
		$skills = $this->getSkills();

		preg_match_all('#\'/([a-z][a-z-]+)#', $page, $matches);

		$ghosts = array_values(array_unique(array_diff($matches[1], $skills)));

		$this->assertEquals([], $ghosts, 'commandes nommées par l\'onglet Dev sans skill dans .claude/skills : '.implode(', ', $ghosts).$this->explainUntracked());

	}

	public function testDeclarationIsNotStale(): void {

		$skills = $this->getSkills();

		$gone = array_values(array_diff(self::ABSENT, $skills));

		$this->assertEquals([], $gone, 'déclarés absents de la page alors qu\'ils n\'existent plus : '.implode(', ', $gone).$this->explainUntracked());

	}

	/**
	 * Les skills présents sur le disque, suivis ou non.
	 *
	 * Le non suivi se lit exprès : un skill neuf et la ligne de l'onglet qui le nomme s'écrivent
	 * ensemble, dans le même arbre, et la page se lit elle aussi sur le disque. Ne compter que les
	 * skills suivis rendrait fantôme la ligne d'un skill en cours d'écriture. Le verdict peut donc
	 * différer d'un arbre à l'autre, et `explainUntracked()` le dit dans chaque message
	 * (conseil des gardiens, `2026-09-28-rythme-des-tests`, piste 2).
	 *
	 * @return string[]
	 */
	private function getSkills(): array {

		$skills = [];

		foreach(glob(\dev\SentinelLib::getRoot().'/'.self::SKILLS) as $file) {
			$skills[] = basename(dirname($file));
		}

		sort($skills);

		return $skills;

	}

	/**
	 * La fin d'un message d'échec : le motif lu et les skills que git ne suit pas, qui n'existent
	 * que dans cet arbre. Sans git, rien ne se dit non suivi.
	 */
	private function explainUntracked(): string {

		$tracked = \dev\SentinelLib::getTracked();

		if($tracked === NULL) {
			return ' ['.self::SKILLS.', git indisponible : les skills non suivis ne se distinguent pas]';
		}

		$untracked = array_values(array_filter($this->getSkills(), fn(string $skill) => isset($tracked['.claude/skills/'.$skill.'/SKILL.md']) === FALSE));

		return ' ['.self::SKILLS.', '.count($this->getSkills()).' lus dont '.count($untracked).' non suivi(s)'.($untracked === [] ? '' : ' : '.implode(', ', $untracked).', qui n\'existent que dans cet arbre').']';

	}

}
?>
