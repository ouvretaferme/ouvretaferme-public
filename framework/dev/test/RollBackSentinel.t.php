<?php
namespace dev;

/**
 * `Module::rollBack()` ne dépile pas : il remet `self::$dbTransactionStack` à 0 et annule la
 * transaction entière, quel que soit l'étage où on l'appelle (`framework/core/Module.php`,
 * `rollBack()`). Le cadriciel n'a pas de savepoint.
 *
 * Une méthode qui répond à un échec par `rollBack()` puis rend la main sans lever emporte donc la
 * transaction de son appelant. Le `commit()` de celui-ci trouve ensuite la pile à 0 et rend `TRUE`
 * sans rien faire : l'écran annonce un succès sur des lignes qui n'existent plus. Mesuré le
 * 2026-09-26 sur `product\ProductLib::create()`, qui fait `Product::fail('reference.duplicate')`,
 * `rollBack()` puis `return` sur un doublon de référence, sous quatre appelants qui ouvrent leur
 * propre transaction.
 *
 * La règle : après un `rollBack()`, on lève. L'appelant qui tient la transaction décide alors
 * lui-même de l'annuler, et la couche ajax rend l'échec.
 *
 * Pourquoi un cliquet et non un interdit : une méthode qui ouvre elle-même la transaction la plus
 * extérieure, et qu'aucun appelant n'englobe, a le droit de l'annuler sans lever. Rien dans son
 * fichier ne le dit, la réponse étant chez ses appelants, et aucune analyse de jetons ne l'atteint.
 * La déclaration gèle donc l'existant par fichier : un `rollBack()` de plus échoue en nommant sa
 * ligne, ce qui oblige à trancher la question au moment où on l'écrit, sans demander de redresser
 * l'existant. Il y en avait 75 hors cadriciel et hors tests le 2026-09-26, dont 58 que rien ne fait
 * suivre d'un `throw`, par
 * `git ls-files '*.php' | grep -v '^framework/' | grep -v '.t.php$' | xargs grep -c -- '->rollBack()'`.
 *
 * Douze fichiers portaient déjà ce mécanisme en commentaire, chacun l'ayant appris pour son compte
 * (`product/lib/Variant.l.php`, `amap/lib/MembershipRequest.l.php`, `amap/lib/Configuration.l.php`,
 * `selling/lib/CustomerMergeAi.l.php`, `shop/lib/Range.l.php`, `learning/lib/Lesson.l.php`…). C'est
 * ce qui appelle une sentinelle plutôt qu'une phrase de plus : la connaissance existait, elle
 * n'atteignait que celui qui rouvrait le fichier où elle était écrite.
 *
 * Lancement : dev/test package=dev file=RollBackSentinel
 */
class RollBackSentinelTest extends SentinelTest {

	const RULE = 'rollBackWithoutThrow';

	/**
	 * L'existant au 2026-09-26, par fichier et en nombre.
	 *
	 * Ces occurrences ne sont pas toutes des défauts : beaucoup sont des Libs ou des pages qui
	 * ouvrent la transaction la plus extérieure. Elles sont déclarées pour que la suivante se
	 * remarque, et chacune se retire le jour où son fichier est repris.
	 */
	const DECLARED = [
		'accounting/bank/lib/Import.l.php' => 3,
		'accounting/bank/page/cashflow.p.php' => 2,
		'accounting/bank/page/enableBanking.p.php' => 1,
		'accounting/cash/lib/Transaction.l.php' => 1,
		'accounting/financialYear/lib/Document.l.php' => 1,
		// `ResultAllocationLib::deleteProof()`, appelée hors de toute transaction par l'écran d'affectation : le justificatif que ProofLib::delete() refuse rend le détachement, et le refus remonte en fail au `$fw` de l'appelant, qu'une exception levée lui retirerait.
		'accounting/financialYear/lib/ResultAllocation.l.php' => 1,
		'accounting/invoicing/lib/Line.l.php' => 1,
		'accounting/journal/page/operation.p.php' => 2,
		// `ProofLib::cleanStructures()`, la boucle du cron `proof/cron/clean` : aucun appelant n'englobe sa transaction, et une structure en échec n'arrête pas les suivantes.
		'accounting/proof/lib/Proof.l.php' => 1,
		'accounting/vat/lib/Vat.l.php' => 1,
		'base/structure/lib/Community.l.php' => 1,
		'base/structure/lib/Invite.l.php' => 1,
		'base/structure/lib/Member.l.php' => 1,
		'base/structure/lib/Section.l.php' => 1,
		'base/user/lib/SignUp.l.php' => 4,
		'commercialisation/payment/lib/StripeLink.l.php' => 2,
		'commercialisation/payment/lib/Wallet.l.php' => 1,
		'base/product/lib/Csv.l.php' => 1,
		'commercialisation/quotation/page/configure/productAttachAi.p.php' => 1,
		'commercialisation/selling/lib/Csv.l.php' => 1,
		// `closeExpiredCollection()`, la boucle du cron de clôture des marchés échus, appelée par le seul `SaleLib::autoClosing()` : aucun appelant n'englobe sa transaction, et un marché en échec n'arrête pas les suivants. Et `createFromMarket()`, qui ouvre la transaction la plus extérieure de la création en ligne d'une vente de caisse : ses trois appelants (`selling/page/market.p.php`, `selling/api/MarketSale_0_1beta.a.php`, le script de recette `marketCloseRecetteAi`) l'appellent hors transaction, et le refus d'un marché clos entre-temps se rend en fail, que la page et l'API affichent, ce qu'une exception levée leur retirerait. Un quatrième appelant qui ouvrirait une transaction autour la perdrait sans erreur. Son second rollBack() défait, dans cette même transaction, la vente dont le marché a été clos ou l'appareil rendu pendant l'envoi, ou dont le calcul relu en base ne rend pas celui que l'appareil a scellé : le refus se rend après elle, en mise de côté ou en fail. Et `resumeFromMarket()`, qui ouvre la transaction la plus extérieure de la reprise d'une vente mise de côté, la vente refusée sous verrou (marché qui ne vend plus, mise de côté déjà traitée, calcul relu qui ne rend pas le contenu) se défait, et le refus se rend en fail après elle, ce qu'une exception levée retirerait à la page du gérant. Un appelant qui ouvrirait une transaction autour la perdrait sans erreur, comme pour `createFromMarket()`.
		// Et `createNotPaidFromMarketDevice()`, qui ouvre la transaction la plus extérieure du différé envoyé par l'appareil, appelée hors transaction par la seule `selling/page/marketDevice.p.php` : la vente dont le total relu ne rend pas celui du vendeur, dont le marché a été clos ou l'appareil rendu pendant l'envoi, ou que dissociateMarketSale() n'a pas sortie du marché, se défait, et le refus se rend en fail après elle, ce qu'une exception levée retirerait à la caisse.
		'commercialisation/selling/lib/Market.l.php' => 5,
		// `failRetry()`, sous `writeOrFail()` : un interblocage a déjà fait défaire par MySQL toute la transaction, celle de l'appelant comprise, et rollBack() ne fait que remettre la pile du cadriciel d'accord avec lui. Le refus `retry` se rend en fail, que la page de la caisse affiche et que l'API traduit en 409, ce qu'une exception levée retirerait à la page.
		'commercialisation/selling/lib/MarketDevice.l.php' => 1,
		// `create()`, sur l'insertion perdue au doublon (marketDevice, marketSequence) d'un envoi d'appareil reçu deux fois : ce n'est pas une erreur, et `MarketLib::createFromMarket()`, qui ouvre la transaction la plus extérieure, relit la vente de l'autre envoi hors de toute transaction.
		'commercialisation/selling/lib/Sale.l.php' => 1,
		// Dont `start()`, qui ouvre la transaction la plus extérieure de `/shop/:doStart`, et dont le refus du mode perdrait son wrapper de tuile si on levait.
		'commercialisation/shop/lib/Shop.l.php' => 3,
		'ouvretaferme/analyze/lib/Report.l.php' => 1,
		'ouvretaferme/game/lib/Player.l.php' => 2,
		'ouvretaferme/subscription/lib/Subscription.l.php' => 2,
		'ouvretaferme/website/lib/Webpage.l.php' => 1,
		'ouvretaferme/website/lib/Website.l.php' => 1,
		'production/sequence/lib/Crop.l.php' => 2,
		'production/series/lib/Cultivation.l.php' => 2,
		'production/series/lib/Place.l.php' => 2,
		'production/series/lib/Series.l.php' => 2,
		'production/series/lib/Timesheet.l.php' => 2,
		'production/work/lib/Category.l.php' => 1,
	];

	public function init(): void {

		foreach($this->getPhpTokens() as $file => $tokens) {

			// Le cadriciel s'exclut : `queue/lib/Task.l.php` annule volontairement la transaction du
			// travail qu'il exécute, étant le point le plus extérieur de cette exécution.
			// Les tests aussi, et pour la même raison portée à leur échelle : un test qui annule sa
			// transaction en fin de scénario détient la plus extérieure par construction, aucun
			// appelant ne l'englobant. Ils sont 103 à le faire, ce qui noierait la règle.
			if(str_starts_with($file, 'framework/') or str_ends_with($file, '.t.php')) {
				continue;
			}

			$this->addFound(self::RULE, $file, $this->scan($tokens));

		}

	}

	/**
	 * Les lignes des `->rollBack()` que le bloc qui les contient ne fait pas suivre d'un `throw`.
	 *
	 * @return int[]
	 */
	private function scan(array $tokens): array {

		$lines = [];

		foreach($tokens as $index => $token) {

			if($token[0] !== T_STRING or $token[1] !== 'rollBack') {
				continue;
			}

			// Un appel de méthode et non une définition ni un homonyme : la flèche d'objet avant,
			// la parenthèse d'appel après.
			if(($tokens[$index - 1][0] ?? NULL) !== T_OBJECT_OPERATOR or ($tokens[$index + 1][1] ?? NULL) !== '(') {
				continue;
			}

			if(self::isOnKo($tokens, $index) === FALSE and self::throwsAfter($tokens, $index) === FALSE) {
				$lines[] = $token[2];
			}

		}

		return $lines;

	}

	/**
	 * L'appel est dans le `onKo:` d'un `$fw->validate()`, qui lève toujours.
	 *
	 * `FailWatch::validate()` appelle `onKo` puis `throw new FailAction()` sans condition
	 * (`framework/core/Fail.php:298`). Le `throw` qui couvre l'appel est donc dans le cadriciel et
	 * non dans le bloc de l'appelant : sans cette reconnaissance, la forme recommandée par le
	 * `CLAUDE.md` pour une réponse sur mesure serait relevée comme une dette, ce qu'elle n'est pas.
	 */
	private static function isOnKo(array $tokens, int $index): bool {

		for($previous = $index - 1; $previous >= 0; $previous--) {

			$text = $tokens[$previous][1];

			// Début d'instruction : au-delà, un `onKo` appartiendrait à un appel voisin.
			if($text === ';' or $text === '{' or $text === '}') {
				return FALSE;
			}

			if($tokens[$previous][0] === T_STRING and $text === 'onKo' and ($tokens[$previous + 1][1] ?? NULL) === ':') {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Un `throw` entre l'appel et la fermeture du bloc qui le contient.
	 *
	 * La portée retenue est ce bloc et non la méthode entière : au-delà, on lirait le `throw` d'une
	 * branche voisine, qui ne protège pas celle qui vient d'annuler. Un `throw` rencontré plus
	 * profond couvre l'appel, puisqu'il quitte la méthode sur le chemin qui le traverse.
	 */
	private static function throwsAfter(array $tokens, int $index): bool {

		$depth = 0;
		$count = count($tokens);

		for($next = $index + 1; $next < $count; $next++) {

			if($tokens[$next][0] === T_THROW) {
				return TRUE;
			}

			$text = $tokens[$next][1];

			if($text === '{') {
				$depth++;
			} else if($text === '}') {

				$depth--;

				if($depth < 0) {
					return FALSE;
				}

			}

		}

		return FALSE;

	}

	public function testScannerFindsRollBack(): void {

		$tokens = SentinelLib::getTokens(<<<'PHP'
			<?php
			class Fixture {
				public static function silent(): void {
					try {
						parent::create($e);
					}
					catch(\DuplicateException) {
						Product::fail('reference.duplicate');
						Product::model()->rollBack();
						return;
					}
				}
				public static function loud(): void {
					catch(\DuplicateException) {
						Product::model()->rollBack();
						throw new \FailAction();
					}
				}
				public static function deeper(): void {
					if($ko) {
						Product::model()->rollBack();
						if($detail) {
							throw new \FailAction();
						}
					}
				}
				public static function onKo(): void {
					$fw->validate(
						onOk: fn() => MembershipRequest::model()->commit(),
						onKo: fn() => MembershipRequest::model()->rollBack()
					);
				}
				public static function rollBack(): void {
					$this->commit();
				}
			}
			PHP);

		// Seul le premier est relevé. Le deuxième lève dans son bloc, le troisième lève plus profond
		// sur le chemin qui le traverse, le quatrième est dans un `onKo:` que `validate()` fait
		// toujours suivre d'un `throw`, et la définition homonyme n'est pas un appel.
		$this->assertEquals([9], $this->scan($tokens));

	}

	public function testRollBackThrows(): void {
		$this->assertRatchet(self::RULE, self::DECLARED, 'rollBack() sans throw derrière, qui emporte la transaction de l\'appelant');
	}

}
