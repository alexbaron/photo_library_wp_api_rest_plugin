<?php

/**
 * Class PhotoLibraryRgbDistance
 *
 * Cette classe gère les opérations sur la table pl_rgb_distance
 * qui stocke les distances calculées entre les couleurs RGB.
 */
class PhotoLibraryRgbDistance
{
    private $table_name;
    private $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'pl_rgb_distance';
    }

    /**
     * Calcule la distance euclidienne entre deux couleurs RGB
     *
     * @param array $color1 Couleur 1 [r, g, b]
     * @param array $color2 Couleur 2 [r, g, b]
     * @return float Distance calculée
     */
    public function calculateEuclideanDistance($color1, $color2): float
    {
        $r_diff = $color1[0] - $color2[0];
        $g_diff = $color1[1] - $color2[1];
        $b_diff = $color1[2] - $color2[2];

        return sqrt($r_diff * $r_diff + $g_diff * $g_diff + $b_diff * $b_diff);
    }

    /**
     * Calcule la distance Delta E CIE76 entre deux couleurs RGB
     *
     * @param array $color1 Couleur 1 [r, g, b]
     * @param array $color2 Couleur 2 [r, g, b]
     * @return float Distance Delta E
     */
    public function calculateDeltaEDistance($color1, $color2): float
    {
        // Conversion RGB vers LAB simplifiée
        // Cette fonction pourrait être améliorée avec une conversion plus précise
        $lab1 = $this->rgbToLab($color1);
        $lab2 = $this->rgbToLab($color2);

        $l_diff = $lab1[0] - $lab2[0];
        $a_diff = $lab1[1] - $lab2[1];
        $b_diff = $lab1[2] - $lab2[2];

        return sqrt($l_diff * $l_diff + $a_diff * $a_diff + $b_diff * $b_diff);
    }

    /**
     * Conversion RGB vers LAB (simplifiée)
     *
     * @param array $rgb [r, g, b]
     * @return array [l, a, b]
     */
    private function rgbToLab($rgb): array
    {
        // Normalisation RGB (0-1)
        $r = $rgb[0] / 255.0;
        $g = $rgb[1] / 255.0;
        $b = $rgb[2] / 255.0;

        // Conversion simplifiée vers LAB
        // Dans une implémentation complète, il faudrait passer par XYZ
        $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
        $a = ($r - $g) * 0.5;
        $b_lab = ($g - $b) * 0.25;

        return [$l * 100, $a * 100, $b_lab * 100];
    }

    /**
     * Enregistre une distance calculée en base de données
     *
     * @param array $color1 Couleur 1 [r, g, b]
     * @param array $color2 Couleur 2 [r, g, b]
     * @param float $distance Distance calculée
     * @param string $algorithm Algorithme utilisé
     * @return bool|int ID de l'enregistrement ou false en cas d'erreur
     */
    public function saveDistance($color1, $color2, $distance, $algorithm = 'euclidean')
    {
        $data = array(
            'color1_r' => (int) $color1[0],
            'color1_g' => (int) $color1[1],
            'color1_b' => (int) $color1[2],
            'color2_r' => (int) $color2[0],
            'color2_g' => (int) $color2[1],
            'color2_b' => (int) $color2[2],
            'distance' => $distance,
            'algorithm' => $algorithm
        );

        $result = $this->wpdb->insert($this->table_name, $data);

        if ($result === false) {
            return false;
        }

        return $this->wpdb->insert_id;
    }

    /**
     * Récupère une distance depuis la base de données
     *
     * @param array $color1 Couleur 1 [r, g, b]
     * @param array $color2 Couleur 2 [r, g, b]
     * @param string $algorithm Algorithme utilisé
     * @return float|null Distance ou null si non trouvée
     */
    public function getDistance($color1, $color2, $algorithm = 'euclidean'): ?float
    {
        $query = $this->wpdb->prepare(
            "SELECT distance FROM {$this->table_name}
			WHERE color1_r = %d AND color1_g = %d AND color1_b = %d
			AND color2_r = %d AND color2_g = %d AND color2_b = %d
			AND algorithm = %s",
            $color1[0],
            $color1[1],
            $color1[2],
            $color2[0],
            $color2[1],
            $color2[2],
            $algorithm
        );

        $result = $this->wpdb->get_var($query);

        return $result !== null ? (float) $result : null;
    }

    /**
     * Calcule et sauvegarde la distance entre deux couleurs si elle n'existe pas déjà
     *
     * @param array $color1 Couleur 1 [r, g, b]
     * @param array $color2 Couleur 2 [r, g, b]
     * @param string $algorithm Algorithme à utiliser
     * @return float Distance calculée
     */
    public function getOrCalculateDistance($color1, $color2, $algorithm = 'euclidean'): float
    {
        // Vérifier si la distance existe déjà
        $existing_distance = $this->getDistance($color1, $color2, $algorithm);

        if ($existing_distance !== null) {
            return $existing_distance;
        }

        // Calculer la distance selon l'algorithme
        switch ($algorithm) {
            case 'delta_e':
                $distance = $this->calculateDeltaEDistance($color1, $color2);
                break;
            case 'euclidean':
            default:
                $distance = $this->calculateEuclideanDistance($color1, $color2);
                break;
        }

        // Sauvegarder la distance
        $this->saveDistance($color1, $color2, $distance, $algorithm);

        return $distance;
    }

    /**
     * Trouve les couleurs les plus proches d'une couleur donnée
     *
     * @param array $target_color Couleur cible [r, g, b]
     * @param int $limit Nombre maximum de résultats
     * @param string $algorithm Algorithme utilisé
     * @return array Liste des couleurs proches avec leurs distances
     */
    public function findSimilarColors($target_color, $limit = 10, $algorithm = 'euclidean'): array
    {
        $query = $this->wpdb->prepare(
            "SELECT color2_r, color2_g, color2_b, distance
			FROM {$this->table_name}
			WHERE color1_r = %d AND color1_g = %d AND color1_b = %d
			AND algorithm = %s
			ORDER BY distance ASC
			LIMIT %d",
            $target_color[0],
            $target_color[1],
            $target_color[2],
            $algorithm,
            $limit
        );

        return $this->wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Supprime les entrées plus anciennes qu'une certaine date
     *
     * @param int $days Nombre de jours à conserver
     * @return int Nombre d'entrées supprimées
     */
    public function cleanOldEntries($days = 30): int
    {
        $query = $this->wpdb->prepare(
            "DELETE FROM {$this->table_name}
			WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        );

        return $this->wpdb->query($query);
    }
}
