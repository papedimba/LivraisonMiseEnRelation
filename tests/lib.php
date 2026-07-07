<?php
declare(strict_types=1);

/**
 * Micro-framework de test sans dependance (ni Composer ni PHPUnit).
 */

$GLOBALS['__T'] = ['pass' => 0, 'fail' => 0, 'echecs' => []];

function t_section(string $titre): void
{
    echo "\n\033[1m# {$titre}\033[0m\n";
}

function t_ok(bool $condition, string $label): void
{
    if ($condition) {
        $GLOBALS['__T']['pass']++;
        echo "  \033[32mOK\033[0m   {$label}\n";
    } else {
        $GLOBALS['__T']['fail']++;
        $GLOBALS['__T']['echecs'][] = $label;
        echo "  \033[31mECHEC\033[0m {$label}\n";
    }
}

function t_eq($attendu, $obtenu, string $label): void
{
    $ok = $attendu === $obtenu;
    if (!$ok) {
        $label .= ' (attendu=' . var_export($attendu, true) . ', obtenu=' . var_export($obtenu, true) . ')';
    }
    t_ok($ok, $label);
}

function t_pres($aiguille, $chaine, string $label): void
{
    t_ok(is_string($chaine) && str_contains($chaine, (string) $aiguille), $label);
}

function t_bilan(): int
{
    $t = $GLOBALS['__T'];
    echo "\n" . str_repeat('-', 50) . "\n";
    echo "Resultat : \033[32m{$t['pass']} reussis\033[0m, ";
    echo ($t['fail'] > 0 ? "\033[31m{$t['fail']} echecs\033[0m" : "0 echec") . "\n";
    if ($t['fail'] > 0) {
        echo "Echecs :\n";
        foreach ($t['echecs'] as $e) {
            echo "  - {$e}\n";
        }
    }
    return $t['fail'] > 0 ? 1 : 0;
}

/**
 * Client HTTP minimal avec cookie jar en memoire, pour les tests d'integration.
 */
final class TestHttp
{
    private array $cookies = [];

    public function __construct(private string $base)
    {
    }

    /** @return array{code:int, body:array, raw:string} */
    public function requete(string $methode, string $chemin, ?array $json = null): array
    {
        $ch = curl_init($this->base . $chemin);
        $entetes = ['Accept: application/json'];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $methode,
            CURLOPT_HEADER => true,
        ]);
        if ($json !== null) {
            $entetes[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json));
        }
        if ($this->cookies) {
            $paires = [];
            foreach ($this->cookies as $k => $v) {
                $paires[] = "{$k}={$v}";
            }
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $paires));
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $entetes);

        $reponse = curl_exec($ch);
        $tailleEntete = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $entete = substr((string) $reponse, 0, $tailleEntete);
        $corps = substr((string) $reponse, $tailleEntete);
        curl_close($ch);

        // Memorise les cookies (Set-Cookie).
        if (preg_match_all('/Set-Cookie:\s*([^=]+)=([^;]+)/i', $entete, $m, PREG_SET_ORDER)) {
            foreach ($m as $c) {
                $this->cookies[trim($c[1])] = trim($c[2]);
            }
        }

        $body = json_decode($corps, true);
        return ['code' => $code, 'body' => is_array($body) ? $body : [], 'raw' => $corps];
    }

    public function get(string $chemin): array
    {
        return $this->requete('GET', $chemin);
    }

    public function post(string $chemin, array $json = []): array
    {
        return $this->requete('POST', $chemin, $json);
    }
}
