<?php
namespace dev;

/**
 * Les routes qui créent, et parmi elles celles qu'un second envoi du même POST crée deux fois.
 *
 * Une requête qui finit sans réponse lisible laisse l'utilisateur sans savoir si sa demande est
 * passée. Celui qui recommence, ou clique deux fois, envoie un second POST, et seul le serveur peut
 * alors refuser le doublon : une contrainte UNIQUE, ou un contrôle écrit dans le module. Cette
 * sentinelle tient la liste des routes qui n'en ont pas, sur laquelle se décide une garde commune
 * du cadriciel.
 *
 * Deux règles :
 * - `create` compte par fichier les appels qui créent : `->doCreate(`, une route `doCreate*`, et un
 *   `XxxLib::create*(` ou `XxxLib::duplicate(` écrit dans une page. Le compte ne peut que baisser ;
 *   une création de plus se déclare dans CREATE.
 * - `unguarded` nomme une à une les routes qui créent sans contrainte UNIQUE qui les garde : un
 *   `->doCreate(` dont aucune contrainte du module ne porte que des colonnes postées ou fixées par
 *   la structure, et non nullables ; une route `doCreate*` d'une page ordinaire ; et toute route
 *   déjà déclarée, qui reste suivie tant qu'elle existe. Chacune se déclare dans UNGUARDED avec la
 *   garde serveur qui empêche le doublon, citée par son fichier et sa ligne, ou « aucune ».
 *
 * Ce qu'elle ne voit pas : une création appelée sous un autre nom que `create*` ou `duplicate`, ou
 * par une méthode d'un Lib que la page appelle sans nommer la création, et le receveur d'un
 * `->doCreate(` tenu dans une variable, qu'elle prend pour la dernière page construite du fichier.
 * Des propriétés de création données par une closure ne se lisent pas : la route est tenue pour non
 * gardée.
 *
 * Lancement : dev/test package=dev file=CreateOnceSentinel
 */
class CreateOnceSentinelTest extends SentinelTest {

	/**
	 * Les routes `->doCreate(` sans UNIQUE sur leurs propriétés de création, et les créations de
	 * OUTSIDE, chacune avec la garde serveur qui empêche un second envoi de créer deux fois.
	 */
	const UNGUARDED = [
		'accounting/account/page/index.p.php:doCreate' => 'étape duplicate (sans verrou) et UNIQUE(financialYear, class), accounting/account/module/Account.e.php:137 et accounting/account/account.yml:17',
		'accounting/asset/page/ancillary.p.php:doCreate' => 'create() supprime l\'existante (asset, exercice) puis réinsère ; UNIQUE, Ancillary.l.php:91',
		'accounting/asset/page/csv.p.php:doCreateAssets' => 'partielle : un renvoi tardif trouve l\'import vidé, deux envois simultanés insèrent deux fois, accounting/asset/page/csv.p.php:80',
		'accounting/asset/page/depreciation.p.php:doCreate' => 'create() remplace la dépréciation de l\'exercice ; UNIQUE(asset, financialYear), Depreciation.l.php:195',
		'accounting/asset/page/index.p.php:doCreate' => 'partielle : écriture déjà couverte (acceptAttachAsset) ou immo en cours ENDED, sinon aucune, journal/module/Operation.e.php:78 et asset/lib/Asset.l.php:156',
		'accounting/asset/page/recovery.p.php:doCreate' => 'create() remplace la dépréciation de l\'exercice ; UNIQUE(asset, financialYear), Depreciation.l.php:195',
		'accounting/asset/page/revision.p.php:doCreate' => 'create() supprime la révision existante (asset, exercice, type) avant d\'insérer, Revision.l.php:100',
		'accounting/bank/page/account.p.php:doCreate' => 'UNIQUE(description), accounting/bank/bank.yml:38',
		'accounting/bank/page/annotationAi.p.php:doCreate' => 'aucune',
		'accounting/bank/page/cashflow.p.php:doWallet' => 'partielle : acceptWalletCreate refuse un mouvement soldé (pas si montant partiel), bank/module/Cashflow.e.php:84',
		'accounting/bank/page/emptyPeriodAi.p.php:doCreate' => 'check gap : trous calculés hors périodes déjà déclarées, EmptyPeriod.e.php:17, bank/lib/Import.l.php:107',
		'accounting/bank/page/reconciliation.p.php:doCreate' => 'partielle : acceptReconciliate refuse un mouvement déjà soldé (pas si partiel), bank/lib/Reconciliation.l.php:222',
		'accounting/cash/page/archives.p.php:doCreate' => 'aucune',
		'accounting/cash/page/cash.p.php:doCreate' => 'aucune (seul le solde initial est refusé s\'il existe, cash/lib/Cash.l.php:717)',
		'accounting/cash/page/register.p.php:doCreate' => 'aucune (sauf cahier Stripe/SumUp : un seul actif par moyen, Register.e.php:407)',
		'accounting/company/page/accountingHelp.p.php:doCreate' => 'refus si une demande est déjà en attente pour la structure, AccountingHelp.l.php:252',
		'accounting/company/page/accountingHelp.p.php:doCreateMessage' => 'aucune',
		'accounting/company/page/public.p.php:doCreate' => 'exercice existant ou refus overlap, sans verrou, accounting/financialYear/lib/FinancialYear.l.php:917 et accounting/financialYear/module/FinancialYear.e.php:942',
		'accounting/financialYear/page/archives.p.php:doCreate' => 'aucune',
		'accounting/financialYear/page/index.p.php:doCreate' => 'check overlap : startDate refusée si un exercice la couvre déjà, FinancialYear.e.php:942',
		'accounting/journal/page/operation.p.php:doCreate' => 'aucune (hash de pièce tiré à chaque envoi), accounting/journal/lib/Operation.l.php:1326',
		'accounting/journal/page/pattern.p.php:doCreate' => 'aucune',
		'accounting/journal/page/pattern.p.php:doCreateFromOperations' => 'aucune',
		'accounting/loan/page/index.p.php:doCreate' => 'aucune (deux emprunts de mêmes caractéristiques peuvent coexister, rien ne les distingue d\'un doublon)',
		'accounting/loan/page/realignment.p.php:doCreate' => 'étape taken, relue dans la transaction qui écrit après le verrou FOR UPDATE de l\'emprunt, qui sérialise ses recalages : un second envoi du même recalage est refusé à la même date, accounting/loan/lib/Realignment.l.php:60',
		'accounting/pdp/page/address.p.php:doCreate' => 'check exists : identifiant déjà en base ou annuaire refusé, Address.e.php:60',
		'accounting/proof/page/operationMissingAi.p.php:doCreate' => 'acceptCreate() via isMarkable refuse un hash déjà marqué ; UNIQUE(hash), OperationMissing.l.php:78',
		'accounting/proof/page/proof.p.php:doCreate' => 'étape content.duplicate sur l\'empreinte du fichier, lue sans verrou ni UNIQUE, accounting/proof/module/File.e.php:108',
		'accounting/proof/page/proof.p.php:doCreateFile' => 'étape content.duplicate sur l\'empreinte du fichier, lue sans verrou ni UNIQUE, accounting/proof/module/File.e.php:108',
		'accounting/proof/page/sampleAi.p.php:doCreate' => 'aucune',
		'accounting/taxReturn/page/index.p.php:/@/liasse-fiscale:doCreateAssociate' => 'aucune',
		'accounting/taxReturn/page/index.p.php:/@/liasse-fiscale:doCreateDeposit' => 'getRefusal \'deposited\' sous verrou FOR UPDATE de l\'exercice, Deposit.l.php:103-130',
		'accounting/taxReturn/page/index.p.php:/@/liasse-fiscale:doCreateLine' => 'aucune',
		'accounting/taxReturn/page/index.p.php:/@/liasse-fiscale:doCreateSubsidiary' => 'aucune',
		'accounting/thirdParty/page/csv.p.php:doCreateThirdParties' => 'l\'import s\'ouvre par la suppression atomique de la clé Redis, le second envoi la trouve absente et n\'écrit rien ; un tiers apparu entre-temps est relu sous verrou, accounting/thirdParty/lib/Csv.l.php:405',
		'accounting/thirdParty/page/index.p.php:doCreateAddress' => 'adresse déjà dans la liste ignorée, la liste entière se réécrit, accounting/thirdParty/lib/ThirdParty.l.php:764',
		'accounting/training/page/farm.p.php:doCreate' => 'UNIQUE(enrollment), DuplicateException captée en échec, Farm.l.php:205',
		'accounting/vat/page/declaration.p.php:doCreateOperations' => 'acceptAccount() refuse une déclaration comptabilisée, sans verrou, accounting/vat/module/Declaration.e.php:115 et accounting/vat/lib/Declaration.l.php:349',
		'base/block/page/block.p.php:doCreate' => 'aucune',
		'base/hr/page/absence.p.php:doCreate' => 'aucune',
		'base/hr/page/presence.p.php:doCreate' => 'check present : refuse une présence qui chevauche une existante, Presence.e.php:110',
		'base/hr/page/teammate.p.php:doCreate' => 'UNIQUE(structure, user) pour une personne qui a un accès, base/hr/hr.yml:47 ; aucune pour une personne sans compte, créée neuve à chaque envoi',
		'base/hr/page/workingTime.p.php:doCreate' => 'UNIQUE(structure, user, date), base/hr/hr.yml:61, que `WorkingTimeLib::create()` tient en mettant à jour la journée existante sur son `DuplicateException`, base/hr/lib/WorkingTime.l.php:135',
		'base/mail/page/contact.p.php:doCreateCollection' => 'UNIQUE(structure, email), doublon traduit en refus, base/mail/mail.yml:56 et base/mail/lib/Contact.l.php:486',
		'base/mail/page/customize.p.php:doCreate' => 'delete puis insert sur (structure, type, shop), UNIQUE, Customize.l.php:64',
		'base/note/page/note.p.php:doCreate' => 'aucune (deux notes au même titre sont légitimes)',
		'base/structure/page/apiKey.p.php:doCreate' => 'aucune (une clé neuve à chaque envoi), framework/api/lib/PersonalAccessToken.l.php:21',
		'base/structure/page/invite.p.php:doCreateMember' => 'partielle : une invitation en attente au même e-mail est refusée (sans verrou), une invitation sans e-mail ne l\'est pas, base/structure/module/Invite.e.php:229',
		'base/structure/page/structure.p.php:doCreate' => 'aucune',
		'base/user/page/avatarUpload.p.php:doCreate' => 'aucune (un second envoi remplace le premier sur l\'avatar, dont il retire le fichier, et prend une place du débit)',
		'commercialisation/amap/page/contract.p.php:doCreate' => 'aucune',
		'commercialisation/amap/page/contract.p.php:doCreateNewProduct' => 'aucune (un produit neuf à chaque envoi, référence non construite), commercialisation/amap/lib/ContractProduct.l.php:674',
		'commercialisation/amap/page/contract.p.php:doCreateProduct' => 'UNIQUE(contract, product), doublon traduit en refus, commercialisation/amap/amap.yml:110 et commercialisation/amap/lib/ContractProduct.l.php:533',
		'commercialisation/amap/page/contractWaiting.p.php:doCreate' => 'refuse une attente active du même adhérent sous verrou du contrat, ContractWaiting.l.php:96',
		'commercialisation/amap/page/customer.p.php:doCreate' => 'partielle : un adhérent actif au même e-mail est refusé (sans verrou), un adhérent sans e-mail ne l\'est pas, commercialisation/selling/module/Customer.e.php:801',
		'commercialisation/amap/page/distribution.p.php:doCreateSeason' => 'les jours déjà occupés sont écartés avant création, lus sans verrou, commercialisation/shop/lib/Date.l.php:379',
		'commercialisation/amap/page/distribution.p.php:doCreateSeasonNext' => 'période couverte refusée, jours occupés écartés, sans verrou, commercialisation/amap/lib/Distribution.l.php:883 et commercialisation/shop/lib/Date.l.php:379',
		'commercialisation/amap/page/duty.p.php:doCreate' => 'aucune',
		'commercialisation/amap/page/index.p.php:doCreateSales' => 'seuls les engagements sans vente sont lus, ventes existantes complétées, sans verrou, commercialisation/amap/lib/Sale.l.php:57',
		'commercialisation/amap/page/membership.p.php:doCreate' => 'aucune (aucun contrôle de période ni UNIQUE par adhérent), commercialisation/amap/lib/Membership.l.php:490',
		'commercialisation/amap/page/membershipRequest.p.php:/amap/{id@int}/adherer/:doCreate' => 'demande en attente à la même adresse reprise (sans verrou), e-mail renvoyé hors délai, commercialisation/amap/lib/MembershipRequest.l.php:132',
		'commercialisation/amap/page/referent.p.php:doCreate' => 'UNIQUE(producer, user), doublon traduit en refus, commercialisation/amap/amap.yml:321 et commercialisation/amap/lib/Referent.l.php:383',
		'commercialisation/amap/page/subscription.p.php:doCreate' => 'aucune (seul un contrat d\'essai refuse une seconde souscription, amap/module/Subscription.e.php:842)',
		'commercialisation/amap/page/subscriptionInvitation.p.php:doCreate' => 'refuse une invitation en cours sous verrou de la souscription, SubscriptionInvitation.l.php:195',
		'commercialisation/amap/page/subscriptionRequest.p.php:doCreate' => 'lockForRequest() refuse une demande en attente sous verrou, SubscriptionRequest.l.php:1050',
		'commercialisation/amap/page/volunteer.p.php:doCreate' => 'UNIQUE(duty, customer) sous verrou du poste, commercialisation/amap/amap.yml:507 et commercialisation/amap/lib/Volunteer.l.php:773',
		'commercialisation/external/page/document.p.php:doCreate' => 'partielle : le remplacement d\'un document est refusé une seconde fois (verrou), un dépôt neuf ne l\'est pas, commercialisation/external/lib/Document.l.php:63',
		'commercialisation/external/page/line.p.php:doCreate' => 'aucune',
		'commercialisation/external/page/line.p.php:doCreateProduct' => 'partielle : une référence saisie bute sur UNIQUE(structure, reference), un produit sans référence est créé deux fois, base/product/product.yml:125',
		'commercialisation/payment/page/link.p.php:doCreate' => 'aucune',
		'commercialisation/payment/page/method.p.php:doCreate' => 'aucune',
		'commercialisation/payment/page/sumupReader.p.php:doCreate' => 'SumUp refuse un code d\'appairage déjà utilisé (SumupReader.l.php:59), UNIQUE readerId (SumupReader.m.php:82)',
		'commercialisation/payment/page/sumupReaderCheckout.p.php:doCreate' => 'acceptCreate() sous verrou refuse un envoi SENT du règlement, SumupReaderCheckout.e.php:78',
		'commercialisation/payment/page/wallet.p.php:doCreate' => 'aucune',
		'commercialisation/catalog/page/catalog.p.php:doCreate' => 'UNIQUE(structure, name), nom exigé, doublon traduit en refus, base/product/product.yml:10 et commercialisation/catalog/lib/Catalog.l.php:20',
		'base/product/page/category.p.php:doCreate' => 'aucune',
		'base/product/page/csv.p.php:doCreateProducts' => 'l\'import s\'ouvre par la suppression atomique de la clé Redis, le second envoi la trouve absente et n\'écrit rien, product/lib/Csv.l.php:158',
		'commercialisation/catalog/page/offer.p.php:doCreateCollection' => 'UNIQUE (date, product) et (catalog, product), contenant fixé par la page, insertion en add-ignore, product/product.yml:65 et catalog/lib/Offer.l.php:2116',
		'base/product/page/product.p.php:doCreate' => 'partielle : une référence saisie est refusée en doublon, un produit sans référence (facultative) se crée deux fois, product/lib/Product.l.php:230',
		'base/product/page/product.p.php:doCreateFirstVariant' => 'acceptEnableVariants() refuse un produit déjà variante, lu sans verrou, product/module/Product.e.php:142',
		'commercialisation/catalog/page/relation.p.php:doCreate' => 'contrôle « produit déjà dans un groupe » lu sans verrou, doublé de l\'UNIQUE (child), catalog/module/Offer.e.php:490 et product/product.yml:145',
		'base/product/page/unit.p.php:doCreate' => 'UNIQUE (structure, singular), doublon rendu en erreur de champ, product/product.yml:188 et product/lib/Unit.l.php:133',
		'commercialisation/quotation/page/community.p.php:doCreateQuotation' => 'contrôle « cotation active identique existe déjà » lu sans verrou (l\'UNIQUE ne garde pas, colonnes nullables), quotation/lib/Community.l.php:423',
		'commercialisation/quotation/page/community.p.php:doCreateSector' => 'UNIQUE (community, name), doublon rendu en erreur de champ, quotation/quotation.yml:456 et quotation/lib/Sector.l.php:221',
		'commercialisation/quotation/page/community.p.php:doOpenCollect' => 'aucune',
		'commercialisation/quotation/page/community.p.php:doRemind' => 'getRecent() écarte les adresses relancées depuis REMINDER_DELAY jours, quotation/lib/Community.l.php:756',
		'commercialisation/quotation/page/community.p.php:doSendReport' => 'verrou Redis et anti-join des destinataires déjà servis, quotation/lib/Community.l.php:123 et :131',
		'commercialisation/selling/page/archives.p.php:doCreate' => 'aucune',
		'commercialisation/selling/page/campaign.p.php:doCreate' => 'aucune',
		'commercialisation/selling/page/campaign.p.php:doUploadAttachment' => 'aucune (un second envoi laisse une pièce que nulle campagne ne cite, supprimée par AttachmentLib::clean() après un jour)',
		'commercialisation/selling/page/csv.p.php:doCreateCustomers' => 'l\'import s\'ouvre par la suppression atomique de la clé Redis, le second envoi la trouve absente et n\'écrit rien, selling/lib/Csv.l.php:189',
		'commercialisation/selling/page/csv.p.php:doCreatePrices' => 'l\'import s\'ouvre par la suppression atomique de la clé Redis, le second envoi la trouve absente et n\'écrit rien, selling/lib/Csv.l.php:587',
		'commercialisation/selling/page/customer.p.php:doCreate' => 'partielle : en AMAP un e-mail déjà actif est refusé (sans verrou), ailleurs ou sans e-mail le client se crée deux fois, selling/module/Customer.e.php:801',
		'commercialisation/selling/page/customerGroup.p.php:doCreate' => 'UNIQUE (structure, name), doublon rendu en erreur de champ, selling/lib/CustomerGroup.l.php:113',
		'commercialisation/selling/page/grid.p.php:doCreate' => 'UNIQUE (customer, product) et (group, product), l\'un des deux est toujours posé, selling/lib/Grid.l.php:289',
		'commercialisation/selling/page/customerInvite.p.php:doCreate' => 'suppression puis réinsertion de l\'invitation du client, mais un second e-mail part, selling/lib/CustomerInvite.l.php:112 et base/structure/lib/Invite.l.php:275',
		'commercialisation/selling/page/invoice.p.php:doCreate' => 'les ventes postées doivent être sans facture, lu sans verrou, selling/module/Invoice.e.php:629 et selling/lib/Sale.l.php:866',
		'commercialisation/selling/page/invoice.p.php:doCreateDeposit' => 'acceptDeposit() exige un paiement encore rattaché à la vente, que la création bascule sur la facture, lu sans verrou, payment/module/Payment.e.php:105',
		'commercialisation/selling/page/invoice.p.php:doCreateMonthly' => 'les ventes postées doivent être sans facture (build de sales), lu sans verrou, selling/module/Invoice.e.php:629 et selling/lib/Sale.l.php:866',
		'commercialisation/selling/page/item.p.php:doCreateCollection' => 'aucune (chaque envoi ajoute ses articles à la vente)',
		'commercialisation/selling/page/market.p.php:doCreateSale' => 'aucune (chaque envoi ouvre une vente neuve sur le marché, selling/lib/Market.l.php:444)',
		'commercialisation/selling/page/marketDevice.p.php:doSendNotPaid' => 'aucune (le différé ne porte ni service ni numéro, chaque envoi crée une vente et le doublon s\'annule, selling/lib/Market.l.php:835)',
		'commercialisation/selling/page/marketDevice.p.php:doCharge' => 'aucune (un envoi au TPE par appareil tenu par le verrou de l\'appareil, SumupReaderCheckoutLib::createByMarketDevice())',
		'commercialisation/selling/page/sale.p.php:doCreate' => 'partielle : une composition est refusée par l\'UNIQUE (compositionOf, deliveredAt), une vente ordinaire se crée deux fois, selling/lib/Sale.l.php:1196',
		'commercialisation/selling/page/sale.p.php:doCreateCollection' => 'aucune (une vente par client posté, à chaque envoi)',
		'commercialisation/shop/page/date.p.php:doCreate' => 'aucune',
		'commercialisation/shop/page/date.p.php:doCreateCollection' => 'les dates déjà occupées sur la boutique sont écartées, puis refus si plus rien, lu sans verrou, shop/lib/Date.l.php:379',
		'commercialisation/shop/page/department.p.php:doCreate' => 'aucune',
		'commercialisation/shop/page/point.p.php:doCreate' => 'UNIQUE (structure, name), nom toujours exigé, exception de doublon non capturée, shop/shop.yml:62',
		'commercialisation/shop/page/range.p.php:doCreate' => 'UNIQUE (shop, catalog), doublon capturé, shop/shop.yml:75 et shop/lib/Range.l.php:31',
		'commercialisation/shop/page/shopInvite.p.php:doCreate' => 'partielle : une invitation en attente au même e-mail est refusée (sans verrou), une invitation sans e-mail ne l\'est pas, base/structure/module/Invite.e.php:229',
		'framework/api/page/admin/index.p.php:doCreate' => 'aucune (clientId tiré au hasard par create(), le nom n\'est pas unique)',
		'internal/communication/page/admin/noteAi.p.php:doCreate' => 'aucune (une seconde note part et notifie de nouveau tous les destinataires)',
		'internal/data/page/admin/accountingHelpAi.p.php:doCreateGap' => 'la clé doit être encore à juger, doublé de l\'UNIQUE (accountingHelp, key), help/lib/Import.l.php:536 et help/help.yml:42',
		'internal/data/page/admin/accountingHelpAi.p.php:doCreateMessage' => 'aucune',
		'internal/data/page/admin/accountingHelpAi.p.php:doSend' => 'sans brouillon, refus nothingToSend ; update whereSentAt(NULL), help/lib/Message.l.php:275 et help/lib/Message.l.php:294',
		'ouvretaferme/analyze/page/report.p.php:doCreate' => 'partielle : un nom saisi est refusé par l\'UNIQUE (plant, season, name), un rapport sans nom (nullable) se crée deux fois, analyze/lib/Report.l.php:294',
		'ouvretaferme/association/page/admin/index.p.php:doCreate' => 'aucune (le premier envoi passe l\'historique en payé et la vente en livrée, le second ne les retrouve plus et en recrée)',
		'ouvretaferme/association/page/membership.p.php:doCreatePayment' => 'historique et vente brouillon repris, sans verrou, ouvretaferme/association/lib/Membership.l.php:538 et ouvretaferme/association/lib/Membership.l.php:308',
		'ouvretaferme/learning/page/account.p.php:doCreate' => 'e-mail unique, et le compte connecté au premier envoi est refusé par checkAnonymous, base/user/user.yml:167 et ouvretaferme/learning/page/account.p.php:28',
		'ouvretaferme/learning/page/admin/theme.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/admin/theme.p.php:doCreateLevel' => 'aucune',
		'ouvretaferme/learning/page/admin/theme.p.php:doCreateSubtheme' => 'aucune',
		'ouvretaferme/learning/page/chapter.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/comment.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/course.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/course.p.php:doCreateProgram' => 'aucune (un second module et ses leçons, rien n\'est unique sur le nom)',
		'ouvretaferme/learning/page/date.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/dateEnrollment.p.php:doCreate' => 'refuse une inscription ouverte existante sous verrou, DateEnrollment.l.php:627 (UNIQUE user,date)',
		'ouvretaferme/learning/page/enrollment.p.php:doCreate' => 'UNIQUE (user, course) et insertion add-ignore, ouvretaferme/learning/learning.yml:205 et ouvretaferme/learning/lib/Enrollment.l.php:498',
		'ouvretaferme/learning/page/lesson.p.php:doCreate' => 'aucune',
		'ouvretaferme/learning/page/question.p.php:doCreate' => 'aucune (une seconde question à la position suivante)',
		'ouvretaferme/learning/page/review.p.php:doCreate' => 'met à jour l\'avis existant (upsert), learning/lib/Review.l.php:272 (UNIQUE course,user)',
		'ouvretaferme/learning/page/staff.p.php:doCreate' => 'partielle : personne choisie refusée en double, personne saisie recréée, ouvretaferme/learning/learning.yml:363 et ouvretaferme/learning/module/Staff.e.php:87',
		'ouvretaferme/learning/page/supervisor.p.php:doCreate' => 'aucune',
		'ouvretaferme/website/page/menu.p.php:doCreate' => 'partielle : menu vers une page refusé en double, menu par adresse ou libellé non, ouvretaferme/website/website.yml:33 et ouvretaferme/website/lib/Menu.l.php:123',
		'ouvretaferme/website/page/news.p.php:doCreate' => 'aucune',
		'ouvretaferme/website/page/webpage.p.php:doCreate' => 'UNIQUE (website, url), l\'adresse étant postée et non nullable, ouvretaferme/website/website.yml:86 et ouvretaferme/website/lib/Webpage.l.php:79',
		'production/aid/page/admin/aid.p.php:doCreate' => 'aucune (une seconde aide du même nom, réservée à l\'équipe)',
		'production/aid/page/admin/aidLink.p.php:doCreate' => 'UNIQUE (aid, target, kind), DuplicateException captée en échec, production/aid/aid.yml:55 et production/aid/lib/AidLink.l.php:18',
		'production/aid/page/admin/aidOrganization.p.php:doCreate' => 'UNIQUE (aid, organization, role), DuplicateException captée en échec, production/aid/aid.yml:67 et production/aid/lib/AidOrganization.l.php:18',
		'production/aid/page/admin/aidPiece.p.php:doCreate' => 'aucune (une seconde pièce du même nom, réservée à l\'équipe)',
		'production/aid/page/admin/area.p.php:doCreate' => 'UNIQUE (aid, department), DuplicateException captée en échec, production/aid/aid.yml:96 et production/aid/lib/Area.l.php:18',
		'production/aid/page/admin/edition.p.php:doCreate' => 'aucune (une seconde édition, réservée à l\'équipe)',
		'production/aid/page/admin/source.p.php:doCreate' => 'aucune (une seconde source à la même adresse, réservée à l\'équipe)',
		'production/gallery/page/photo.p.php:doCreate' => 'aucune',
		'production/map/page/bed.p.php:doCreate' => 'nom de planche obligatoire et déjà pris dans le jardin refusé, lu avant l\'insertion sans verrou ni UNIQUE, production/map/module/Bed.e.php:33',
		'production/map/page/decor.p.php:doCreate' => 'seulement pour un décor posé sur parcelle/jardin : check « alone », Decor.e.php:251 et :291',
		'production/map/page/decorBuilding.p.php:doCreate' => 'partielle : le hangar personnalisé est relu sous verrou et un second envoi est refusé (decorChanged), production/map/lib/DecorBuilding.l.php:130 ; un bâtiment neuf est créé deux fois, le plafond relu sous verrou ne borne que le nombre',
		'production/map/page/decorObject.p.php:doCreate' => 'aucune : chaque envoi crée un élément neuf avec son envoi (production/map/lib/DecorUpload.l.php:111), et un second envoi crée un second élément ; le plafond DECOR_OBJECT_MAX relu sous verrou ne borne que le nombre, production/map/lib/DecorObject.l.php:87',
		'production/map/page/decorLayer.p.php:doCreate' => 'aucune',
		'production/map/page/decorUpload.p.php:doCreate' => 'aucune : chaque envoi crée un bâtiment neuf avec son envoi (production/map/lib/DecorUpload.l.php:67), et un second envoi crée un second bâtiment ; le plafond relu sous verrou ne borne que le nombre, production/map/lib/DecorBuilding.l.php:122',
		'production/map/page/onboarding.p.php:doCreate' => 'aucune (une seconde parcelle avec ses jardins et ses planches)',
		'production/map/page/plot.p.php:doCreate' => 'aucune',
		'production/map/page/zone.p.php:doCreate' => 'aucune',
		'production/planning/page/harvestWeek.p.php:doHarvest' => 'partielle : l\'intervention est réutilisée, HarvestWeek.l.php:776, mais en mode ajout la quantité se cumule, series/lib/Task.l.php:2933',
		'production/planning/page/taskDay.p.php:doCreate' => 'aucune (les interventions et leurs temps sont recréés)',
		'production/planning/page/taskDetail.p.php:doHarvest' => 'partielle : en mode ajout la quantité se recumule, series/lib/Task.l.php:2933',
		'production/planning/page/taskWeek.p.php:doCreate' => 'aucune (les interventions et leurs temps sont recréés)',
		'production/planning/page/taskWeek.p.php:doHarvest' => 'partielle : l\'intervention est réutilisée, HarvestWeek.l.php:776, mais en mode ajout la quantité se cumule, series/lib/Task.l.php:2933',
		'production/planning/page/taskYear.p.php:doCreate' => 'aucune (les interventions et leurs temps sont recréés)',
		'production/planning/page/timeDay.p.php:doCreate' => 'aucune (les interventions et leurs temps sont recréés)',
		'production/plant/page/plant.p.php:doCreate' => 'UNIQUE (structure, name), la structure fixée par la page, production/plant/plant.yml:73 et production/plant/lib/Plant.l.php:299',
		'production/plant/page/size.p.php:doCreate' => 'UNIQUE (structure, plant, name), l\'espèce postée devant exister, production/plant/plant.yml:88 et production/plant/lib/Size.l.php:45',
		'production/plant/page/variety.p.php:doCreate' => 'UNIQUE (structure, plant, name), l\'espèce postée devant exister, production/plant/plant.yml:121 et production/plant/lib/Variety.l.php:379',
		'production/sequence/page/crop.p.php:doCreate' => 'UNIQUE (sequence, plant), production/sequence/sequence.yml:21 et production/sequence/lib/Crop.l.php:133',
		'production/sequence/page/flow.p.php:doCreate' => 'aucune',
		'production/sequence/page/sequence.p.php:doCreate' => 'aucune',
		'production/series/page/comment.p.php:doCreateCollection' => 'aucune (un second commentaire sur chaque intervention)',
		'production/series/page/copyAi.p.php:doCreate' => 'n\'écrit rien, redirige vers l\'écran de conflits, production/series/page/copyAi.p.php:115',
		'production/series/page/csv.p.php:doCreateCultivations' => 'le fichier en cache est effacé par un DEL Redis atomique avant l\'écriture, le second envoi ne crée rien, production/series/lib/Csv.l.php:1039',
		'production/series/page/cultivation.p.php:doCreate' => 'UNIQUE (series, plant), production/series/series.yml:59 et production/series/lib/Cultivation.l.php:1776',
		'production/series/page/place.p.php:doCreate' => 'aucune (les interventions et leurs temps sont recréés, comme taskWeek.p.php:doCreate dont elle reprend le panneau)',
		'production/series/page/series.p.php:doCreate' => 'aucune (une seconde série avec ses cultures)',
		'production/series/page/series.p.php:doCreateSequence' => 'aucune (un second itinéraire, aucun nom unique)',
		'production/series/page/task.p.php:doCreate' => 'aucune',
		'production/series/page/task.p.php:doCreateFromSeriesCollection' => 'aucune (les interventions sont recréées sur chaque série)',
		'production/toolbox/page/compositionFormula.p.php:doCreate' => 'nom déjà pris refusé avant l\'écriture et UNIQUE (plan, name), production/toolbox/toolbox.yml:42 et production/toolbox/lib/CompositionFormula.l.php:431',
		'production/toolbox/page/compositionLine.p.php:doCreate' => 'espèce déjà servie refusée avant l\'écriture et UNIQUE (week, species), production/toolbox/toolbox.yml:70 et production/toolbox/lib/CompositionLine.l.php:71',
		'production/toolbox/page/gauge.p.php:doCreate' => 'check « placed » : un seul pluviomètre actif par lieu, Gauge.e.php:71',
		'production/toolbox/page/maintenance.p.php:doCreate' => 'aucune',
		'production/toolbox/page/maintenanceDone.p.php:doCreate' => 'aucune',
		'production/toolbox/page/waterMeter.p.php:doCreate' => 'check « duplicate » : nom de compteur actif unique, WaterMeter.e.php:51',
		'production/work/page/action.p.php:doCreate' => 'aucune',
		'production/work/page/category.p.php:doCreate' => 'aucune',
		'production/work/page/method.p.php:doCreate' => 'aucune',
		'production/work/page/supplier.p.php:doCreate' => 'aucune',
		'production/work/page/tool.p.php:doCreate' => 'UNIQUE (structure, name, status), le statut valant ACTIVE à la création, production/work/work.yml:99 et production/work/lib/Tool.l.php:596',
	];

	/**
	 * Les routes qui créent, comptées par fichier.
	 */
	const CREATE = [
		'accounting/account/page/index.p.php' => 6,
		'accounting/asset/page/allocation.p.php' => 3,
		'accounting/asset/page/ancillary.p.php' => 2,
		'accounting/asset/page/csv.p.php' => 1,
		'accounting/asset/page/depreciation.p.php' => 2,
		'accounting/asset/page/expense.p.php' => 4,
		'accounting/asset/page/index.p.php' => 11,
		'accounting/asset/page/recovery.p.php' => 2,
		'accounting/asset/page/revision.p.php' => 2,
		'accounting/bank/page/account.p.php' => 2,
		'accounting/bank/page/annotationAi.p.php' => 2,
		'accounting/bank/page/cashflow.p.php' => 6,
		'accounting/bank/page/emptyPeriodAi.p.php' => 1,
		'accounting/bank/page/enableBanking.p.php' => 1,
		'accounting/bank/page/import.p.php' => 1,
		'accounting/bank/page/reconciliation.p.php' => 3,
		'accounting/cash/page/archives.p.php' => 1,
		'accounting/cash/page/cash.p.php' => 5,
		'accounting/cash/page/register.p.php' => 1,
		'accounting/cash/page/suggestion.p.php' => 1,
		'accounting/cash/page/transaction.p.php' => 3,
		'accounting/company/page/accountingHelp.p.php' => 3,
		'accounting/company/page/public.p.php' => 4,
		'accounting/financialYear/page/archives.p.php' => 1,
		'accounting/financialYear/page/fec.p.php' => 7,
		'accounting/financialYear/page/index.p.php' => 3,
		'accounting/invoicing/page/invoicing.p.php' => 2,
		'accounting/invoicing/page/line.p.php' => 1,
		'accounting/journal/page/journalCode.p.php' => 9,
		'accounting/journal/page/operation.p.php' => 4,
		'accounting/journal/page/pattern.p.php' => 8,
		'accounting/loan/page/index.p.php' => 1,
		'accounting/loan/page/realignment.p.php' => 1,
		'accounting/pdp/page/address.p.php' => 6,
		'accounting/pdp/page/partner.p.php' => 1,
		'accounting/preaccounting/page/purchase.p.php' => 1,
		'accounting/preaccounting/page/sale.p.php' => 5,
		'accounting/preaccounting/page/suggestion.p.php' => 2,
		'accounting/proof/page/folder.p.php' => 1,
		'accounting/proof/page/operationMissingAi.p.php' => 1,
		'accounting/proof/page/proof.p.php' => 5,
		'accounting/proof/page/sampleAi.p.php' => 1,
		'accounting/taxReturn/page/index.p.php' => 4,
		'accounting/thirdParty/page/csv.p.php' => 1,
		'accounting/thirdParty/page/index.p.php' => 6,
		'accounting/training/page/farm.p.php' => 1,
		'accounting/vat/page/declaration.p.php' => 5,
		'base/block/page/block.p.php' => 2,
		'base/hr/page/absence.p.php' => 1,
		'base/hr/page/presence.p.php' => 2,
		'base/hr/page/teammate.p.php' => 2,
		'base/hr/page/workingTime.p.php' => 1,
		'base/mail/page/contact.p.php' => 2,
		'base/mail/page/customize.p.php' => 1,
		'base/note/page/note.p.php' => 1,
		'base/note/page/notebook.p.php' => 1,
		'base/structure/page/apiKey.p.php' => 1,
		'base/structure/page/community.p.php' => 5,
		'base/structure/page/invite.p.php' => 1,
		'base/structure/page/role.p.php' => 1,
		'base/structure/page/sectionAi.p.php' => 1,
		'base/structure/page/structure.p.php' => 1,
		'base/structure/page/survey.p.php' => 1,
		'base/user/page/admin/agent.p.php' => 1,
		'base/user/page/admin/ban.p.php' => 1,
		'base/user/page/avatarUpload.p.php' => 2,
		'base/user/page/signUp.p.php' => 1,
		'base/user/page/tab.p.php' => 1,
		'commercialisation/amap/page/contract.p.php' => 6,
		'commercialisation/amap/page/contractWaiting.p.php' => 1,
		'commercialisation/amap/page/customer.p.php' => 2,
		'commercialisation/amap/page/distribution.p.php' => 4,
		'commercialisation/amap/page/duty.p.php' => 1,
		'commercialisation/amap/page/index.p.php' => 2,
		'commercialisation/amap/page/membership.p.php' => 2,
		'commercialisation/amap/page/membershipRequest.p.php' => 1,
		'commercialisation/amap/page/referent.p.php' => 1,
		'commercialisation/amap/page/subscription.p.php' => 1,
		'commercialisation/amap/page/subscriptionInvitation.p.php' => 1,
		'commercialisation/amap/page/subscriptionRequest.p.php' => 1,
		'commercialisation/amap/page/volunteer.p.php' => 1,
		'commercialisation/external/page/document.p.php' => 2,
		'commercialisation/external/page/line.p.php' => 3,
		'commercialisation/payment/page/link.p.php' => 1,
		'commercialisation/payment/page/method.p.php' => 1,
		'commercialisation/payment/page/sumupReader.p.php' => 1,
		'commercialisation/payment/page/sumupReaderCheckout.p.php' => 3,
		'commercialisation/payment/page/wallet.p.php' => 2,
		'commercialisation/catalog/page/catalog.p.php' => 2,
		'base/product/page/category.p.php' => 1,
		'base/product/page/csv.p.php' => 1,
		'commercialisation/catalog/page/offer.p.php' => 2,
		'base/product/page/product.p.php' => 3,
		'commercialisation/catalog/page/relation.p.php' => 1,
		'base/product/page/unit.p.php' => 1,
		'commercialisation/quotation/page/community.p.php' => 4,
		'commercialisation/quotation/page/responseAi.p.php' => 2,
		'commercialisation/selling/page/archives.p.php' => 1,
		'commercialisation/selling/page/campaign.p.php' => 2,
		'commercialisation/selling/page/csv.p.php' => 2,
		'commercialisation/selling/page/customer.p.php' => 1,
		'commercialisation/selling/page/customerGroup.p.php' => 1,
		'commercialisation/selling/page/grid.p.php' => 1,
		'commercialisation/selling/page/customerInvite.p.php' => 1,
		'commercialisation/selling/page/invoice.p.php' => 4,
		'commercialisation/selling/page/item.p.php' => 2,
		'commercialisation/selling/page/market.p.php' => 2,
		'commercialisation/selling/page/marketDevice.p.php' => 3,
		'commercialisation/selling/page/sale.p.php' => 5,
		'commercialisation/shop/page/date.p.php' => 3,
		'commercialisation/shop/page/department.p.php' => 1,
		'commercialisation/shop/page/point.p.php' => 1,
		'commercialisation/shop/page/public.p.php' => 3,
		'commercialisation/shop/page/range.p.php' => 1,
		'commercialisation/shop/page/shopInvite.p.php' => 1,
		'framework/api/page/admin/index.p.php' => 2,
		'internal/communication/page/admin/noteAi.p.php' => 2,
		'internal/communication/page/webinaire.p.php' => 1,
		'internal/data/page/admin/accountingHelpAi.p.php' => 4,
		'ouvretaferme/analyze/page/report.p.php' => 1,
		'ouvretaferme/association/page/admin/index.p.php' => 2,
		'ouvretaferme/association/page/membership.p.php' => 2,
		'ouvretaferme/game/page/index.p.php' => 1,
		'ouvretaferme/learning/page/account.p.php' => 3,
		'ouvretaferme/learning/page/admin/theme.p.php' => 3,
		'ouvretaferme/learning/page/chapter.p.php' => 1,
		'ouvretaferme/learning/page/comment.p.php' => 1,
		'ouvretaferme/learning/page/course.p.php' => 4,
		'ouvretaferme/learning/page/date.p.php' => 1,
		'ouvretaferme/learning/page/dateEnrollment.p.php' => 1,
		'ouvretaferme/learning/page/enrollment.p.php' => 1,
		'ouvretaferme/learning/page/lesson.p.php' => 1,
		'ouvretaferme/learning/page/public.p.php' => 2,
		'ouvretaferme/learning/page/question.p.php' => 2,
		'ouvretaferme/learning/page/review.p.php' => 1,
		'ouvretaferme/learning/page/staff.p.php' => 3,
		'ouvretaferme/learning/page/supervisor.p.php' => 1,
		'ouvretaferme/website/page/manage.p.php' => 1,
		'ouvretaferme/website/page/menu.p.php' => 1,
		'ouvretaferme/website/page/news.p.php' => 1,
		'ouvretaferme/website/page/public.p.php' => 1,
		'ouvretaferme/website/page/webpage.p.php' => 1,
		'production/aid/page/admin/aid.p.php' => 1,
		'production/aid/page/admin/aidLink.p.php' => 1,
		'production/aid/page/admin/aidOrganization.p.php' => 1,
		'production/aid/page/admin/aidPiece.p.php' => 1,
		'production/aid/page/admin/area.p.php' => 1,
		'production/aid/page/admin/edition.p.php' => 1,
		'production/aid/page/admin/source.p.php' => 1,
		'production/gallery/page/photo.p.php' => 1,
		'production/map/page/bed.p.php' => 4,
		'production/map/page/configuration.p.php' => 1,
		'production/map/page/decor.p.php' => 1,
		'production/map/page/decorBuilding.p.php' => 1,
		'production/map/page/decorLayer.p.php' => 1,
		'production/map/page/decorObject.p.php' => 2,
		'production/map/page/decorUpload.p.php' => 2,
		'production/map/page/onboarding.p.php' => 2,
		'production/map/page/plot.p.php' => 2,
		'production/map/page/zone.p.php' => 1,
		'production/planning/page/dashboard.p.php' => 1,
		'production/planning/page/taskDay.p.php' => 3,
		'production/planning/page/taskWeek.p.php' => 4,
		'production/planning/page/taskYear.p.php' => 3,
		'production/planning/page/timeDay.p.php' => 4,
		'production/plant/page/forecast.p.php' => 1,
		'production/plant/page/plant.p.php' => 1,
		'production/plant/page/size.p.php' => 1,
		'production/plant/page/variety.p.php' => 1,
		'production/sequence/page/crop.p.php' => 1,
		'production/sequence/page/flow.p.php' => 1,
		'production/sequence/page/sequence.p.php' => 2,
		'production/series/page/comment.p.php' => 2,
		'production/series/page/copyAi.p.php' => 1,
		'production/series/page/csv.p.php' => 1,
		'production/series/page/cultivation.p.php' => 1,
		'production/series/page/index.p.php' => 4,
		'production/series/page/place.p.php' => 2,
		'production/series/page/series.p.php' => 4,
		'production/series/page/task.p.php' => 3,
		'production/toolbox/page/composition.p.php' => 1,
		'production/toolbox/page/compositionFormula.p.php' => 1,
		'production/toolbox/page/compositionLine.p.php' => 1,
		'production/toolbox/page/equipmentReading.p.php' => 1,
		'production/toolbox/page/favorite.p.php' => 1,
		'production/toolbox/page/gauge.p.php' => 1,
		'production/toolbox/page/maintenance.p.php' => 1,
		'production/toolbox/page/maintenanceConfiguration.p.php' => 1,
		'production/toolbox/page/maintenanceDone.p.php' => 1,
		'production/toolbox/page/rain.p.php' => 1,
		'production/toolbox/page/waterConfiguration.p.php' => 1,
		'production/toolbox/page/waterMeter.p.php' => 2,
		'production/toolbox/page/waterReading.p.php' => 1,
		'production/work/page/action.p.php' => 1,
		'production/work/page/category.p.php' => 1,
		'production/work/page/method.p.php' => 1,
		'production/work/page/planning.p.php' => 1,
		'production/work/page/supplier.p.php' => 1,
		'production/work/page/tool.p.php' => 1,
	];

	public function init() {

		foreach(SentinelLib::getFiles('.p.php') as $file => $path) {

			// Un script de configuration ou de tâche planifiée ne reçoit aucun envoi d'utilisateur.
			if(str_contains($file, '/page/configure/') or str_contains($file, '/page/cron/')) {
				continue;
			}

			$scan = self::scan(SentinelLib::getTokens(file_get_contents($path)));

			$this->addFound('create', $file, $scan['create']);

			foreach($scan['doCreate'] as $page => [$pageClass, $propertiesCreate, $line]) {

				if(self::isGuardedByModule($pageClass, $propertiesCreate) === FALSE) {
					$this->addFoundKey('unguarded', $file, $page, $line);
				}

			}

			// Une route `doCreate*` d'une page ordinaire crée sans qu'aucun schéma ne se lise, et une
			// route déjà déclarée reste suivie : sa disparition rend sa déclaration périmée.
			foreach($scan['routes'] as $page => $line) {

				if(str_starts_with($page, 'doCreate') or array_key_exists($file.':'.$page, self::UNGUARDED)) {
					$this->addFoundKey('unguarded', $file, $page, $line);
				}

			}

		}

	}

	public function testCreate(): void {
		$this->assertRatchet('create', self::CREATE, 'appel qui crée dans une page, à déclarer dans CREATE ; une route qui crée sans contrainte UNIQUE qui la garde se déclare aussi dans UNGUARDED');
	}

	public function testUnguarded(): void {
		$this->assertDeclared('unguarded', self::UNGUARDED, 'route qui crée sans contrainte UNIQUE qui la garde : un second envoi du même POST crée deux fois ; déclarer dans UNGUARDED la garde serveur qui l\'empêche, ou « aucune »');
	}

	/**
	 * Le nombre de routes « aucune » décide d'une garde commune : une valeur mal écrite passerait pour
	 * une garde, et une citation qui ne mène à aucun fichier n'en prouve aucune.
	 */
	public function testDeclaredGuards(): void {

		$files = [...array_keys(SentinelLib::getFiles('.php')), ...array_keys(SentinelLib::getFiles('.yml'))];
		$wrong = [];

		foreach(self::UNGUARDED as $route => $guard) {

			if(preg_match('/^aucune( \(.+\))?$/u', $guard) === 1) {
				continue;
			}

			preg_match_all('/([\w\/]+\.(?:[a-z]\.php|yml)):\d+/', $guard, $citations);

			if($citations[1] === []) {
				$wrong[] = $route.' (ni « aucune » ni citation <fichier>:<ligne>)';
			}

			foreach($citations[1] as $citation) {

				$matches = array_filter($files, fn(string $file) => $file === $citation or str_ends_with($file, '/'.$citation));

				if(count($matches) !== 1) {
					$wrong[] = $route.' ('.$citation.' désigne '.count($matches).' fichiers)';
				}

			}

		}

		$this->assertEquals([], $wrong, 'garde mal dite dans UNGUARDED, écrire « aucune », « aucune (…) », ou la garde avec sa citation <fichier>:<ligne> qui ne désigne qu\'un fichier : '.implode(' · ', $wrong));

	}

	/**
	 * Une route `doCreate` est gardée par son schéma quand une contrainte UNIQUE du module ne porte que
	 * des colonnes postées, ou la structure que le contexte fixe, aucune nullable : MySQL laisse passer
	 * plusieurs NULL, et une colonne que `create()` pose (un numéro, un jeton) prend une valeur neuve à
	 * chaque envoi.
	 */
	private static function isGuardedByModule(string $pageClass, array|false|null $propertiesCreate): bool {

		if(class_exists($pageClass) === FALSE) {
			throw new \Exception('Page '.$pageClass.' introuvable, la sentinelle lit mal le receveur de ->doCreate(');
		}

		$module = new \ReflectionClass($pageClass)->getProperty('module')->getDefaultValue();
		$mModule = $module::model();

		if(class_exists($module.'Lib') === FALSE) {
			throw new \Exception('Lib '.$module.'Lib introuvable pour la page '.$pageClass);
		}

		$propertiesCreate ??= ($module.'Lib')::getPropertiesCreate();

		$constraints = $mModule->getUniqueConstraints();

		foreach($mModule->getProperties() as $property) {

			if($mModule->isPropertyUnique($property)) {
				$constraints[] = [$property];
			}

		}

		$propertiesNull = array_values(array_filter($mModule->getProperties(), fn(string $property) => $mModule->isPropertyNull($property)));

		return self::isGuarded($constraints, is_array($propertiesCreate) ? $propertiesCreate : FALSE, $propertiesNull);

	}

	/**
	 * @param string[][] $constraints
	 * @param string[]|false $propertiesCreate FALSE quand une closure les donne, qui ne se lit pas
	 * @param string[] $propertiesNull
	 */
	private static function isGuarded(array $constraints, array|false $propertiesCreate, array $propertiesNull): bool {

		if($propertiesCreate === FALSE) {
			return FALSE;
		}

		foreach($constraints as $constraint) {

			if(array_diff($constraint, [...$propertiesCreate, 'structure']) === [] and array_intersect($constraint, $propertiesNull) === []) {
				return TRUE;
			}

		}

		return FALSE;

	}

	/**
	 * Les créations d'une page : les lignes de chaque appel qui crée, les `->doCreate(` par nom de page
	 * avec la classe de la page qui les porte et leurs propriétés de création, et les routes par nom.
	 *
	 * Les propriétés valent NULL quand ni la page ni la route n'en donnent, la liste du Lib s'appliquant
	 * alors, une liste quand elles sont écrites en clair, et FALSE quand une expression les donne.
	 *
	 * @return array{create: int[], doCreate: array<string, array{0: string, 1: string[]|false|null, 2: int}>, routes: array<string, int>}
	 */
	private static function scan(array $tokens): array {

		$create = [];
		$doCreate = [];
		$routes = [];
		$pageClass = NULL;
		$propertiesPage = NULL;

		foreach($tokens as $i => [$type, $text, $line]) {

			if($type === T_NEW and in_array($tokens[$i + 1][0], [T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED, T_STRING], TRUE) and str_ends_with($tokens[$i + 1][1], 'Page')) {

				$pageClass = ltrim($tokens[$i + 1][1], '\\');
				$propertiesPage = ($tokens[$i + 2][0] === '(') ? self::getProperties($tokens, self::getArgument($tokens, $i + 2, 'propertiesCreate', 1)) : NULL;

				continue;

			}

			if($type === T_DOUBLE_COLON and $tokens[$i + 2][0] === '(' and str_ends_with($tokens[$i - 1][1], 'Lib') and preg_match('/^(create\w*|duplicate)$/', $tokens[$i + 1][1]) === 1) {
				$create[] = $line;
				continue;
			}

			if($type !== T_OBJECT_OPERATOR or $tokens[$i + 2][0] !== '(') {
				continue;
			}

			$method = $tokens[$i + 1][1];

			if($method === 'doCreate' and $pageClass !== NULL) {

				$page = self::getArgument($tokens, $i + 2, 'page', 2);
				$properties = self::getProperties($tokens, self::getArgument($tokens, $i + 2, 'propertiesCreate', 1));

				if($page !== NULL and $tokens[$page[0]][0] !== T_CONSTANT_ENCAPSED_STRING) {
					throw new \Exception('page: non littéral ligne '.$line.', la sentinelle ne peut pas nommer la route');
				}

				$create[] = $line;
				$doCreate[($page === NULL) ? 'doCreate' : substr($tokens[$page[0]][1], 1, -1)] = [$pageClass, $properties ?? $propertiesPage, $line];

			} else if(in_array($method, ['post', 'write', 'match', 'http'], TRUE)) {

				// `match()` et `http()` prennent les méthodes en premier argument et les routes en second.
				$argument = self::getArgument($tokens, $i + 2, NULL, in_array($method, ['match', 'http'], TRUE) ? 1 : 0);

				foreach(self::getStrings($tokens, $argument) as $page) {

					$routes[$page] = $line;

					if(str_starts_with($page, 'doCreate')) {
						$create[] = $line;
					}

				}

			}

		}

		return ['create' => $create, 'doCreate' => $doCreate, 'routes' => $routes];

	}

	/**
	 * Les bornes d'un argument de l'appel ouvert en `$open`, désigné par son nom ou, à défaut, par son
	 * rang, au premier niveau de l'appel.
	 *
	 * @return array{0: int, 1: int}|null le premier et le dernier jeton de sa valeur
	 */
	private static function getArgument(array $tokens, int $open, ?string $name, int $position): ?array {

		$close = SentinelLib::getClosing($tokens, $open);
		$arguments = [];
		$depth = 0;
		$start = $open + 1;

		for($i = $open + 1; $i <= $close; $i++) {

			$type = $tokens[$i][0];

			if($depth === 0 and ($type === ',' or $i === $close)) {

				if($start < $i) {
					$arguments[] = [$start, $i - 1];
				}

				$start = $i + 1;

			} else if($type === '(' or $type === '[' or $type === '{') {
				$depth++;
			} else if($type === ')' or $type === ']' or $type === '}') {
				$depth--;
			}

		}

		foreach($arguments as $rank => [$first, $last]) {

			$named = ($tokens[$first][0] === T_STRING and $tokens[$first + 1][0] === ':');

			if($named and $tokens[$first][1] === $name) {
				return [$first + 2, $last];
			} else if($named === FALSE and $rank === $position) {
				return [$first, $last];
			}

		}

		return NULL;

	}

	/**
	 * Les propriétés de création d'un argument : NULL sans argument, la liste quand elle est écrite en
	 * clair, FALSE pour toute autre expression.
	 *
	 * @return string[]|false|null
	 */
	private static function getProperties(array $tokens, ?array $argument): array|false|null {

		if($argument === NULL) {
			return NULL;
		}

		[$first, $last] = $argument;

		if($tokens[$first][0] !== '[' or SentinelLib::getClosing($tokens, $first) !== $last) {
			return FALSE;
		}

		return self::getStrings($tokens, $argument);

	}

	/**
	 * Les chaînes littérales d'un argument, seule ou en tableau.
	 *
	 * @return string[]
	 */
	private static function getStrings(array $tokens, ?array $argument): array {

		if($argument === NULL) {
			return [];
		}

		$strings = [];

		for($i = $argument[0]; $i <= $argument[1]; $i++) {

			if($tokens[$i][0] === T_CONSTANT_ENCAPSED_STRING) {
				$strings[] = substr($tokens[$i][1], 1, -1);
			}

		}

		return $strings;

	}

	public function testDetector(): void {

		$code = <<<'PHP'
<?php
new \fixture\ThingPage(propertiesCreate: ['name'])
	->doCreate(fn() => throw new ReloadAction())
	->doCreate(function($data) {
		\fixture\OtherLib::create(new \fixture\Other());
	}, page: 'doCreateCopy', propertiesCreate: fn() => [])
	->doCreate(fn() => NULL, ['code', 'name'], 'doCreateCode')
	->post('doCreateMany', fn() => NULL)
	->post(['doHarvest', 'doCreateList'], fn() => NULL)
	->match(['post'], 'doCreateMatch', fn() => NULL)
	->get('create', fn() => \fixture\ThingLib::getById(1));
\fixture\ThingLib::duplicate(new \fixture\Thing());
\fixture\ThingLib::createForShop(new \fixture\Thing());
\fixture\ThingLib::created(new \fixture\Thing());
PHP;

		$scan = self::scan(SentinelLib::getTokens($code));

		$this->assertEquals([3, 4, 5, 7, 8, 9, 10, 12, 13, 14], $scan['create'], 'appels qui créent');
		$this->assertEquals([
			'doCreate' => ['fixture\ThingPage', ['name'], 3],
			'doCreateCopy' => ['fixture\ThingPage', FALSE, 4],
			'doCreateCode' => ['fixture\ThingPage', ['code', 'name'], 7],
		], $scan['doCreate'], 'routes doCreate, avec la liste de la page, une closure illisible, une liste positionnelle');
		$this->assertEquals(['doCreateMany' => 8, 'doHarvest' => 9, 'doCreateList' => 9, 'doCreateMatch' => 10], $scan['routes'], 'routes nommées, en tableau et par match()');

	}

	public function testGuard(): void {

		$this->assertTrue(self::isGuarded([['structure', 'name']], ['name'], []), 'colonnes postées et structure');
		$this->assertFalse(self::isGuarded([['structure', 'number']], ['name'], []), 'colonne posée par create()');
		$this->assertFalse(self::isGuarded([['key']], ['key'], ['key']), 'colonne nullable');
		$this->assertFalse(self::isGuarded([['name']], FALSE, []), 'propriétés données par une closure');
		$this->assertTrue(self::isGuarded([['compositionOf'], ['code']], ['code'], []), 'une contrainte suffit');

	}

}
?>