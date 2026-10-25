<?php
new AdaptativeView('/doc/import:series', function($data, DocTemplate $t) {

	$t->title = s("Importer un plan de culture");
	$t->subTitle = s("Vous pouvez importer un plan de culture au format CSV. C'est une fonctionnalité bien pratique si vous préférez concevoir votre plan de culture avec un tableur et le visualiser ensuite sur {siteName} !");

	$t->menuSelected = 'importSeries';

	echo '<div class="util-block">';

		echo '<h2>'.s("Comment importer un plan de culture ?").'</h2>';
		echo '<p>'.s("Importer un plan de culture revient à importer sur {siteName} un fichier CSV qui contient des listes de séries que vous souhaitez ajouter à votre plan de culture. Deux formats sont utilisables pour importer des séries :").'</p>';
		echo '<ul>';
			echo '<li>'.s("le format <b>{siteName}</b>").'</li>';
			echo '<li>'.s("le format <b>Brinjel</b>, qui permet d'importer vos séries depuis ce logiciel ou depuis Qrop").'</li>';
			echo '<li>'.s("un import depuis <b>Elzeard</b> est également possible avec un convertisseur proposé par Alexis, qui transformera le format d'export de Elzeard vers le format de {siteName} (<link>voir le script en Python sur Github</link>)", ['link' => '<a href="https://github.com/atantet/elzeard2otf/">']).'</li>';
		echo '</ul>';

	echo '</div>';

	echo '<h2>'.s("Importer un fichier CSV au format {siteName}").'</h2>';
	echo '<p>';
	echo s("Le fichier CSV que vous importez doit comporter une ligne par culture, et les colonnes de ce fichier doivent correspondre à la liste des données à fournir décrite plus bas. Si vous ne respectez pas ce format, vous obtiendrez un résultat qui ne sera pas satisfaisant.");
	echo '</p>';
	echo \main\CsvUi::getSyntaxInfo();
	echo '<p>';
	echo '<a href="'.Asset::getPath('series', 'series.csv').'" data-ajax-navigation="never" class="btn btn-outline-primary">'.s("Télécharger un exemple CSV").'</a>';
	echo '</p>';
	echo '<br/>';
	echo '<h3>'.s("Liste des données à fournir").'</h3>';

	$list = [
		[
			s("Saison").' '.\util\FormUi::asterisk(),
			'season',
			s("La saison à laquelle intégrer cette culture"),
			2024
		],
		[
			s("Numéro de série"),
			'series_id',
			s("Les cultures avec un numéro de série identique seront rassemblées dans la même série et traitées comme des associations de culture"),
			123
		],
		[
			s("Nom de la série").' '.\util\FormUi::asterisk(),
			'series_name',
			s("Le nom de la série pour cette culture"),
			s("Carotte primeur")
		],
		[
			s("Mode de culture").' '.\util\FormUi::asterisk(),
			'mode',
			s("Les valeurs possibles :").
			'<ul>'.
			'<li>'.s("{value} → plein-champ", '<span class="util-literal">'.\series\Series::OPEN_FIELD.'</span>').'</li>'.
			'<li>'.s("{value} → sous abri", '<span class="util-literal">'.\series\Series::GREENHOUSE.'</span>').'</li>'.
			'<li>'.s("{value} → mixte", '<span class="util-literal">'.\series\Series::MIX.'</span>').'</li>'.
			'</ul>',
			'open-field'
		],
		[
			s("Utilisation du sol").' '.\util\FormUi::asterisk(),
			'use',
			s("Les valeurs possibles :").
			'<ul>'.
			'<li>'.s("{value} → Culture sur planches", '<span class="util-literal">'.\series\Series::BED.'</span>').'</li>'.
			'<li>'.s("{value} → Culture sur surface libre", '<span class="util-literal">'.\series\Series::BLOCK.'</span>').'</li>'.
			'</ul>',
			'bed'
		],
		[
			s("Espèce").' '.\util\FormUi::asterisk(),
			'species',
			s("Le nom de l'espèce doit correspondre à <link>une espèce existante de votre ferme</link>, seules les espèces annuelles sont acceptées.", ['link' => $data->eStructure->empty() ? NULL : '<a href="'.\plant\PlantUi::urlManage($data->eStructure).'">']),
			s("Carotte")
		],
		[
			s("Implantation").' '.\util\FormUi::asterisk(),
			'planting_type',
			s("Les valeurs possibles :").
			'<ul>'.
			'<li>'.s("{value} → semis direct", '<span class="util-literal">'.\series\Cultivation::SOWING.'</span>').'</li>'.
			'<li>'.s("{value} → plant autoproduit", '<span class="util-literal">'.\series\Cultivation::YOUNG_PLANT.'</span>').'</li>'.
			'<li>'.s("{value} → plant acheté", '<span class="util-literal">'.\series\Cultivation::YOUNG_PLANT_BOUGHT.'</span>').'</li>'.
			'</ul>',
			'young-plant'
		],
		[
			s("Nombre de graines par trou"),
			'seeds_per_hole',
			s("Pris en compte dans le cas d'implantation par semis direct ou plant autoproduit"),
			3
		],
		[
			s("Plateau de semis"),
			'young_plants_tray',
			s("Pris en compte uniquement dans le cas d'implantation par plant autoproduit et le plateau de semis doit avoir été préalablement créé dans la <link>liste du matériel de votre ferme<link>", ['link' => $data->eStructure->empty() ? NULL : '<a href="/work/tool:manage?structure='.$data->eStructure['id'].'">']),
			s("Plaque de 150")
		],
		[
			s("Date de semis"),
			'sowing_date',
			s("Pris en compte uniquement dans le cas d'implantation par semis direct et des plant autoproduit (format AAAA-MM-JJ)"),
			'2024-01-25'
		],
		[
			s("Date de plantation"),
			'planting_date',
			s("Pris en compte uniquement dans le cas d'implantation par plant autoproduit ou acheté (format AAAA-MM-JJ)"),
			'2024-03-05'
		],
		[
			s("Début de récolte"),
			'first_harvest_date',
			s("Format AAAA-MM-JJ"),
			'2024-05-15'
		],
		[
			s("Fin de récolte"),
			'last_harvest_date',
			s("Format AAAA-MM-JJ"),
			'2024-06-15'
		],
		[
			s("Objectif de surface en m²"),
			'block_area',
			s("Pris en compte uniquement dans le cas de culture sur surface libre"),
			'100'
		],
		[
			s("Densité d'implantation par m²"),
			'block_density',
			s("Pris en compte uniquement dans le cas de culture sur surface libre, ne pas être utilisé simultanément avec <pre>block_spacing_rows</pre> et <pre>block_spacing_plants</pre>"),
			'500'
		],
		[
			s("Espacement entre les rangs en cm"),
			'block_spacing_rows',
			s("Pris en compte uniquement dans le cas de culture sur surface libre, ne pas être utilisé simultanément avec <pre>block_density</pre>"),
			'40'
		],
		[
			s("Espacement sur le rang en cm"),
			'block_spacing_plants',
			s("Pris en compte uniquement dans le cas de culture sur surface libre, ne pas être utilisé simultanément avec <pre>block_density</pre>"),
			'15'
		],
		[
			s("Objectif de longueur de planches en mL"),
			'bed_length',
			s("Pris en compte uniquement dans le cas de culture sur planches"),
			'100'
		],
		[
			s("Densité d'implantation par m²"),
			'bed_density',
			s("Pris en compte uniquement dans le cas de culture sur planches, ne pas être utilisé simultanément avec <pre>bed_rows</pre> et <pre>bed_spacing_plants</pre>"),
			'500'
		],
		[
			s("Nombre de rangs par planche"),
			'bed_rows',
			s("Pris en compte uniquement dans le cas de culture sur planches, ne pas être utilisé simultanément avec <pre>bed_density</pre>"),
			'3'
		],
		[
			s("Espacement sur le rang en cm"),
			'bed_spacing_plants',
			s("Pris en compte uniquement dans le cas de culture sur planches, ne pas être utilisé simultanément avec <pre>bed_density</pre>"),
			'15'
		],
		[
			s("Série clôturée"),
			'finished',
			s("<example>yes</example> si la série est clôturée, <example>no</example> sinon", ['example' => '<span class="util-literal">']),
			'false'
		],
		[
			s("Unité de récolte"),
			'harvest_unit',
			s("Les valeurs possibles :").
			'<ul>'.
			'<li>'.s("{value} → au kg", '<span class="util-literal">'.\series\Cultivation::KG.'</span>').'</li>'.
			'<li>'.s("{value} → à la pièce", '<span class="util-literal">'.\series\Cultivation::UNIT.'</span>').'</li>'.
			'<li>'.s("{value} → à la botte", '<span class="util-literal">'.\series\Cultivation::BUNCH.'</span>').'</li>'.
			'</ul>',
			'bunch'
		],
		[
			s("Objectif de rendement par m²"),
			'yield_expected_area',
			s("Le rendement attendu pour cette culture en fonction de l'unité de récolte choisie"),
			'3.5'
		],
		[
			s("Nom de la variété"),
			'variety_name',
			s("Une variété utilisée pour cette culture. Il est possible d'enchainer les colonnes <i>variety_name</i> et <i>variety_part</i> autant de fois que nécessaire dans le fichier CSV à importer."),
			'Andine cornue'
		],
		[
			s("Fréquence de la variété en %"),
			'variety_part',
			s("La part de la variété dans la culture. La somme des variétés doit être égale à 100."),
			'25'
		],
	];

	echo \main\CsvUi::getDataList($list);

	echo '<h2>'.s("Importer un fichier CSV depuis Qrop").'</h2>';

	echo '<p>'.s("Pour récupérer le fichier CSV de Qrop à importer sur {siteName}, il vous suffit de sélectionner <i>Exporter le plan de culture</i>.").'</p>';
	echo Asset::image('main', 'doc/import-qrop.png');
	echo '<p>'.s("Notez que vous aurez probablement des corrections à faire dans le fichier CSV issu de Qrop, notamment au niveau des unités de récolte ou des espèces. {siteName} vous fera un rapport des modifications à effectuer après chargement de votre fichier.").'</p>';

	echo '<h2>'.s("Importer un fichier CSV depuis Brinjel").'</h2>';

	echo '<p>'.s("Pour récupérer le fichier CSV de Brinjel à importer sur {siteName} :").'</p>';
	echo '<ul>';
	echo '<li>'.s("Allez dans <b>Paramètres</b>").'</li>';
	echo '<li>'.s("Dans la section <b>Données de la ferme</b>, téléchargez le <b>Plan de culture seul</b>").'</li>';
	echo '</ul>';
	echo '<p>'.s("Notez que vous aurez probablement des corrections à faire dans le fichier CSV issu de Brinjel, notamment au niveau des unités de récolte ou des espèces. {siteName} vous fera un rapport des modifications à effectuer après chargement de votre fichier.").'</p>';
	echo '<p>';
	echo '<a href="https://app.brinjel.com/" class="btn btn-outline-primary" target="_blank">'.s("Aller sur Brinjel").'</a> ';
	echo '</p>';

});
?>
