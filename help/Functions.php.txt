<?php

/**
 * Espace de noms pour les classes Core et les fonctions globales de l'application.
 */
namespace App\Core;

/**
 * Génère une chaîne de sel compatible avec l'algorithme Blowfish (bcrypt) utilisé par crypt().
 *
 * Cette fonction crée un sel aléatoire et le formate correctement pour être utilisé
 * comme deuxième argument de la fonction `crypt()` lors du hachage d'un mot de passe
 * avec l'algorithme bcrypt (`$2y$`). Le sel généré inclut le coût algorithmique (work factor).
 *
 * @param int $cost Le coût algorithmique (work factor) pour bcrypt.
 *                  Doit être compris entre 4 et 31 inclus. Une valeur plus élevée
 *                  rend le hachage plus lent et plus résistant aux attaques par force brute.
 *                  La valeur par défaut est 10. Si une valeur invalide est fournie,
 *                  elle sera ramenée à 10.
 *
 * @return string Une chaîne de sel formatée pour bcrypt (ex: "$2y$10$abcdefghijklmnopqrstuv").
 *                Cette chaîne contient l'identifiant de l'algorithme (`$2y$`),
 *                le coût formaté (`%02d$`), et 22 caractères de sel encodés en base64 modifiée.
 */
function gen_salt(int $cost = 10): string {
    // Valide le paramètre de coût. S'il est en dehors de la plage [4, 31],
    // utilise la valeur par défaut (10).
    if ($cost < 4 || $cost > 31) {
        $cost = 10;
    }

    // Génère 16 octets aléatoires cryptographiquement sûrs.
    // C'est la source d'entropie pour le sel. 16 octets * 8 bits/octet = 128 bits.
    // Après encodage Base64, cela donnera 22 caractères ([16 * 4/3] arrondi).
    $randomBytes = random_bytes(16);

    // Encode les octets aléatoires en Base64.
    $base64Encoded = base64_encode($randomBytes);

    // Remplace les caractères '+' par '.' dans la chaîne Base64.
    // L'alphabet Base64 standard contient '+' et '/', mais l'implémentation de
    // crypt() pour bcrypt utilise un alphabet modifié où '+' est remplacé par '.'.
    // Le caractère '/' est généralement conservé, et le padding '=' est retiré.
    // La fonction strtr est utilisée ici pour effectuer le remplacement.
    $salt = strtr($base64Encoded, '+', '.');

    // Construit la chaîne de sel finale au format attendu par crypt() pour bcrypt:
    // - "$2y$" : Identifiant de l'algorithme bcrypt.
    // - sprintf("%02d$", $cost) : Le coût formaté sur deux chiffres avec un '$' à la fin (ex: "10$").
    // - $salt : Les 22 caractères du sel encodé et modifié.
    $fullSaltString = sprintf('$2y$%02d$', $cost) . $salt;

    // Retourne la chaîne de sel complète.
    return $fullSaltString;
}

?>