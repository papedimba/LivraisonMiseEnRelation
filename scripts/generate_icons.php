<?php
declare(strict_types=1);

/**
 * Genere les icones PWA de CityHub 225 (monogramme "CH" sur colis, couleurs
 * orange / noir / vert). A relancer si l'on change la charte graphique.
 *   php scripts/generate_icons.php
 *
 * Astuce : pour utiliser votre vrai logo, deposez-le en PNG carre a
 *   public/assets/logo.png (affiche dans l'en-tete) et remplacez au besoin
 *   public/assets/icons/icon-192.png et icon-512.png par des versions carrees
 *   de votre logo.
 */

function police(): string
{
    foreach ([
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/Library/Fonts/Arial Bold.ttf',
    ] as $f) {
        if (is_file($f)) {
            return $f;
        }
    }
    return '';
}

function generer_icone(int $taille, string $chemin): void
{
    $img = imagecreatetruecolor($taille, $taille);
    imagesavealpha($img, true);

    $orange = imagecolorallocate($img, 0xf2, 0x65, 0x22);
    $noir = imagecolorallocate($img, 0x17, 0x18, 0x1a);
    $blanc = imagecolorallocate($img, 0xff, 0xff, 0xff);
    $vert = imagecolorallocate($img, 0x2e, 0x9e, 0x4b);

    // Fond orange.
    imagefilledrectangle($img, 0, 0, $taille, $taille, $orange);

    // Colis noir centre (comme le logo).
    $c = $taille / 2;
    $demi = (int) ($taille * 0.30);
    $x1 = (int) ($c - $demi);
    $y1 = (int) ($c - $demi);
    $x2 = (int) ($c + $demi);
    $y2 = (int) ($c + $demi);
    imagefilledrectangle($img, $x1, $y1, $x2, $y2, $noir);

    // Bande verte en bas (rappel du drapeau / accent).
    imagefilledrectangle($img, $x1, (int) ($y2 - $taille * 0.06), $x2, $y2, $vert);

    // Monogramme "CH".
    $font = police();
    if ($font !== '') {
        $tailleTexte = (int) ($taille * 0.26);
        $texte = 'CH';
        $bbox = imagettfbbox($tailleTexte, 0, $font, $texte);
        $largeur = $bbox[2] - $bbox[0];
        $hauteur = $bbox[1] - $bbox[7];
        $tx = (int) ($c - $largeur / 2);
        $ty = (int) ($c + $hauteur / 2 - $taille * 0.02);
        imagettftext($img, $tailleTexte, 0, $tx, $ty, $blanc, $font, $texte);
    }

    imagepng($img, $chemin);
    imagedestroy($img);
}

$base = __DIR__ . '/../public/assets/icons';
generer_icone(192, $base . '/icon-192.png');
generer_icone(512, $base . '/icon-512.png');

echo "Icones CityHub 225 generees dans public/assets/icons/\n";
