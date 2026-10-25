<?php
namespace dev;

/**
 * Un fichier suivi par git cite un chemin que ses lecteurs peuvent ouvrir. `.claude/state/` est
 * gitignoré par construction (`.gitignore`, ligne 4) : une citation concrète de ce répertoire ne
 * résout donc jamais sur le poste d'un autre membre de l'équipe, en production, sur les autres postes, ni chez la session qui
 * reprendra le fichier une fois le dossier du chantier nettoyé. Le lecteur y perd la raison de ce
 * qu'il lit, et il n'a aucun moyen de savoir que la référence était morte à l'écriture.
 *
 * Le cas coûte le plus cher dans `migration/`, où la citation porte une instruction : un script à
 * remettre en place, la mesure qui justifie une reprise, le récapitulatif du chantier. Celui qui
 * tire lit un geste qu'il ne peut pas faire.
 *
 * Ce qui reste légitime, et que la sentinelle laisse passer, c'est le chemin qui **décrit la
 * convention** au lieu de désigner un fichier : il porte alors un segment entre chevrons
 * (`.claude/state/<chantier>/recap.html`). Le discriminateur est formel, donc mécanique : un
 * placeholder ne se résout pour personne et ne promet rien ; un chemin concret promet un fichier.
 *
 * **Une occurrence se tranche par une question, pas par un réflexe : ce que la phrase appuie
 * existe-t-il ailleurs que sous ce chemin ?** Si oui, la citation double ce que le fichier porte
 * déjà et se retire, la mesure ou la décision restant sur place, dans le fichier de `migration/`,
 * dans un `CLAUDE.md` de package, dans le message de commit ou dans le commentaire lui-même. Si non,
 * la citation est le seul support de quelque chose que git n'a pas : elle dit au lecteur qu'il existe
 * et où le demander, elle se garde, et son entrée en `CITED` est définitive.
 *
 * C'est la règle que le `CLAUDE.md` racine pose déjà pour lui seul, « une entrée de ce fichier cite
 * du code suivi par git », étendue à tout ce que git transporte, avec l'exception que sa formulation
 * ne portait pas. Les deux temps ont été relevés par E-Dashboard le 2026-09-30 sur ses quatre
 * entrées, toutes du premier cas : trois portent leur mesure en clair sur place, la quatrième cite un
 * support intransportable pour une règle que `origin/main:CLAUDE.md:19` porte mot pour mot.
 *
 * Ce que la sentinelle ne voit pas : un chemin assemblé par concaténation, un chemin cité sans son
 * préfixe `.claude/` (« le plan du lot 3 »), et un chemin vers un autre répertoire gitignoré.
 *
 * Elle ne lit pas `.claude/` (`SentinelLib::EXCLUDED`), où les skills et `cas-vus/` décrivent le
 * mécanisme, et pas les sentinelles, dont les fixtures portent volontairement la forme fautive.
 *
 * Lancement : dev/test package=dev file=StatePathSentinel
 */
class StatePathSentinelTest extends SentinelTest {

	const RULE = 'statePath';

	/**
	 * La dette au 2026-09-30 : 77 citations dans 75 fichiers, toutes de la même forme, un en-tête de
	 * fichier ou un commentaire qui renvoie au plan, à la spécification ou à la planche du chantier
	 * qui l'a fait écrire. Aucune n'est reprise ailleurs, donc chacune se résorbe en portant ce
	 * qu'elle appuie dans un endroit que git transporte, pas en effaçant la phrase.
	 *
	 * ⚠️ **Cette déclaration se mesure sur `origin/main`, jamais sur l'arbre de travail.** Elle voyage
	 * par git ; un fichier encore dans l'arbre, non. Une entrée qui nomme un fichier que l'amont ne
	 * porte pas est verte chez son auteur et **périmée chez tous ceux qui tirent**, avec un message qui
	 * les envoie corriger une déclaration juste. Une preuve dans les deux sens ne l'attrape pas : elle
	 * éprouve la sentinelle contre l'arbre qui l'a écrite. Six entrées ont été retirées pour cette
	 * raison le 2026-09-30 (relevé par E-Dashboard, rejoué des deux côtés) : quatre du lot 2 du tableau
	 * de bord, deux d'un chantier amap. Elles s'ajouteront avec les hunks qui les rendent vraies, chez
	 * leur auteur, comme toute déclaration de sentinelle. Le contrôle est une commande, pas une
	 * relecture : `git cat-file -e origin/main:<chemin>` sur chaque entrée **déjà déclarée**.
	 *
	 * ⚠️ **Ce contrôle ne s'applique jamais à l'entrée qu'on ajoute.** Elle part dans le même commit que
	 * son fichier, donc `cat-file` échoue forcément sur elle au moment de la coupe, et ce zéro se lit
	 * comme « déclaration périmée, retire l'entrée », l'inverse du geste à faire. Ce qui fait foi pour
	 * une entrée neuve est que son chemin soit parmi les fichiers du commit, vérifié sur le commit et
	 * non sur l'amont. Sans cette phrase, le prochain lot qui ajoute une entrée la retire aussitôt,
	 * seul et de bonne foi (relevé par E-AMAP le 2026-09-30).
	 *
	 * ⚠️ **Tant que ce fichier est absent de l'amont, une coupe l'emporte entier**, et c'est l'outil qui
	 * l'impose : `.claude/push/coupe.py:291` refuse les numéros de hunks sur un fichier neuf, donc la
	 * sélection n'est pas offerte et le pousseur prend toutes les entrées ou aucune. Un pousseur qui
	 * emporte la version de l'arbre emporte les entrées des autres chantiers, dont les fichiers ne sont
	 * pas dans son push, et rend la sentinelle rouge chez tous ceux qui tirent, en son nom. C'est l'autre
	 * moitié du même défaut, vue du pousseur au lieu du lecteur. Une fois le fichier dans l'amont, `base()`
	 * le trouve dans la révision (`:164`) et la coupe redevient une modification à hunks : chacun n'emporte
	 * alors que ses entrées, et joue le contrôle ci-dessus **au moment de la coupe**, l'amont bougeant
	 * entre-temps. Le premier push de ce fichier passe donc avant les lots qui y ajoutent une entrée,
	 * ce que `:326` nomme déjà pour le cas symétrique (relevé par E-Dashboard le 2026-09-30, lignes
	 * rejouées des deux côtés).
	 *
	 * ⚠️ **Toute occurrence n'est pas une dette, et le test porte sur ce que la phrase attend du chemin,
	 * pas sur ce que le chemin contient.** Une citation dont la charge est sur place, un chiffre, une
	 * date, un résultat écrit dans la même phrase, n'apporte que sa provenance : elle promet à qui tire
	 * un fichier qu'il n'aura jamais, et elle se retire en gardant ce qui rend la mesure rejouable. Une
	 * citation dont la charge est **actionnable et n'existe nulle part ailleurs** est l'information
	 * elle-même : `migration/2026-09-25-amap-saison.md:7` nomme un script jamais commité
	 * (`git log --all -- '*e2SaisonRecette*'` ne rend rien le 2026-09-30), que `.claude/state/` est seul
	 * à porter, et la retirer retirerait l'information et non son support. Le chemin gitignoré est alors
	 * la bonne réponse et non le symptôme, l'entrée est sa place définitive, et elle ne baissera pas
	 * (relevé par E-AMAP le 2026-09-30, critère resserré par E-Dashboard le même jour sur une citation
	 * dont les quatre chiffres étaient en gras dans la phrase qui citait). Le cliquet ne dit donc pas
	 * « à corriger », il dit « compté » : ce qui doit rester se déclare et s'explique, comme partout
	 * ailleurs dans les sentinelles.
	 *
	 * Les trois de `migration/` sont les seules qui portent une instruction, et se traitent d'abord :
	 * un script à remettre en place, la mesure qui justifie une reprise, le récapitulatif du chantier.
	 */
	const CITED = [
		'accounting/account/lib/SaleBreakdownAi.l.php' => 1,
		'accounting/cash/test/CardRegisterWaitAi.t.php' => 1,
		'accounting/cash/test/ImportRawAi.t.php' => 1,
		'accounting/cash/test/RegisterMethodInactiveAi.t.php' => 1,
		'accounting/cash/test/RegisterPaymentMethodAi.t.php' => 1,
		'accounting/cash/test/StripePlatformAi.t.php' => 1,
		'accounting/cash/test/SumupCardFallbackAi.t.php' => 1,
		'accounting/cash/test/SumupClosingAi.t.php' => 1,
		'accounting/cash/test/SumupFixtureAi.t.php' => 1,
		'accounting/cash/test/SumupImportAi.t.php' => 1,
		'accounting/cash/test/SumupLinkBalanceAi.t.php' => 1,
		'accounting/cash/test/SumupMarketAi.t.php' => 1,
		'accounting/cash/test/SumupMemorizeAi.t.php' => 1,
		'accounting/cash/test/SumupPayoutAi.t.php' => 1,
		'accounting/cash/test/SumupPointAfterPaidAi.t.php' => 1,
		'accounting/cash/test/SumupPointingAi.t.php' => 1,
		'accounting/cash/test/SumupPromisedAi.t.php' => 1,
		'accounting/cash/test/SumupReconciliationCancelAi.t.php' => 1,
		'accounting/cash/test/SumupReconciliationTimeAi.t.php' => 1,
		'accounting/cash/test/SumupSignAi.t.php' => 1,
		'accounting/cash/test/SumupUnlinkedStateAi.t.php' => 1,
		'accounting/company/ui/OnboardingAi.u.php' => 1,
		'accounting/financialYear/lib/ClosingSaleBreakdownAi.l.php' => 1,
		'accounting/preaccounting/test/ProductAccountAi.t.php' => 1,
		'accounting/taxReturn/lib/Declaration.l.php' => 1,
		'accounting/taxReturn/lib/FixedAsset.l.php' => 1,
		'accounting/taxReturn/lib/Provision.l.php' => 1,
		'accounting/taxReturn/lib/Result.l.php' => 1,
		'accounting/taxReturn/lib/Share.l.php' => 1,
		'accounting/taxReturn/lib/TaxReturn.l.php' => 1,
		'accounting/taxReturn/page/index.p.php' => 2,
		'accounting/taxReturn/test/Declaration.t.php' => 1,
		'accounting/taxReturn/test/Share.t.php' => 1,
		'accounting/taxReturn/ui/Control.u.php' => 1,
		'accounting/taxReturn/ui/Declaration.u.php' => 1,
		'accounting/taxReturn/ui/FixedAsset.u.php' => 1,
		'accounting/taxReturn/ui/Line.u.php' => 1,
		'accounting/taxReturn/ui/Provision.u.php' => 1,
		'accounting/taxReturn/ui/Result.u.php' => 1,
		'accounting/taxReturn/ui/Share.u.php' => 1,
		'accounting/taxReturn/ui/TaxReturn.u.php' => 1,
		'base/structure/test/ProfileAi.t.php' => 1,
		'base/structure/ui/CommunityStartAi.u.php' => 1,
		'base/user/lib/ChallengeAi.l.php' => 1,
		'base/user/ui/ChallengeAi.u.php' => 1,
		'commercialisation/payment/lib/SumupAi.l.php' => 1,
		'commercialisation/payment/lib/SumupPointingAi.l.php' => 1,
		'commercialisation/payment/test/SumupAi.t.php' => 2,
		'commercialisation/payment/test/SumupBalanceReliableAi.t.php' => 1,
		'commercialisation/payment/test/SumupOauthStateAi.t.php' => 1,
		'base/product/test/CsvAi.t.php' => 1,
		'commercialisation/catalog/test/OfferWebAi.t.php' => 1,
		'base/product/test/ProductWebAi.t.php' => 1,
		'base/product/test/StockAi.t.php' => 1,
		'commercialisation/quotation/test/CatalogCronAi.t.php' => 1,
		'commercialisation/selling/test/CsvExportAi.t.php' => 1,
		'commercialisation/selling/test/ItemSearchAi.t.php' => 1,
		'commercialisation/selling/test/PdfAi.t.php' => 1,
		'commercialisation/selling/test/SummaryAi.t.php' => 1,
		'commercialisation/shop/test/PublicAi.t.php' => 1,
		'commercialisation/shop/test/ShopDeckListAi.t.php' => 1,
		'commercialisation/shop/ui/OpenAi.u.php' => 1,
		'commercialisation/shop/ui/StartAi.u.php' => 1,
		'framework/dev/test/SubQueryAi.t.php' => 1,
		'internal/aggregate/lib/CompositionSourceAi.l.php' => 1,
		'migration/2026-09-25-amap-saison.md' => 1,
		'migration/2026-09-27-cahiers-448-reprise-stripe.md' => 1,
		'ouvretaferme/association/test/MembershipProductAi.t.php' => 1,
		'ouvretaferme/learning/ui/DatePublic.u.php' => 1,
		'ouvretaferme/main/ui/Design.u.php' => 1,
		'ouvretaferme/presentation/ui/TrainingAi.u.php' => 1,
		'production/toolbox/lib/Composition.l.php' => 1,
		'production/toolbox/test/CompositionRule.t.php' => 1,
		'production/toolbox/ui/Composition.u.php' => 1,
	];

	public function init() {

		foreach(['.php', '.md'] as $suffix) {

			foreach(SentinelLib::getFiles($suffix) as $file => $path) {

				if(fnmatch('*Sentinel*.t.php', basename($file))) {
					continue;
				}

				$this->addFound(self::RULE, $file, $this->findStatePaths(file_get_contents($path)));

			}

		}

	}

	/** @return int[] */
	private function findStatePaths(string $contents): array {

		$lines = [];

		foreach(explode("\n", $contents) as $position => $line) {

			foreach(self::getPaths($line) as $cited) {

				if(str_contains($cited, '<') === FALSE) {
					$lines[] = $position + 1;
					break;
				}

			}

		}

		return $lines;

	}

	/**
	 * Les chemins de `.claude/state/` d'une ligne, avec leurs éventuels chevrons.
	 *
	 * @return string[]
	 */
	private static function getPaths(string $line): array {

		preg_match_all('#\.claude/state/[\w./<>-]+#', $line, $matches);

		return $matches[0];

	}

	public function testDetector(): void {

		$contents = <<<'TEXTE'
			Le chantier est dans `.claude/state/cahiers-448/recap.html`.
			Le récapitulatif vit dans `.claude/state/<chantier>/recap.html`, sans date.
			Le plan du lot 3 tranche la question.
			Voir .claude/state/amap/E2-saison/outils/e2SaisonRecette.p.php, à remettre en place.
			Un dossier seul : .claude/state/dashboard/ et rien de plus.
			TEXTE;

		$this->assertEquals([1, 4, 5], $this->findStatePaths($contents), 'citation concrète de .claude/state');
		$this->assertEquals(['.claude/state/<chantier>/recap.html'], self::getPaths('`.claude/state/<chantier>/recap.html`'), 'le placeholder est bien lu, et écarté ensuite');

	}

	public function testCitedState(): void {
		$this->assertRatchet(self::RULE, self::CITED, 'chemin de .claude/state cité par un fichier que git transporte, illisible pour qui tire');
	}

}
?>
