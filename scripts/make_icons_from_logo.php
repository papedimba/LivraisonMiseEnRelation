<?php
declare(strict_types=1);

/**
 * Genere les icones carrees de l'application a partir de VOTRE logo.
 *
 * 1) Deposez votre logo (PNG ou JPG) ici :
 *        public/assets/logo-source.png
 * 2) Lancez :
 *        php scripts/make_icons_from_logo.php
 *
 * Le script produit :
 *   - public/assets/logo.png            (logo pour l'en-tete, hauteur ~160 px)
 *   - public/assets/icons/icon-192.png  (icone carree 192x192)
 *   - public/assets/icons/icon-512.png  (icone carree 512x512)
 *
 * Options (variables d'environnement) :
 *   BG=#f26522   couleur de fond des icones (defaut orange CityHub) ou "transparent"
 *   SRC=chemin   fichier source (defaut public/assets/logo-source.png)
 *   CROP=x,y,w,h decoupe une zone du logo avant de fabriquer l'icone
 *                (utile pour ne garder que l'embleme, sans le texte)
 *
 * Exemple pour ne garder que l'embleme rond en haut d'un logo 1024x760 :
 *   CROP=250,20,520,520 php scripts/make_icons_from_logo.php
 */

$racine = dirname(__DIR__);
$src = getenv('SRC') ?: $racine . '/public/assets/logo-source.png';
$bg = getenv('BG') ?: '#f26522';
$crop = getenv('CROP') ?: '';

if (!is_file($src)) {
    fwrite(STDERR, "Logo introuvable : {$src}\n");
    fwrite(STDERR, "Deposez votre logo a public/assets/logo-source.png (ou definissez SRC=...).\n");
    exit(1);
}

$data = file_get_contents($src);
$logo = @imagecreatefromstring($data);
if ($logo === false) {
    fwrite(STDERR, "Format d'image non reconnu (utilisez PNG ou JPG).\n");
    exit(1);
}
imagealphablending($logo, true);
imagesavealpha($logo, true);

// Decoupe optionnelle.
if ($crop !== '') {
    $p = array_map('intval', explode(',', $crop));
    if (count($p) === 4) {
        $sub = imagecreatetruecolor($p[2], $p[3]);
        imagealphablending($sub, false);
        imagesavealpha($sub, true);
        imagecopy($sub, $logo, 0, 0, $p[0], $p[1], $p[2], $p[3]);
        imagedestroy($logo);
        $logo = $sub;
    }
}

$lw = imagesx($logo);
$lh = imagesy($logo);

function couleur_fond($img, string $bg): int
{
    if (strtolower($bg) === 'transparent') {
        $c = imagecolorallocatealpha($img, 0, 0, 0, 127);
        return $c;
    }
    $bg = ltrim($bg, '#');
    if (strlen($bg) === 3) {
        $bg = $bg[0] . $bg[0] . $bg[1] . $bg[1] . $bg[2] . $bg[2];
    }
    return imagecolorallocate($img, hexdec(substr($bg, 0, 2)), hexdec(substr($bg, 2, 2)), hexdec(substr($bg, 4, 2)));
}

function fabriquer_icone($logo, int $lw, int $lh, int $taille, string $bg, string $chemin): void
{
    $img = imagecreatetruecolor($taille, $taille);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $fond = couleur_fond($img, $bg);
    imagefilledrectangle($img, 0, 0, $taille, $taille, $fond);
    imagealphablending($img, true);

    // Le logo tient dans 82 % de l'icone (zone de securite "maskable").
    $zone = $taille * 0.82;
    $echelle = min($zone / $lw, $zone / $lh);
    $nw = (int) round($lw * $echelle);
    $nh = (int) round($lh * $echelle);
    $dx = (int) (($taille - $nw) / 2);
    $dy = (int) (($taille - $nh) / 2);

    imagecopyresampled($img, $logo, $dx, $dy, 0, 0, $nw, $nh, $lw, $lh);
    imagepng($img, $chemin);
    imagedestroy($img);
}

$icons = $racine . '/public/assets/icons';
fabriquer_icone($logo, $lw, $lh, 192, $bg, $icons . '/icon-192.png');
fabriquer_icone($logo, $lw, $lh, 512, $bg, $icons . '/icon-512.png');

// Logo pour l'en-tete : hauteur 160 px, fond transparent, largeur proportionnelle.
$hauteurEntete = 160;
$largeurEntete = (int) round($lw * ($hauteurEntete / $lh));
$entete = imagecreatetruecolor($largeurEntete, $hauteurEntete);
imagealphablending($entete, false);
imagesavealpha($entete, true);
$transparent = imagecolorallocatealpha($entete, 0, 0, 0, 127);
imagefilledrectangle($entete, 0, 0, $largeurEntete, $hauteurEntete, $transparent);
imagealphablending($entete, true);
imagecopyresampled($entete, $logo, 0, 0, 0, 0, $largeurEntete, $hauteurEntete, $lw, $lh);
imagepng($entete, $racine . '/public/assets/logo.png');
imagedestroy($entete);

echo "OK :\n  public/assets/logo.png (en-tete)\n  public/assets/icons/icon-192.png\n  public/assets/icons/icon-512.png\n";
