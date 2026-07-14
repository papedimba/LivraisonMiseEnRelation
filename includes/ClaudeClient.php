<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

/**
 * Client minimaliste pour l'API Anthropic (Claude), via curl PHP natif.
 * Aucune dependance Composer requise - compatible hebergement mutualise.
 */
final class ClaudeClient
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    public static function isConfigured(): bool
    {
        return ANTHROPIC_API_KEY !== '';
    }

    /**
     * Envoie une conversation a Claude et retourne le texte de la reponse.
     *
     * @param array $messages Liste de ['role' => 'user'|'assistant', 'content' => string]
     */
    public static function chat(array $messages, string $systemPrompt = '', int $maxTokens = 1024): string
    {
        if (!self::isConfigured()) {
            return "Le service d'assistance IA n'est pas configure pour le moment. "
                . 'Veuillez contacter le support via le formulaire de reclamation.';
        }

        $payload = [
            'model' => ANTHROPIC_MODEL,
            'max_tokens' => $maxTokens,
            'messages' => $messages,
        ];
        if ($systemPrompt !== '') {
            $payload['system'] = $systemPrompt;
        }

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . ANTHROPIC_API_KEY,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            app_log('ClaudeClient curl error: ' . $curlError);
            return "Une erreur technique est survenue lors de la communication avec l'assistant. Veuillez reessayer.";
        }

        $decoded = json_decode($response, true);

        if ($httpCode !== 200 || !isset($decoded['content'][0]['text'])) {
            app_log('ClaudeClient API error (HTTP ' . $httpCode . '): ' . $response);
            return "L'assistant est momentanement indisponible. Veuillez reessayer dans quelques instants.";
        }

        return $decoded['content'][0]['text'];
    }
}
