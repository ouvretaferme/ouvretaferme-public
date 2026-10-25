<?php
namespace website;

class WebsiteLib extends WebsiteCrud {

	public static function getPropertiesCreate(?string $origin = NULL): array {
		return ['internalDomain', 'name', 'description'];
	}

	public static function getPropertiesUpdate(?string $origin = NULL): array {
		return ['internalDomain', 'domain', 'name', 'description', 'footer'];
	}

	public static function getPropertiesCustomize(): array {
		return ['customDesign', 'customDesignPosition', 'customWidth', 'customText', 'customColor', 'customLinkColor', 'customBackground', 'customFont', 'customTitleFont'];
	}

	public static function applyDesign(Website $eWebsite): void {

		if(\structure\StructureLib::getById($eWebsite['structure'])->acceptFeature(\structure\Structure::FEATURE_DESIGN)) {
			return;
		}

		foreach(array_diff(self::getPropertiesCustomize(), ['customDesign']) as $property) {
			$eWebsite[$property] = Website::model()->getDefaultValue($property);
		}

		if($eWebsite['customDesign']['id'] !== Website::model()->getDefaultValue('customDesign')) {
			$eWebsite['customDesign'] = DesignLib::getById(Website::model()->getDefaultValue('customDesign'));
		}

	}

	public static function getSitemap(Website $eWebsite): string {

		$cWebpage = WebpageLib::getByWebsite($eWebsite, onlyActive: TRUE, onlyPublic: TRUE);

		$sitemapArray = [];

		foreach($cWebpage as $eWebpage) {

			$sitemapArray[] = [
				'loc' => WebsiteUi::url($eWebsite, '/'.$eWebpage['url']),
				'lastmod' => NULL,
				'changefreq' => 'weekly',
				'priority' => '1'
			];

		}

		return \main\SitemapLib::generateSitemap($sitemapArray);

	}

	public static function getByStructure(\structure\Structure $eStructure): Website {

		return Website::model()
			->select(Website::getSelection())
			->whereStructure($eStructure)
			->get();

	}

	public static function getByInternalDomain(string $domain): Website {

		return Website::model()
			->select(Website::getSelection())
			->whereInternalDomain($domain)
			->get();

	}

	public static function getByDomain(string $domain): Website {

		return Website::model()
			->select(Website::getSelection())
			->whereDomain($domain)
			->get();

	}

	public static function create(Website $e): void {

		$e->expects(['structure']);

		$e->add([
			'description' => NULL
		]);

		Website::model()->beginTransaction();

		try {

			Website::model()->insert($e);

			$cTemplate = TemplateLib::getAutocreate($e['structure']);

			foreach($cTemplate as $eTemplate) {

				$online = self::isAutocreateOnline($e, $eTemplate);

				$eWebpage = new Webpage([
					'website' => $e,
					'structure' => $e['structure'],
					'template' => $eTemplate,
					'url' => $eTemplate['defaultUrl'],
					'title' => $eTemplate['defaultTitle'],
					'description' => $eTemplate['defaultDescription'],
					'content' => self::getAutocreateContent($e, $eTemplate),
					'status' => $online ? Webpage::ACTIVE : Webpage::INACTIVE
				]);

				Webpage::model()->insert($eWebpage);

				$eMenu = new Menu([
					'website' => $e,
					'structure' => $e['structure'],
					'webpage' => $eWebpage,
					'label' => $eTemplate['defaultLabel'],
					'status' => $online ? Menu::ACTIVE : Menu::INACTIVE
				]);

				Menu::model()->insert($eMenu);

			}

			Website::model()->commit();

		} catch(\DuplicateException $e) {

			Website::model()->rollBack();

			switch($e->getInfo()['duplicate']) {

				case ['structure'] :
					Website::fail('structure.duplicate');
					break;

				case ['internalDomain'] :
					Website::fail('internalDomain.duplicate');
					break;

				case ['domain'] :
					Website::fail('domain.duplicate');
					break;

			}

		}

	}

	/**
	 * Le site d'une AMAP prend comme accueil la description saisie à sa création. Celui d'un
	 * producteur garde l'accueil commun du gabarit, comme avant que la description y entre. Sans
	 * description, l'accueil commun reste aussi pour une AMAP.
	 */
	protected static function getAutocreateContent(Website $e, Template $eTemplate): ?string {

		if(
			$e['structure']->isAmap() and
			$eTemplate['fqn'] === 'homepage' and
			$e['description'] !== NULL
		) {
			return '<main><p>'.encode($e['description']).'</p></main>';
		}

		return $eTemplate['defaultContent'];

	}

	protected static function isAutocreateOnline(Website $e, Template $eTemplate): bool {

		return (
			$eTemplate['defaultContent'] === NULL or
			str_contains($eTemplate['defaultContent'], '@membership') === FALSE or
			WidgetLib::hasMembership($e)
		);

	}

	public static function update(Website $e, array $properties): void {

		if(in_array('domain', $properties)) {

			// Le domaine a changé
			if($e['domain'] !== NULL) {

				if(
					Website::model()
						->whereDomain($e['domain'])
						->exists($e) === FALSE
				) {
					$properties[] = 'domainStatus';
					$properties[] = 'domainTry';
					$e['domainStatus'] = Website::PENDING;
					$e['domainTry'] = 0;
				}

			} else {
				$properties[] = 'domainStatus';
				$e['domainStatus'] = NULL;
			}

		}

		parent::update($e, $properties);

	}

	public static function delete(Website $e): void {

		$e->expects(['id']);

		Website::model()->beginTransaction();

		Menu::model()
			->whereWebsite($e)
			->delete();

		Webpage::model()
			->whereWebsite($e)
			->delete();

		News::model()
			->whereWebsite($e)
			->delete();

		Website::model()->delete($e);

		Website::model()->commit();

	}

}
?>
