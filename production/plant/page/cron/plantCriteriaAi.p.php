<?php
/**
 * Range sur chaque espèce de référence les critères de notation que la communauté
 * retient le plus souvent pour elle.
 *
 * À la quinzaine, parce que le classement ne bouge qu'au rythme des choix qui
 * arrivent : sur les 90 structures qui avaient voté au 1er septembre 2026, il faut
 * plusieurs dizaines de nouveaux choix pour déplacer un critère d'un rang. Plus
 * souvent ne changerait rien ; plus rarement laisserait la carte de notation servir
 * un classement que les votes ont déjà démenti.
 *
 * De nuit, le dépouillement portant sur toutes les structures.
 */
new Page()
	->cron('index', function($data) {

		\plant\CriterionLib::synchronizeReference();

	}, interval: '0 3 1,15 * *');
?>
