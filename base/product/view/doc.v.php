<?php
new AdaptativeView('/doc/import:products', function($data, DocTemplate $t) {

	$t->title = s("Importer des produits");
	$t->subTitle = s("Vous pouvez importer des produits au format CSV. Cette fonctionnalité peut vous être utile si vous utilisiez d'autres logiciels avant {siteName} ou si vous voulez concevoir plus facilement votre gamme de produits sur tableur !");

	$t->menuSelected = 'importProducts';

	echo '<h2>'.s("Importer des produits au format CSV").'</h2>';
	echo '<p>';
		echo s("Les produits que vous importez au format CSV sont ajoutés à la liste de vos produits existants, à l'exception de ceux que vous tentez d'importer sous une référence déjà existante. Les produits concernés ne seront pas ajoutés une deuxième fois mais seront modifiés avec les nouvelles valeurs, à l'exception de l'unité de vente qui ne peut pas être modifiée par un import.");
	echo '</p>';
	echo '<p>';
		echo '<b>'.s("Si vous réimportez un produit avec une référence identique, toutes les valeurs seront mises à jour y compris celles qui ne sont pas présentes dans votre fichier CSV. Soyez donc vigilant car si vous importez un fichier sans la colonne <i>marks</i> par exemple, tous les produits réimportés avec une référence identique perdront leur signe de qualité.").'</b>';
	echo '</p>';
	echo '<p>';
		echo s("Le fichier CSV que vous importez doit comporter une ligne par produit, et les colonnes de ce fichier doivent correspondre à la liste des données à fournir décrite plus bas.");
	echo '</p>';
	echo \main\CsvUi::getSyntaxInfo();
	echo '<p>';
		echo '<a href="'.Asset::getPath('product', 'products.csv').'" data-ajax-navigation="never" class="btn btn-outline-primary">'.s("Télécharger un exemple CSV").'</a>';
	echo '</p>';
	echo '<div class="util-block-optional mt-2">';
		echo '<h4>'.s("Vous utilisiez Elzeard ?").'</h4>';
		echo s("Un import des produits depuis <b>Elzeard</b> est également possible avec un convertisseur proposé par Alexis, qui transformera le format d'export de Elzeard vers le format de {siteName} (<link>voir le script en Python sur Github</link>)", ['link' => '<a href="https://github.com/atantet/elzeard2otf/">']);
	echo '</div>';
	echo '<br/>';
	echo '<h3>'.s("Liste des données à fournir").'</h3>';


	$profiles = '';
	foreach(\product\Product::getProfiles('import') as $profile) {

		$profiles .= '<li><span class="util-literal">'.\product\CsvLib::getProductProfiles()[$profile].'</span> → '.\product\ProductUi::p('profile')->values[$profile].'</li>';

	}


	$qualities = '';
	foreach(\product\Product::getQualities() as $set => $fqn) {
		$qualities .= '<li>';
			$qualities .= s("{value} → {label}", [
				'label' => \product\ProductUi::getQualitiesLabels()[$set],
				'value' => '<span class="util-literal">'.$fqn.'</span>'
			]);
		$qualities .= '</li>';
	}

	$list = [
		[
			s("Type de produit").' '.\util\FormUi::asterisk(),
			'type',
			s("Les valeurs possibles :").
			'<ul>'.
				$profiles.
			'</ul>',
			'plant'
		],
		[
			s("Nom du produit").' '.\util\FormUi::asterisk(),
			'name',
			s("Le nom du produit"),
			s("Tomate")
		],
		[
			s("Référence du produit"),
			'reference',
			s("Une référence interne pour le produit (uniquement des chiffres, des lettres et des tirets). Une même référence ne peut pas être utilisée pour plusieurs produits."),
			s("TOMCDB")
		],
		[
			s("Unité de vente"),
			'unit',
			s("L'unité de vente doit correspondre à <link>une des unités de vente de votre ferme</link>. Vous devez utiliser le nom de l'unité au singulier.", ['link' => $data->eStructure->empty() ? NULL : '<a href="/product/unit:manage?structure='.$data->eStructure['id'].'">']),
			'kg'
		],
		[
			s("Prix pour les particuliers"),
			'price_private',
			s("Le prix en € TTC du produit pour les particuliers.<br/>Vous devez renseigner au moins un prix, qu'il soit professionnel ou particulier, pour votre produit."),
			'5'
		],
		[
			s("Prix pour les professionnels"),
			'price_pro',
			s("Le prix en € HT du produit pour les professionnels.<br/>Vous devez renseigner au moins un prix, qu'il soit professionnel ou particulier, pour votre produit."),
			'3'
		],
		[
			s("Taux de TVA").' '.\util\FormUi::asterisk(),
			'vat_rate',
			s("Le taux de TVA du produit (peut être laissé à 0 si vous avez indiqué que votre ferme n'est pas redevable de la TVA)"),
			'5,5'
		],
		[
			s("Complément d'information sur le produit"),
			'additional',
			s("Un court texte pour compléter le nom du produit"),
			s("Environ 400 grammes")
		],
		[
			s("Origine du produit"),
			'origin',
			s("L'origine géographique du produit"),
			s("La ferme du voisin (63)")
		],
		[
			s("Signes de qualité"),
			'marks',
			s("Les signes de qualité, séparés par des virgules, parmi :").
			'<ul>'.
				'<li>'.s("{value} → Agriculture biologique", '<span class="util-literal">organic</span>').'</li>'.
				'<li>'.s("{value} → En conversion vers l'agriculture biologique", '<span class="util-literal">'.\product\Product::CONVERSION.'</span>').'</li>'.
					$qualities.
			'</ul>',
			'organic, aop'
		]
	];

	echo \main\CsvUi::getDataList($list);

	echo '<br/>';
	echo '<h3>'.s("Autres données que vous pouvez fournir").'</h3>';

	echo '<p class="util-info">'.s("Certaines données qui ne s'appliquent qu'à certains profils de produits peuvent être ajoutées à votre fichier CSV.<br/>Par exemple, la colonne pour définir la variété d'un produit ne sera prise en compte que pour les produits d'origine végétale.").'</p>';


	$apply = function($property) {

		$values = '';
		foreach(\product\Product::getProfiles($property) as $profile) {
			$values .= ' <span class="util-literal">'.\product\ProductUi::p('profile')->values[$profile].'</span>';
		}

		return '<div class="util-annotation">'.s("Utilisable pour : {values}",['values' => $values]).'</div>';

	};

	$list = [
		[
			s("Mode de distribution"),
			'distribution',
			s("Le mode de distribution du produit parmi :").
			'<ul>'.
				'<li>'.s("{value} → Produit issu de votre production", '<span class="util-literal">'.\product\Product::PRODUCTION.'</span>').'</li>'.
				'<li>'.s("{value} → Produit en achat-revente", '<span class="util-literal">'.\product\Product::RESALE.'</span>').'</li>'.
			'</ul>'.
			s("Si ce champ n'est pas fourni, le produit sera considéré comme issu de votre propre production.").$apply('distribution'),
			'production'
		],
		[
			s("Espèce"),
			'species',
			s("Le nom de l'espèce dont fait partie ce produit et qui doit correspondre à <link>une espèce existante de votre ferme</link>.", ['link' => $data->eStructure->empty() ? NULL : '<a href="'.\plant\PlantUi::urlManage($data->eStructure).'">']).$apply('plant'),
			s("Tomate")
		],
		[
			s("Variété"),
			'variety',
			s("Un nom de variété").$apply('variety'),
			s("Coeur de boeuf")
		],
		[
			s("Surgelé"),
			'frozen',
			s("<example>yes</example> si le produit est vendu surgelé, <example>no</example> ou vide sinon", ['example' => '<span class="util-literal">']).$apply('frozen'),
			'yes'
		],
		[
			s("Conditionnement"),
			'packaging',
			s("Le conditionnement du produit").$apply('packaging'),
			s("Pack de 6 bouteilles")
		],
		[
			s("Composition"),
			'composition',
			s("La composition du produit").$apply('ingredients'),
			s("Purée de noisette (90 %), Sucre de canne (10 %)")
		],
		[
			s("Allergènes"),
			'allergen',
			s("Les allergènes du produits").$apply('allergens'),
			s("Traces de fruits à coques")
		],
		[
			s("Description"),
			'description',
			s("La description du produit en texte simple"),
			s("Facile à cuisiner, cette pomme de terre ravira vos papilles.")
		],
	];

	echo \main\CsvUi::getDataList($list);

});
?>
