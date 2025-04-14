<?php

/**
 * Espace de noms pour les classes Core de l'application.
 */
namespace App\Core;

/**
 * Classe SortLinkGenerator pour générer les liens de tri dans les en-têtes de tableau HTML.
 *
 * Fournit une méthode statique `render` qui crée le code HTML pour un en-tête de colonne (`<th>`)
 * contenant un lien cliquable. Ce lien permet à l'utilisateur de trier les données du tableau
 * selon cette colonne, en alternant entre l'ordre ascendant (ASC) et descendant (DESC).
 * La méthode gère également l'affichage d'une icône (Font Awesome) indiquant l'état actuel du tri
 * pour cette colonne.
 */
class SortLinkGenerator {

    /**
     * Génère le code HTML pour un en-tête de colonne de tableau (`<th>`) avec un lien de tri.
     *
     * @param string $baseUrl       L'URL de base de la page actuelle (liste). Doit contenir
     *                              les paramètres de filtre existants, mais PAS le paramètre 'page'.
     * @param string $columnName    Le nom technique de la colonne (tel qu'utilisé dans la clause
     *                              ORDER BY de la requête SQL et dans les paramètres GET 'sort_by').
     * @param string $label         Le texte lisible à afficher dans l'en-tête (ex: "Nom Utilisateur").
     * @param string|null $currentSortBy La colonne actuellement utilisée pour le tri (peut être null).
     * @param string|null $currentSortDir La direction actuelle du tri ('ASC' ou 'DESC', peut être null).
     *
     * @return string Le code HTML complet de la balise `<th>` contenant le lien de tri et l'icône.
     */
    public static function render(string $baseUrl, string $columnName, string $label, ?string $currentSortBy, ?string $currentSortDir): string {

        // Détermine la prochaine direction de tri pour CETTE colonne quand on cliquera dessus.
        // Si la colonne actuelle est celle-ci ET qu'elle est triée en ASC, le prochain clic triera en DESC.
        // Dans tous les autres cas (colonne différente OU tri DESC actuel), le prochain clic triera en ASC.
        $newSortDir = ($currentSortBy === $columnName && $currentSortDir === 'ASC') ? 'DESC' : 'ASC';

        // ---- Construction de l'URL du lien de tri ----

        // Analyse l'URL de base pour extraire les paramètres GET existants (ex: filtres).
        $urlParams = [];
        // parse_url récupère la partie query string. parse_str la convertit en tableau associatif.
        // L'opérateur null coalescent (?? '') évite une erreur si l'URL n'a pas de query string.
        parse_str(parse_url($baseUrl, PHP_URL_QUERY) ?? '', $urlParams);

        // Ajoute ou remplace les paramètres de tri dans le tableau $urlParams.
        $urlParams['sort_by'] = $columnName;   // La colonne sur laquelle trier.
        $urlParams['sort_dir'] = $newSortDir;  // La direction déterminée ci-dessus.
        // Supprime le paramètre 'page'. Quand on change de tri, on revient toujours à la page 1.
        unset($urlParams['page']);

        // Reconstruit l'URL complète avec les nouveaux paramètres de tri.
        // Récupère le chemin de l'URL de base (ex: "/index.php").
        $path = parse_url($baseUrl, PHP_URL_PATH) ?? '';
        // Concatène le chemin, le '?' et la query string reconstruite avec http_build_query.
        $url = $path . '?' . http_build_query($urlParams);

        // ---- Détermination de l'icône et de la classe CSS pour le <th> ----

        // Icône par défaut (tri neutre).
        $iconClass = 'fas fa-sort';
        // Classe CSS pour le <th> (vide par défaut).
        $thClass = '';

        // Si la colonne actuellement triée ($currentSortBy) est celle pour laquelle on génère le lien :
        if ($currentSortBy === $columnName) {
            // Choisit l'icône 'haut' ou 'bas' selon la direction actuelle du tri.
            $iconClass = ($currentSortDir === 'ASC') ? 'fas fa-sort-up' : 'fas fa-sort-down';
            // Ajoute une classe CSS au <th> pour indiquer visuellement que la colonne est triée.
            $thClass = 'sorted';
        }

        // ---- Génération du HTML final ----
        // Crée la balise <th> avec la classe CSS déterminée ('sorted' ou vide).
        // Crée le lien <a> avec l'URL construite.
        // Affiche le label lisible (protégé avec htmlspecialchars).
        // Ajoute l'icône <i> avec les classes CSS déterminées (fas fa-sort...).
        return "<th class=\"{$thClass}\"><a href=\"{$url}\">" . htmlspecialchars($label) . "<i class=\"sort-icon {$iconClass}\"></i></a></th>";
    }
}