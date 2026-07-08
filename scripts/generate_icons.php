<?php
declare(strict_types=1);

/**
 * Genere les icones PWA (colis blanc sur fond violet) dans
 * public/assets/icons/. A relancer si l'on change la charte graphique.
 *   php scripts/generate_icons.php
 */

function generer_icone(int $taille, string $chemin): void
{
    $img = imagecreatetruecolor($taille, $taille);
    imagesavealpha($img, true);

    $violet = imagecolorallocate($img, 0x6c, 0x4d, 0xff);
    $violetFonce = imagecolorallocate($img, 0x57, 0x3b, 0xe0);
    $blanc = imagecolorallocate($img, 0xff, 0xff, 0xff);

    imagefilledrectangle($img, 0, 0, $taille, $taille, $violet);

    // Colis (carre blanc centre).
    $c = $taille / 2;
    $demi = (int) ($taille * 0.24);
    $x1 = (int) ($c - $demi);
    $y1 = (int) ($c - $demi);
    $x2 = (int) ($c + $demi);
    $y2 = (int) ($c + $demi);
    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $blanc);

    // Ruban adhesif (croix violette) pour l'effet colis.
    $ep = max(2, (int) ($taille * 0.035));
    imagefilledrectangle($img, (int) ($c - $ep / 2), $y1, (int) ($c + $ep / 2), $y2, $violet);
    imagefilledrectangle($img, $x1, (int) ($c - $ep / 2), $x2, (int) ($c + $ep / 2), $violet);

    // Rabat superieur (ligne du haut du colis).
    imagefilledrectangle($img, $x1, $y1, $x2, (int) ($y1 + $taille * 0.05), $violetFonce);

    imagepng($img, $chemin);
    imagedestroy($img);
}

$base = __DIR__ . '/../public/assets/icons';
generer_icone(192, $base . '/icon-192.png');
generer_icone(512, $base . '/icon-512.png');

echo "Icones generees dans public/assets/icons/\n";
