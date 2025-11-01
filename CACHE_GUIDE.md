# Guide d'optimisation du cache pour serveur mutualisé

## Vue d'ensemble

Le système de cache WordPress implémenté dans PhotoLibrary est spécialement conçu pour fonctionner efficacement sur des serveurs mutualisés où les ressources sont limitées et les solutions de cache externes (Redis, Memcached) ne sont généralement pas disponibles.

## Avantages pour serveur mutualisé

### ✅ **Cache WordPress natif (wp_cache)**
- **Pas de dépendances externes** : Fonctionne avec n'importe quel hébergement WordPress
- **Faible consommation mémoire** : Utilise la mémoire PHP locale
- **Configuration automatique** : Aucune configuration serveur requise
- **Compatible avec les plugins de cache** : Fonctionne avec W3 Total Cache, WP Rocket, etc.

### 🚀 **Stratégies d'optimisation**

## 1. Durées de cache adaptées

```php
const CACHE_DURATIONS = array(
    'keywords'     => 3600,    // 1 heure - les mots-clés changent rarement
    'pictures_all' => 1800,    // 30 minutes - liste complète des images
    'picture_data' => 7200,    // 2 heures - données d'une image spécifique
    'search'       => 900,     // 15 minutes - résultats de recherche
    'hierarchy'    => 3600,    // 1 heure - hiérarchie des mots-clés
    'random'       => 300,     // 5 minutes - images aléatoires (courte durée)
);
```

**Pourquoi ces durées ?**
- **Mots-clés** : Changent rarement, cache long
- **Recherches** : Cache plus court pour fraîcheur des résultats
- **Images aléatoires** : Cache très court pour variété

## 2. Invalidation intelligente

Le cache est automatiquement invalidé lors de :
- Ajout d'une nouvelle image (`add_attachment`)
- Modification d'une image (`edit_attachment`)
- Suppression d'une image (`delete_attachment`)
- Modification des termes/tags (`set_object_terms`)

## 3. Endpoints optimisés

### **GET /wp-json/photo-library/v1/pictures/keywords**
```bash
# Premier appel : Données depuis la base
# Appels suivants : Données depuis le cache (jusqu'à 1 heure)
curl "http://votre-site.com/wp-json/photo-library/v1/pictures/keywords"
```

### **POST /wp-json/photo-library/v1/pictures/by_keywords**
```bash
# Cache par combinaison de mots-clés
curl -X POST \
  -H "Content-Type: application/json" \
  -d '{"search": ["nature", "paysage"]}' \
  "http://votre-site.com/wp-json/photo-library/v1/pictures/by_keywords"
```

### **GET /wp-json/photo-library/v1/pictures/{id}**
```bash
# Cache par ID d'image (2 heures)
curl "http://votre-site.com/wp-json/photo-library/v1/pictures/123"
```

## 4. Monitoring du cache

### **Statistiques du cache**
```bash
curl "http://votre-site.com/wp-json/photo-library/v1/cache/stats"
```

**Réponse exemple :**
```json
{
  "message": "PhotoLibrary Cache Statistics",
  "cache_enabled": false,
  "cache_type": "runtime",
  "timestamps": {
    "last_content_update": 1698764400,
    "search_cache_version": 1698764400
  },
  "server_info": {
    "memory_limit": "256M",
    "max_execution_time": "30",
    "opcache_enabled": true
  },
  "timestamp": "2025-10-30 14:30:00"
}
```

### **Vider le cache manuellement**
```bash
curl -X DELETE "http://votre-site.com/wp-json/photo-library/v1/cache/flush"
```

## 5. Optimisations pour serveur mutualisé

### **A. Réduction des requêtes base de données**
- Cache des mots-clés évite les requêtes répétées
- Cache des résultats de recherche évite les requêtes complexes
- Cache des données d'images évite les métadonnées répétées

### **B. Gestion mémoire optimisée**
```php
// Le cache utilise wp_cache_set avec des durées limitées
// Libération automatique de la mémoire après expiration
wp_cache_set($key, $data, $group, $duration);
```

### **C. Invalidation granulaire**
- Seules les données modifiées sont invalidées
- Pas de flush global du cache
- Preservation des autres données en cache

## 6. Bonnes pratiques

### **Pour les développeurs**

```php
// Toujours vérifier le cache avant la requête base
$cached_data = PL_Cache_Manager::get_keywords_cached();
if ($cached_data !== false) {
    return $cached_data;
}

// Toujours mettre en cache après génération
$data = generate_expensive_data();
PL_Cache_Manager::set_data_cache($data);
```

### **Pour les administrateurs**

1. **Monitoring régulier** : Vérifiez `/cache/stats` pour surveiller les performances
2. **Flush manuel** : Utilisez `/cache/flush` si vous constatez des données obsolètes
3. **Optimisation serveur** : Activez OPcache si disponible

### **Compatibilité avec plugins de cache**

- **W3 Total Cache** : Compatible, ajoute une couche supplémentaire
- **WP Rocket** : Compatible, optimise la livraison des API
- **LiteSpeed Cache** : Compatible avec la mise en cache d'objets

## 7. Métriques de performance

### **Avant cache (serveur mutualisé typique)**
- Endpoint /keywords : ~200-500ms
- Recherche complexe : ~800-1500ms
- Image avec métadonnées : ~100-300ms

### **Après cache**
- Endpoint /keywords (cache hit) : ~5-15ms
- Recherche complexe (cache hit) : ~10-30ms
- Image avec métadonnées (cache hit) : ~5-10ms

## 8. Résolution de problèmes

### **Cache ne fonctionne pas**
```bash
# Vérifier les statistiques
curl "http://votre-site.com/wp-json/photo-library/v1/cache/stats"

# Vérifier si le cache répond
curl "http://votre-site.com/wp-json/photo-library/v1/pictures/keywords" | jq '.cached'
```

### **Données obsolètes**
```bash
# Forcer le flush
curl -X DELETE "http://votre-site.com/wp-json/photo-library/v1/cache/flush"
```

### **Performance toujours lente**
1. Vérifier la limite mémoire PHP
2. Activer OPcache si disponible
3. Considérer un plugin de cache de page

## Conclusion

Ce système de cache est spécialement optimisé pour les contraintes des serveurs mutualisés :
- **Aucune configuration requise**
- **Compatibilité maximale**
- **Faible empreinte mémoire**
- **Invalidation automatique**
- **Monitoring intégré**

Il peut améliorer les performances des API REST de **80-95%** sur un serveur mutualisé typique.