#!/bin/bash

# PhotoLibrary Pinecone Quick Sync Script
# Script pour synchroniser rapidement l'index Pinecone avec les palettes WordPress

set -e

echo "=============================================="
echo "PhotoLibrary Pinecone Quick Sync Tool"
echo "=============================================="
echo ""

# Vérifier PINECONE_API_KEY
if [ -z "$PINECONE_API_KEY" ]; then
    echo "❌ ERREUR: Variable d'environnement PINECONE_API_KEY non définie"
    echo ""
    echo "Configurez votre clé API Pinecone:"
    echo "  export PINECONE_API_KEY=\"your-api-key-here\""
    echo ""
    echo "Ou ajoutez-la dans .ddev/config.yaml:"
    echo "  web_environment:"
    echo "    - PINECONE_API_KEY=your-api-key-here"
    echo ""
    exit 1
fi

echo "✅ Clé API Pinecone configurée"
echo ""

# Vérifier que nous sommes dans le bon répertoire
if [ ! -f "../../../wp-config.php" ] && [ ! -f "../../wp-config.php" ]; then
    echo "❌ ERREUR: Ce script doit être exécuté depuis le répertoire du plugin"
    echo "   cd wp-content/plugins/photo_library_wp_api_rest_plugin/"
    echo "   Ou depuis la racine WordPress"
    exit 1
fi

# Déterminer le répertoire WordPress
if [ -f "wp-config.php" ]; then
    WP_ROOT="."
elif [ -f "../../../wp-config.php" ]; then
    WP_ROOT="../../.."
else
    echo "❌ ERREUR: Impossible de trouver wp-config.php"
    exit 1
fi

echo "✅ Répertoire WordPress détecté: $WP_ROOT"
echo ""

# Aller dans le répertoire WordPress pour les commandes WP-CLI
cd "$WP_ROOT"

# Vérifier DDEV
if command -v ddev &> /dev/null && ddev describe &> /dev/null; then
    WP_CLI="ddev wp"
    echo "✅ Environnement DDEV détecté"
else
    WP_CLI="wp"
    echo "✅ WP-CLI local détecté"
fi

echo ""

# Afficher les options
echo "Options disponibles:"
echo ""
echo "  1. Analyser les palettes existantes (recommandé pour commencer)"
echo "  2. Synchroniser palettes existantes → Pinecone"
echo "  3. Extraire palettes manquantes + synchroniser"
echo "  4. Vider complètement l'index Pinecone et reconstruire"
echo "  5. Test de connexion Pinecone seulement"
echo "  6. Simulation complète (dry-run)"
echo ""

read -p "Choisissez une option (1-6): " choice

case $choice in
    1)
        echo ""
        echo "📊 Analyse des palettes disponibles..."
        echo "================================================="

        # Statistiques générales
        echo ""
        echo "Statistics générales:"
        $WP_CLI photolibrary stats

        echo ""
        echo "Aperçu des 10 premières palettes:"
        $WP_CLI photolibrary list_palettes_for_pinecone --limit=10
        ;;

    2)
        echo ""
        echo "☁️  Synchronisation des palettes existantes vers Pinecone..."
        echo "============================================================"

        # Test de connexion d'abord
        echo "Test de la connexion..."
        $WP_CLI photolibrary test_pinecone

        echo ""
        echo "Lancement de la synchronisation:"
        $WP_CLI photolibrary rebuild_pinecone_index
        ;;

    3)
        echo ""
        echo "🔄 Extraction des palettes manquantes + synchronisation..."
        echo "========================================================="

        echo "Étape 1: Extraction des palettes manquantes"
        $WP_CLI photolibrary sync_palettes --batch-size=50

        echo ""
        echo "Étape 2: Synchronisation vers Pinecone"
        $WP_CLI photolibrary rebuild_pinecone_index
        ;;

    4)
        echo ""
        echo "⚠️  ATTENTION: Cette option va VIDER complètement l'index Pinecone!"
        echo "=================================================================="
        echo ""
        read -p "Êtes-vous sûr de vouloir continuer? (oui/non): " confirm

        if [ "$confirm" != "oui" ]; then
            echo "❌ Opération annulée"
            exit 0
        fi

        echo ""
        echo "🗑️  Vidage et reconstruction complète de l'index..."
        $WP_CLI photolibrary rebuild_pinecone_index --clear-first
        ;;

    5)
        echo ""
        echo "🔍 Test de connexion Pinecone..."
        echo "================================"

        $WP_CLI photolibrary test_pinecone

        # Test avec la classe complète
        echo ""
        echo "Test de la classe PL_Color_Search_Index:"
        $WP_CLI eval "
        try {
            \$index = new PL_Color_Search_Index();
            \$test = \$index->test_connection();
            echo 'Status: ' . \$test['status'] . \"\\n\";
            echo 'Message: ' . \$test['message'] . \"\\n\";
            if (isset(\$test['stats'])) {
                echo 'Vecteurs dans l\'index: ' . \$test['stats']['total_vectors'] . \"\\n\";
            }
        } catch (Exception \$e) {
            echo 'Erreur: ' . \$e->getMessage() . \"\\n\";
        }"
        ;;

    6)
        echo ""
        echo "🔬 Simulation complète (aucune modification ne sera effectuée)..."
        echo "================================================================"

        echo "Simulation de la reconstruction:"
        $WP_CLI photolibrary rebuild_pinecone_index --dry-run
        ;;

    *)
        echo "❌ Option invalide: $choice"
        exit 1
        ;;
esac

echo ""
echo "=============================================="
echo "Script terminé"
echo "=============================================="
