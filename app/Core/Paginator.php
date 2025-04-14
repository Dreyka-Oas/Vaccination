<?php

/**
 * Espace de noms pour les classes Core de l'application.
 */
namespace App\Core;

/**
 * Classe Paginator pour gérer la logique de pagination.
 *
 * Fournit des méthodes statiques pour calculer les informations nécessaires
 * à la pagination (nombre total de pages, offset pour les requêtes SQL)
 * et pour générer le code HTML des contrôles de navigation de pagination
 * (liens vers les pages précédentes, suivantes, numéros de page, etc.)
 * en utilisant le style de pagination de Bootstrap.
 */
class Paginator {

    /**
     * Calcule les données de pagination essentielles.
     *
     * À partir du nombre total d'éléments, du nombre d'éléments par page
     * et de la page actuelle demandée, cette méthode calcule le nombre total
     * de pages nécessaires, ajuste la page actuelle pour qu'elle soit valide
     * (entre 1 et le nombre total de pages), et calcule l'offset SQL
     * nécessaire pour récupérer les éléments de la page courante.
     *
     * @param int $totalItems  Le nombre total d'éléments à paginer.
     * @param int $perPage     Le nombre maximum d'éléments à afficher par page.
     * @param int $currentPage Le numéro de la page actuellement demandée (provenant de l'URL, par exemple).
     *
     * @return array Un tableau associatif contenant :
     *               - 'total_items' (int) : Le nombre total d'éléments.
     *               - 'per_page' (int) : Le nombre d'éléments par page.
     *               - 'current_page' (int) : Le numéro de page actuel (validé).
     *               - 'total_pages' (int) : Le nombre total de pages.
     *               - 'offset' (int) : L'offset à utiliser dans une requête SQL (clause LIMIT/OFFSET).
     */
    public static function getPaginationData(int $totalItems, int $perPage, int $currentPage): array {
        // Calcule le nombre total de pages. Utilise ceil() pour arrondir à l'entier supérieur.
        // Si totalItems est 0, il y a quand même 1 page (même si vide).
        $totalPages = $totalItems > 0 ? ceil($totalItems / $perPage) : 1;

        // Valide la page actuelle demandée.
        // Elle ne peut pas être inférieure à 1 ni supérieure au nombre total de pages.
        $currentPage = max(1, min($currentPage, $totalPages));

        // Calcule l'offset pour la requête SQL.
        // Pour la page 1, offset = (1 - 1) * perPage = 0.
        // Pour la page 2, offset = (2 - 1) * perPage = perPage. etc.
        $offset = ($currentPage - 1) * $perPage;

        // Retourne toutes les données calculées dans un tableau associatif.
        return [
            'total_items' => $totalItems,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset
        ];
    }

    /**
     * Génère le code HTML pour les contrôles de navigation de pagination (style Bootstrap).
     *
     * Crée une liste `<ul>` avec la classe 'pagination' de Bootstrap, contenant les liens
     * vers les pages précédentes/suivantes, les numéros de page autour de la page actuelle,
     * et potentiellement des indicateurs '...' si le nombre total de pages est grand.
     *
     * @param string $baseUrl     L'URL de base pour les liens de pagination. Les paramètres
     *                            existants (filtre, tri) doivent déjà être inclus. Le paramètre
     *                            `page` sera ajouté automatiquement.
     * @param int $currentPage Le numéro de la page actuelle.
     * @param int $totalPages  Le nombre total de pages.
     *
     * @return string Le code HTML de la barre de pagination, ou une chaîne vide si
     *                la pagination n'est pas nécessaire (totalPages <= 1).
     */
    public static function render(string $baseUrl, int $currentPage, int $totalPages): string {
        // Si une seule page ou moins, pas besoin de pagination.
        if ($totalPages <= 1) {
            return '';
        }

        // Début de la structure HTML de pagination Bootstrap.
        $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center mb-0">';
        // Détermine le séparateur à utiliser pour ajouter le paramètre 'page' à l'URL.
        // Si l'URL de base contient déjà un '?', utilise '&', sinon utilise '?'.
        $separator = strpos($baseUrl, '?') === false ? '?' : '&';

        // ---- Bouton Précédent ('«') ----
        // Ajoute la classe 'disabled' si on est sur la première page.
        $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
        $html .= '<li class="page-item ' . $prevDisabled . '">';
        // Le lien pointe vers la page précédente (currentPage - 1).
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage - 1) . '">«</a>';
        $html .= '</li>';

        // ---- Liens des Numéros de Page ----
        // Calcule la plage de numéros de page à afficher autour de la page actuelle.
        $linksToShow = 5; // Nombre maximum de liens de numéro de page à afficher.
        // Calcule la page de début et de fin de la plage.
        $startPage = max(1, $currentPage - floor($linksToShow / 2));
        $endPage = min($totalPages, $currentPage + floor($linksToShow / 2));

        // Ajuste la plage si elle est trop petite et qu'on est près des bords.
        // Si la plage calculée contient moins de $linksToShow liens...
        if ($endPage - $startPage + 1 < $linksToShow) {
            // Si on est au début (startPage = 1), étend la fin jusqu'à $linksToShow (ou $totalPages).
            if ($startPage === 1) {
                $endPage = min($totalPages, $startPage + $linksToShow - 1);
            }
            // Si on est à la fin (endPage = $totalPages), étend le début en arrière.
            elseif ($endPage === $totalPages) {
                $startPage = max(1, $endPage - $linksToShow + 1);
            }
        }

        // Affiche le lien vers la page 1 et les '...' si nécessaire (si la plage ne commence pas à 1).
        if ($startPage > 1) {
            // Lien vers la page 1.
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
            // Affiche '...' s'il y a un écart entre la page 1 et le début de la plage calculée.
            if ($startPage > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Affiche les liens pour la plage de pages calculée ($startPage à $endPage).
        for ($i = $startPage; $i <= $endPage; $i++) {
            // Ajoute la classe 'active' si $i est la page actuelle.
            $active = $currentPage == $i ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '">';
            $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a>';
            $html .= '</li>';
        }

        // Affiche les '...' et le lien vers la dernière page si nécessaire (si la plage ne va pas jusqu'à la fin).
        if ($endPage < $totalPages) {
            // Affiche '...' s'il y a un écart entre la fin de la plage et la dernière page.
            if ($endPage < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            // Lien vers la dernière page ($totalPages).
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
        }

        // ---- Bouton Suivant ('»') ----
        // Ajoute la classe 'disabled' si on est sur la dernière page.
        $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
        $html .= '<li class="page-item ' . $nextDisabled . '">';
        // Le lien pointe vers la page suivante (currentPage + 1).
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . ($currentPage + 1) . '">»</a>';
        $html .= '</li>';

        // Ferme les balises HTML.
        $html .= '</ul></nav>';
        // Retourne le code HTML généré.
        return $html;
    }
}