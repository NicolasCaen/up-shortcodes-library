# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format s’inspire de Keep a Changelog et suit le versionnage sémantique.

## [1.1.0] - 2025-09-26
### Ajouté
- Import par défaut depuis le plugin: bouton « Importer les shortcodes par défaut (plugin) » qui lit `defaults/up-shortcodes-default.xml`.
- Fichier d’exemple `defaults/up-shortcodes-default.xml` (shortcode `[hello]`).
- Nouvelles options de sortie: `base_location` (thème / mu-plugins / plugin / personnalisé) et `relative_subdir`.
- Paramètre « Sous-dossier par shortcode » (wrap_in_subfolder) dans les réglages.
- Export enrichi: inclusion des flags `_up_sl_generate_php_file`, `_up_sl_generate_css_file`, `_up_sl_generate_js_file`, `_up_sl_generate_scss_file`.
- Définition `UP_SL_VERSION` et versionnage des assets admin.

### Modifié
- Export migré vers un handler `admin-post` pour éviter les erreurs « headers already sent ».
- UI des réglages: affichage/masquage du champ « Chemin personnalisé » selon la base sélectionnée.
- Calcul robuste du chemin MU-plugins avec garde-fous si certaines constantes WP ne sont pas définies en analyse statique.

### Compat
- Rétro-compatibilité conservée avec l’ancien flag `_up_sl_generate_file` lors de l’import.

## [1.0.0] - 2025-09-01
### Ajouté
- Version initiale: CPT `up-shortcodes`, édition PHP/JS/SCSS, compilation SCSS→CSS, génération de fichiers, metabox d’options.
