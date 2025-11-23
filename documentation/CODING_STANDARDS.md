# PhotoLibrary REST API Plugin - Standards de Codage WordPress

Ce plugin respecte les standards de codage WordPress grâce à PHPCS et PHPCBF.

## Installation des outils de développement

```bash
composer install
```

## Utilisation des standards de codage

### Vérifier le code
```bash
# Rapport détaillé
composer run phpcs

# Rapport de résumé
composer run cs:check
```

### Corriger automatiquement le code
```bash
# Corrections automatiques
composer run cs:fix
```

### Commandes directes
```bash
# Avec les binaires vendeur
./vendor/bin/phpcs --standard=phpcs.xml
./vendor/bin/phpcbf --standard=phpcs.xml
```

## Configuration

Le fichier `phpcs.xml` contient la configuration des standards WordPress :
- **Standard**: WordPress-Extra (règles étendues)
- **Documentation**: WordPress-Docs
- **PHP Version**: 7.4+
- **WordPress Version**: 5.0+

### Règles exclues
- `WordPress.Files.FileName.InvalidClassFileName`
- `WordPress.Files.FileName.NotHyphenatedLowercase`
- `Generic.Commenting.DocComment.MissingShort`
- `Squiz.Commenting.FunctionComment.Missing`
- `Squiz.Commenting.ClassComment.Missing`
- `Squiz.Commenting.VariableComment.Missing`
- `WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase`

## Progression du nettoyage

### État initial
- **1831 erreurs** et **101 warnings**

### Après PHPCBF automatique
- **1742 corrections automatiques**
- **140 erreurs** et **27 warnings** restants

### Améliorations apportées
- ✅ Formatage WordPress standard
- ✅ Conventions de nommage
- ✅ Commentaires conformes
- ✅ Structure des fichiers
- ✅ Conditions Yoda
- ✅ Espacement et indentation

### Fichiers traités
- `photo_library_rest_api.php`
- `src/class.photo-library.php`
- `src/class.photo-library-route.php`
- `src/class.photo-library-db.php`
- `src/class.photo-library-data-handler.php`
- `src/class.photo-library-schema.php`
- `src/class.photo-library-install.php`

## Workflow de développement

1. **Avant commit** : `composer run cs:check`
2. **Correction automatique** : `composer run cs:fix`
3. **Vérification finale** : `composer run cs:check`

## Standards appliqués

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer)
- [WordPress Coding Standards for PHP_CodeSniffer](https://github.com/WordPress/WordPress-Coding-Standards)
