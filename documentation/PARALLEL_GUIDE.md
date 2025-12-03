## 🚀 Guide de Parallélisation pour sync_palettes

Voici comment paralléliser le traitement des palettes avec plusieurs approches :

### 1. **Parallélisation par processus (pcntl_fork)**

```bash
# Utiliser 4 processus parallèles
wp photolibrary sync_palettes --parallel=4

# Combinaison avec autres options
wp photolibrary sync_palettes --parallel=4 --batch-size=10 --force
```

### 2. **Traitement par workers asynchrones**

```bash
# Utiliser 3 workers
wp photolibrary sync_palettes --workers=3

# Workers avec simulation
wp photolibrary sync_palettes --workers=5 --dry-run
```

### 3. **Méthodes de parallélisation disponibles**

#### A. **Fork de processus** (Linux/macOS uniquement)
- Utilise `pcntl_fork()` pour créer des processus enfants
- Chaque processus traite un sous-ensemble d'images
- Communication via fichiers temporaires
- **Avantages**: Vraie parallélisation, isolation des erreurs
- **Inconvénients**: Consomme plus de mémoire

#### B. **Workers avec chunks**
- Divise le travail en chunks séquentiels
- Optimise l'ordre de traitement
- **Avantages**: Compatible partout, contrôle mémoire
- **Inconvénients**: Parallélisation simulée

#### C. **Pool de connexions HTTP**
- Pour les traitements nécessitant des API externes
- Utilise cURL multi-threading
- **Avantages**: Optimal pour requêtes réseau
- **Inconvénients**: Complexité additionnelle

### 4. **Optimisations recommandées**

```bash
# Configuration optimale pour serveur performant
wp photolibrary sync_palettes --parallel=4 --batch-size=50 --workers=2

# Configuration économe pour serveur limité
wp photolibrary sync_palettes --workers=2 --batch-size=20

# Test de performance
wp photolibrary sync_palettes --parallel=8 --max-images=100 --dry-run
```

### 5. **Monitoring et debugging**

```bash
# Activer le debug pour voir la parallélisation
wp photolibrary sync_palettes --parallel=4 --debug

# Statistiques détaillées
wp photolibrary stats
```

### 6. **Considérations de performance**

- **CPU-bound tasks**: Utilisez `--parallel` (nombre de cœurs CPU)
- **I/O-bound tasks**: Utilisez `--workers` (plus élevé possible)
- **Mémoire limitée**: Réduisez `--batch-size`
- **Base de données**: Évitez trop de connexions simultanées

### 7. **Exemple d'intégration dans le code**

Le trait `PhotoLibrary_CLI_Parallel` fournit les méthodes :
- `process_parallel_fork()` - Fork de processus
- `process_async_workers()` - Workers asynchrones
- `process_sequential()` - Traitement classique
- `process_http_pool()` - Pool HTTP pour APIs

Ces méthodes sont automatiquement utilisées selon les paramètres `--parallel` et `--workers`.
