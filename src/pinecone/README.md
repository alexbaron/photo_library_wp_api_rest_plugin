# Intégration Pinecone pour Photo Library WordPress

Cette intégration permet d'utiliser Pinecone pour la recherche sémantique d'images dans votre plugin WordPress Photo Library.

## Installation

### 1. Configuration de Pinecone

1. Créez un compte sur [Pinecone.io](https://pinecone.io)
2. Créez un nouvel index avec les paramètres suivants :
   ```bash
   # Via Pinecone CLI (recommandé)
   pc index create \
     --name photo-library-index \
     --dimension 1536 \
     --metric cosine \
     --cloud aws \
     --region us-east-1 \
     --model llama-text-embed-v2 \
     --field_map text=content
   ```

3. Notez votre :
   - Clé API (commence par `pc-`)
   - Host de l'index (format : `your-index-abc123.svc.aped-4627-b74a.pinecone.io`)

### 2. Configuration WordPress

#### Option A : Interface d'administration
1. Allez dans **Réglages → Pinecone Settings** dans votre admin WordPress
2. Entrez votre clé API et l'host de l'index
3. Testez la connexion

#### Option B : Variables d'environnement
```php
// Dans wp-config.php ou .env
define('PINECONE_API_KEY', 'pc-your-api-key-here');
define('PINECONE_HOST', 'your-index-abc123.svc.aped-4627-b74a.pinecone.io');
```

#### Option C : Configuration par code
```php
require_once 'src/pinecone/class.pinecone-config.php';

PineconeConfig::setApiKey('pc-your-api-key-here');
PineconeConfig::setHost('your-index-abc123.svc.aped-4627-b74a.pinecone.io');
```

## Utilisation

### 1. Connexion basique

```php
require_once 'src/pinecone/class.photo-library-pinecone.php';

$pinecone = new PhotoLibraryPinecone();

// Test de connexion
if ($pinecone->testConnection()) {
    echo "Connexion réussie !";
}
```

### 2. Indexer une photo

```php
// Indexer une photo avec ses embeddings
$photo_id = 123;
$embedding = generate_image_embedding($photo_id); // Vous devez implémenter cette fonction
$metadata = [
    'title' => get_the_title($photo_id),
    'description' => get_post_field('post_content', $photo_id),
    'keywords' => ['nature', 'paysage', 'montagne'],
    'upload_date' => get_post_field('post_date', $photo_id)
];

$success = $pinecone->indexPhoto($photo_id, $embedding, $metadata);
```

### 3. Indexer plusieurs photos en lot

```php
$photos_data = [
    [
        'id' => 123,
        'embedding' => $embedding1,
        'metadata' => $metadata1
    ],
    [
        'id' => 124,
        'embedding' => $embedding2,
        'metadata' => $metadata2
    ]
];

$success = $pinecone->batchIndexPhotos($photos_data);
```

### 4. Recherche par similarité

```php
// Recherche avec un vecteur de requête
$query_vector = generate_query_embedding("landscape mountain");
$results = $pinecone->searchSimilarPhotos($query_vector, 10);

foreach ($results as $result) {
    echo "Photo ID: {$result['photo_id']}, Score: {$result['similarity_score']}\n";
}
```

### 5. Recherche par texte (avec embeddings intégrés)

```php
// Recherche textuelle avec reranking
$results = $pinecone->searchPhotosByText("beautiful sunset", 10, [], true);

foreach ($results as $result) {
    $title = $result['metadata']['title'] ?? 'Sans titre';
    echo "{$title} (Score: {$result['similarity_score']})\n";
}
```

### 6. Recherche avec filtres

```php
// Filtrer par métadonnées
$filter = [
    'upload_date' => ['$gte' => '2024-01-01'],
    'keywords' => ['$in' => ['nature', 'paysage']]
];

$results = $pinecone->searchPhotosByText("mountain", 10, $filter);
```

### 7. Indexation automatique

```php
// Configurer l'indexation automatique lors de l'upload
$pinecone = new PhotoLibraryPinecone();
$pinecone->addHooks();

// Hook pour générer les embeddings et indexer
add_action('photo_library_index_photo', function($photo_id) {
    // Votre logique de génération d'embeddings
    $embedding = generate_image_embedding($photo_id);

    if ($embedding) {
        $pinecone = new PhotoLibraryPinecone();
        $metadata = [
            'title' => get_the_title($photo_id),
            'description' => get_post_field('post_content', $photo_id)
        ];
        $pinecone->indexPhoto($photo_id, $embedding, $metadata);
    }
});
```

## Génération d'embeddings

Pinecone ne génère pas automatiquement les embeddings pour les images. Vous devez implémenter la génération d'embeddings en utilisant un service externe :

### Options recommandées :

1. **OpenAI CLIP API** (le plus simple)
2. **Google Vision AI**
3. **Azure Computer Vision**
4. **Modèle local CLIP**

### Exemple avec OpenAI CLIP :

```php
function generate_image_embedding($photo_id) {
    $image_path = get_attached_file($photo_id);

    // Encoder l'image en base64
    $image_data = base64_encode(file_get_contents($image_path));

    // Appel à l'API OpenAI CLIP (exemple conceptuel)
    $response = wp_remote_post('https://api.openai.com/v1/embeddings', [
        'headers' => [
            'Authorization' => 'Bearer ' . OPENAI_API_KEY,
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'model' => 'clip-vit-base-patch32',
            'input' => $image_data,
            'encoding_format' => 'float'
        ])
    ]);

    $data = json_decode(wp_remote_retrieve_body($response), true);
    return $data['data'][0]['embedding'] ?? null;
}
```

## Commandes WP-CLI

```bash
# Indexer toutes les photos non indexées
wp photo-library pinecone-index-all

# Réindexer toutes les photos
wp photo-library pinecone-reindex-all

# Obtenir les statistiques
wp photo-library pinecone-stats
```

## Filtres et opérateurs supportés

### Opérateurs de filtre :
- `$eq` : égal à
- `$ne` : différent de
- `$gt`, `$gte` : supérieur (ou égal)
- `$lt`, `$lte` : inférieur (ou égal)
- `$in` : dans la liste
- `$nin` : pas dans la liste
- `$exists` : le champ existe
- `$and`, `$or` : opérateurs logiques

### Exemples de filtres :

```php
// Photos uploadées après 2024
$filter = ['upload_date' => ['$gte' => '2024-01-01']];

// Photos avec mots-clés spécifiques
$filter = ['keywords' => ['$in' => ['nature', 'paysage']]];

// Filtres combinés
$filter = [
    '$and' => [
        ['upload_date' => ['$gte' => '2024-01-01']],
        ['keywords' => ['$in' => ['nature']]],
        ['file_size' => ['$gt' => 100000]]
    ]
];
```

## Bonnes pratiques

### 1. Indexation par lots
```php
// Traiter par petits lots pour éviter les timeouts
$photo_ids = $pinecone->getPhotosNeedingIndexing(1000);
$batch_size = 10;

for ($i = 0; $i < count($photo_ids); $i += $batch_size) {
    $batch = array_slice($photo_ids, $i, $batch_size);
    // Traiter le lot
}
```

### 2. Gestion d'erreurs
```php
$result = $pinecone->searchPhotosByText($query);

if ($result === false) {
    error_log("Erreur de recherche Pinecone");
    // Fallback vers recherche WordPress classique
} else {
    // Traiter les résultats
}
```

### 3. Cache des résultats
```php
$cache_key = 'pinecone_search_' . md5($query);
$results = get_transient($cache_key);

if ($results === false) {
    $results = $pinecone->searchPhotosByText($query);
    set_transient($cache_key, $results, HOUR_IN_SECONDS);
}
```

### 4. Monitoring
```php
// Surveiller les statistiques régulièrement
$stats = $pinecone->getIndexStats();
if ($stats && $stats['total_vectors'] > 100000) {
    // Considérer la mise à l'échelle ou l'optimisation
}
```

## Résolution de problèmes

### Erreurs communes :

1. **"Connection failed"** : Vérifiez votre clé API et l'host
2. **"Metadata too large"** : Limitez les métadonnées à 40KB par enregistrement
3. **"Batch too large"** : Réduisez la taille des lots (max 96 records/batch pour le texte)
4. **"Rate limit exceeded"** : Ajoutez des délais entre les requêtes

### Debug :
```php
// Activer les logs d'erreurs
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Logs WordPress
error_log("Message de debug Pinecone");
```

## Sécurité

- Ne jamais exposer les clés API dans le code frontend
- Utiliser des variables d'environnement pour les clés sensibles
- Valider toutes les entrées utilisateur avant les requêtes
- Limiter l'accès aux fonctions d'administration

## Performance

- Utiliser le reranking pour améliorer la qualité des résultats
- Mettre en cache les recherches fréquentes
- Indexer par petits lots en arrière-plan
- Surveiller l'utilisation de la bande passante

## Support

Pour plus d'informations :
- [Documentation officielle Pinecone](https://docs.pinecone.io/)
- [Guide de production Pinecone](https://docs.pinecone.io/guides/production/)
- [Limites de l'API Pinecone](https://docs.pinecone.io/reference/api/database-limits)
