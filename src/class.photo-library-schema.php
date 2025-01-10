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
	protected $plRestDb;
	/**
	 * Constructeur de la classe PhotoLibrarySchema.
	 *
	 * @param mixed $schema
	 */
	public function __construct(PL_REST_DB $pL_REST_DB = NULL)
	{
		if ($pL_REST_DB) {
			$this->plRestDb = $pL_REST_DB;
		}
	}

	final function getWpdb()
	{
		return $this->plRestDb->getWpDb();
	}

	/**
	 * Prépare les données de toutes les images en un tableau conforme au schéma.
	 *
	 * @param array $pictures Un tableau d'objets représentant les images.
	 * @return array Un tableau de données des images conformes au schéma.
	 */
	public function prepareAllPicturesDataAsArray(array $pictures = []): array
	{
		$data = [];
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
	public function getPalette($picture, $area = null)
	{
		if ($palette = $this->plRestDb->getPalette($picture->id)) {
			return $palette;
		}

		try {
			$palette =  ColorThief::getPalette($this->getSourceUrl(
				$picture,
				5,
				5,
				'array',
				$area
			));

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
		$schema = [];
		$schema['id'] = $picture->id;
		$schema['width'] = $metadata['width'];
		$schema['height'] = $metadata['height'];
		$schema['src'] = $metadata['sizes'];
		$schema['src']['original'] = $this->getSourceUrl($picture);

		if (
			isset($metadata['image_meta']['keywords']) &&  count($metadata['image_meta']['keywords'])
		) {
			$schema['keywords'] = $metadata['image_meta']['keywords'];
		}

		$schema['keywords'] = $metadata['image_meta']['keywords'];
		if ($picture->palette) {
			$schema['palette'] = unserialize($picture->palette);
		} else {
			if ($this->plRestDb) {

				$area = null;
				if ($metadata['width'] && $metadata['height']) {
					$area = $this->calcArea(
						$metadata['width'],
						$metadata['height']
					);
				}

				$schema['palette'] = $this->getPalette($picture, $area);
			}
		}
		if (isset($schema['palette'])) {
			$schema['brightest_color'] = $this->getBrightestColor($schema['palette']);
		}
		$schema['author'] = 'stéphane wagner';
		$schema['alt'] = '';
		$schema['metadata'] = $metadata['metadata'];
		$schema['filesize'] = $metadata['filesize'];
		$schema['title'] = $picture->title;

		return $schema;
	}

	/**
	 * Calculation of the area to get the picture palette.
	 *
	 * @param int $width
	 * @param mixed $height
	 * @return array|null
	 */
	public function calcArea(int $width = 0, $height = 0)
	{

		if ($width < 1 || $height < 1) {
			return null;
		}

		$area = [
			(($width / 3) * 2) + 20,
			$height * 0.75,
			$width,
			$height,
		];

		return $area;
	}

	public function calculateBrightness($color)
	{
		list($R, $G, $B) = $color;
		return (299 * $R + 587 * $G + 114 * $B) / 1000;
	}

	public function getBrightestColor(array $palette = [])
	{

		$brightnessArray = array_map([$this, 'calculateBrightness'], $palette);

		$maxBrightnessIndex = array_reduce(array_keys($brightnessArray), function ($carry, $key) use ($brightnessArray) {
			return $brightnessArray[$key] > $brightnessArray[$carry] ? $key : $carry;
		}, 0);

		return $palette[$maxBrightnessIndex];
	}

	/**
	 * Summary of getSourceUrl
	 * @param stdClass $picture
	 * @return string
	 */
	public function getSourceUrl(stdClass $picture): string
	{
		return urldecode(str_replace(
			'phototheque-wp.ddev.site',
			'www.photographie.stephanewagner.com',
			$picture->img_url
		));
	}
}
