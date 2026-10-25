<?php
/**
 * Les décisions prises sur le référentiel de variétés, une ligne chacune.
 *
 * Écrit par plant/configure/variety/arbitrageAi, appliqué par
 * variety/referenceEnrichAi puis variety/nameEnrichAi. Ne se vide jamais :
 * c'est lui qui porte une décision d'un environnement à l'autre.
 *
 * Chaque ligne décrit un état voulu, pas un geste, et se rejoue donc sans
 * effet :
 *
 *   reference  <espèce> <fqn>              <nom retenu>
 *   graphie    <espèce> <fqn>              <nom saisi qui lui revient>
 *   fusion     <espèce> <fqn abandonné>    <fqn qui le remplace>
 *   retrait    <espèce> <fqn>              <nom saisi qui ne lui revient plus>
 *   detachement <espèce> <fqn>             <nom saisi à ne plus rattacher>
 */
return [

];
