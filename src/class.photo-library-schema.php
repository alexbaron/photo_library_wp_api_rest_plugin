<?php

use ColorThief\ColorThief;

/**
 * Class PhotoLibrarySchema
 *
 * Cette classe est responsable de la préparation des données des images
 * pour les rendre conformes à un schéma spécifique.
 */
class PhotoLibrarySchema
{
	/**
	 * Constructeur de la classe PhotoLibrarySchema.
	 *
	 * @param mixed $schema
	 */
	public function __construct(
		protected ?PL_REST_DB $plRestDb = null,
		protected ?PL_COLOR_HANDLER $plColorHandler = null
	) {
		if (!$plColorHandler) {
			$this->plColorHandler = new PL_COLOR_HANDLER();
		}
	}

	final public function getWpdb()
	{
		return $this->plRestDb->getWpDb();
	}

	/**
	 * Prépare les données de toutes les images en un tableau conforme au schéma.
	 *
	 * @param array $pictures Un tableau d'objets représentant les images.
	 * @return array Un tableau de données des images conformes au schéma.
	 */
	public function prepareAllPicturesDataAsArray(array $pictures = array()): array
	{
		$data = array();
		if ($pictures) {
			foreach ($pictures as $picture) {
				$data[] = $this->preparePictureDataAsArray($picture);
			}
		}
		return $data;
	}

	/**
	 * getPalette color of image.
	 *
	 * @param mixed $picture
	 * @param mixed $area
	 * @return array|null
	 */
	public function getPalette($picture, $area = null): array|null
	{
		// check in db of cache
		if ($palette = $this->plRestDb->getPaletteFromDb($picture->id)) {
			return $palette;
		}

		try {
			$palette = $this->plColorHandler->extractPalette($picture, $area);
			$palette = $this->plRestDb->savePaletteMeta($picture->id, $palette);
		} catch (\Exception $e) {
			error_log($e->getMessage());
		}

		return $palette;
	}

	/**
	 * Prépare les données d'une image en un tableau conforme au schéma.
	 *
	 * @param stdClass $picture Un objet représentant une image.
	 * @return array Un tableau de données de l'image conforme au schéma.
	 */
	public function preparePictureDataAsArray(stdClass $picture): array
	{
		// Désérialiser les métadonnées de l'image
		$metadata = unserialize($picture->metadata);

		// Préparation du schéma
		$schema                    = array();
		$schema['id']              = $picture->id;
		$schema['width']           = $metadata['width'];
		$schema['height']          = $metadata['height'];
		$schema['src']             = $metadata['sizes'];
		$schema['src']['original'] = $this->plColorHandler->getSourceUrl($picture);
		$schema['metadata']        = null;

		if (
			isset($metadata['image_meta']['keywords']) && count($metadata['image_meta']['keywords'])
		) {
			$schema['keywords'] = $metadata['image_meta']['keywords'];
		}

		$schema['keywords'] = $metadata['image_meta']['keywords'];
		if ($picture->palette) {
			$schema['palette'] = unserialize($picture->palette);
		} elseif ($this->plRestDb) {
			$area = null;
			if ($metadata['width'] && $metadata['height']) {
				$area = $this->plColorHandler->calcArea(
					$metadata['width'],
					$metadata['height']
				);
			}

			$schema['palette'] = $this->getPalette($picture, $area);
		}
		if (isset($schema['palette'])) {
			$schema['brightest_color'] = $this->plColorHandler->getBrightestColor($schema['palette']);
		}
		$schema['author']      = 'stéphane wagner';
		$schema['alt']         = '';
		// $schema['metadata']    = $metadata['metadata'];
		$schema['filesize']    = $metadata['filesize'];
		$schema['title']       = $picture->title;
		$schema['description'] = $picture->description;

		return $schema;
	}
}
