<?php

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
	public function __construct() {}

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
	 * Prépare les données d'une image en un tableau conforme au schéma.
	 *
	 * @param stdClass $picture Un objet représentant une image.
	 * @return array Un tableau de données de l'image conforme au schéma.
	 */
	public function preparePictureDataAsArray(stdClass $picture): array
	{
		// Désérialiser les métadonnées de l'image
		$metadata = unserialize($picture->meta_value);

		// Préparation du schéma
		$schema = [];
		$schema['id'] = $picture->id;
		$schema['width'] = $metadata['width'];
		$schema['height'] = $metadata['height'];
		$schema['src'] = $metadata['sizes'];
		$schema['src']['original'] = urldecode(str_replace(
			'phototheque-wp.ddev.site',
			'www.photographie.stephanewagner.com',
			$picture->img_url
		));
		$schema['author'] = 'stéphane wagner';
		$schema['alt'] = '';
		$schema['metadata'] = $metadata['image_meta'];
		$schema['filesize'] = $metadata['filesize'];
		$schema['title'] = $picture->title;

		return $schema;
	}
}
