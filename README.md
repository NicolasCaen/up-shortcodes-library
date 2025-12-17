# UP Shortcodes Library

Gestion des shortcodes via un Custom Post Type, avec édition (PHP / JS / SCSS), compilation SCSS→CSS et génération de fichiers dans le thème.

## Fonctionnalités

- **CPT `up-shortcodes`** avec metabox d’édition:
  - **Code PHP** du shortcode (contenu de `add_shortcode(...)`).
  - **JavaScript (optionnel)** chargé pour le shortcode.
  - **SCSS/CSS (optionnel)** pour le style du shortcode.
  - **CSS compilé (lecture seule)**: aperçu mis à jour à chaque sauvegarde.
- **Metabox latérale** “Nom de fichier et génération”:
  - **Nom de fichier (sans extension)**, normalisé en `shortcode-...` automatiquement.
  - Cases à cocher pour choisir les fichiers à générer:
    - **PHP** (au niveau racine du dossier de sortie)
    - **CSS** (dans `assets/css/`)
    - **JS** (dans `assets/js/`)
    - **SCSS** (dans `assets/scss/`)
- **Compilation SCSS→CSS** à la sauvegarde (via scssphp si disponible), avec **préfixe de mixins**.
- **Dossier de sortie configurable** (par défaut: `wp-content/themes/<theme-actif>/shortcodes/`).

## Structure de fichiers générés

```
shortcodes/
├─ shortcode-monomodule.php               (si coché)
└─ assets/
   ├─ css/  └─ shortcode-monomodule.css   (si coché)
   ├─ js/   └─ shortcode-monomodule.js    (si coché)
   └─ scss/ └─ shortcode-monomodule.scss  (si coché)
```

> Le **nom de base** est issu du champ “Nom de fichier” et est toujours normalisé en `shortcode-...`.

## Dépendances

- **WordPress** 5.8+ (recommandé) pour l’éditeur et les APIs utilisées.
- **CodeMirror** via `wp_enqueue_code_editor()` (fourni par WordPress, pas d’installation séparée).
- **SCSSPHP (optionnel)** pour la compilation SCSS → CSS:
  - Si le plugin **UP Variation Generator** (`up-variation-generator`) est actif, il fournit `scssphp` via son `vendor/` et la compilation fonctionnera automatiquement.
  - Sans `scssphp`, la compilation est désactivée (le champ CSS compilé affichera le SCSS ou restera vide selon les cas) et aucun fichier CSS ne sera généré si la compilation échoue.
- **Droits d’écriture** sur le dossier de sortie (thème) : `wp-content/themes/<theme>/shortcodes/`.

## Réglages

- Clé d’option: `up_sl_settings`.
- Valeurs:
  - `output_directory` (string) — chemin absolu, par défaut `get_stylesheet_directory() . '/shortcodes/'`.
- Une page d’administration du plugin permet de définir ce répertoire (si incluse/active: `admin/admin-page.php`).

## Filtres

- `up_sl_base_mixins_path` (string $path) → string
  - Permet de **surcharger le chemin** du fichier de mixins SCSS préfixé avant compilation.
  - **Par défaut**: `plugins/up-shortcodes-library/assets/scss/_mixins.scss`.
  - Exemple (dans le thème ou un mu-plugin) pour réutiliser les mixins d’UP Variation Generator:

    ```php
    add_filter('up_sl_base_mixins_path', function($path){
        return WP_PLUGIN_DIR . '/up-variation-generator/assets/scss/_mixins.scss';
    });
    ```

## Clés méta utilisées

- `_up_sl_php_code` — code PHP du shortcode.
- `_up_sl_js_code` — code JS.
- `_up_sl_scss_code` — code SCSS source.
- `_up_sl_compiled_css` — CSS compilé (aperçu et génération).
- `_up_sl_file_name` — nom de fichier de base (sans extension), normalisé en `shortcode-...`.
- `_up_sl_generate_php_file` — `1`/`0` pour générer le PHP.
- `_up_sl_generate_css_file` — `1`/`0` pour générer le CSS.
- `_up_sl_generate_js_file` — `1`/`0` pour générer le JS.
- `_up_sl_generate_scss_file` — `1`/`0` pour générer le SCSS.

## Hooks internes et comportement

- `save_post_up-shortcodes` → déclenche la sauvegarde des champs, la compilation SCSS et la **génération des fichiers** selon les cases cochées.
- `admin_enqueue_scripts` → charge CodeMirror et les assets d’admin du plugin lors de l’édition/création de `up-shortcodes`.

## Mixins SCSS

Un fichier de mixins est **préfixé automatiquement** au SCSS lors de la compilation. Mixins inclus (`assets/scss/_mixins.scss`):

- `px($target-px)` — helper conversion simple.
- `PxToRem($size)` — conversion px → rem base 16.
- `@mixin b($min,$max)` — media query entre 2 largeurs.
- `@mixin m($min)` — media query max-width.
- `@mixin p($max)` — media query min-width.

Ces mixins peuvent être **remplacés** en pointant vers un autre fichier (voir filtre `up_sl_base_mixins_path`).

## Prérequis & Permissions

- PHP 7.4+ recommandé.
- Droits d’écriture sur le dossier de sortie.
- Pour la compilation SCSS, la classe `ScssPhp\ScssPhp\Compiler` doit être disponible (via un plugin tiers ou un autoloader Composer).

## Installation & usage

1. Déposer le dossier `up-shortcodes-library/` dans `wp-content/plugins/`.
2. Activer le plugin dans WordPress.
3. (Optionnel) Configurer le **dossier de sortie** dans la page de réglages du plugin.
4. Aller dans **Shortcodes** → **Ajouter**:
   - Saisir le **code PHP**, **JS** et/ou **SCSS**.
   - Définir le **Nom de fichier** (sans extension). Il sera normalisé en `shortcode-...`.
   - Cocher les **fichiers à générer** (PHP / CSS / JS / SCSS).
   - Enregistrer: les fichiers sont écrits dans le dossier de sortie.

## Limitations & Notes

- Si la compilation SCSS échoue ou si `scssphp` est indisponible, le champ CSS compilé peut être vide; la génération de CSS respectera l’état compilé actuel.
- Les fonctions WordPress (e.g. `add_shortcode`) doivent être valides dans le fichier PHP généré.

## Licence

Ce plugin est fourni dans le cadre du projet et peut être adapté selon vos besoins.

## Changelog

### 1.2.0 — 2025-12-17
- Documentation: Ajout d'un éditeur Markdown pour rédiger un README.md associé au shortcode.
- Génération: Option pour générer le fichier README.md dans le dossier du shortcode.
- Workflow: Case à cocher "Ajouter aux shortcodes du plugin" pour sauvegarder automatiquement le shortcode dans le XML par défaut du plugin (`defaults/up-shortcodes-default.xml`).
- Import/Export: Prise en charge du champ README et des nouveaux flags de génération dans l'export XML.

### 1.1.0 — 2025-09-26
- Nouvelles options de sortie: calcul automatique du dossier de génération via `base_location` (thème / mu-plugins / ce plugin / personnalisé) et `relative_subdir`.
- UI réglages: le champ « Chemin personnalisé » est masqué/désactivé sauf si « Chemin personnalisé » est sélectionné.
- Robustesse chemins MU‑plugins: garde‑fous si certaines constantes WP ne sont pas définies lors de l’analyse statique.
- Import/Export:
  - Export déplacé vers `admin-post.php` pour éviter « headers already sent ».
  - Ajout du bouton « Importer les shortcodes par défaut (plugin) » lisant `defaults/up-shortcodes-default.xml`.
  - Export enrichi: inclut les flags `_up_sl_generate_*_file`; import compatible (avec rétro‑compatibilité sur `_up_sl_generate_file`).
- Versionnage assets admin via `UP_SL_VERSION`.

### 1.0.0 — 2025-09-01
- Version initiale: CPT `up-shortcodes`, édition PHP/JS/SCSS, compilation SCSS→CSS, génération de fichiers, metabox options.

[Changelog complet](https://github.com/gehin/up-shortcodes-library/blob/master/CHANGELOG.md)
