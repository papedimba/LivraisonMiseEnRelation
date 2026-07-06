<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/Response.php';
require_once __DIR__ . '/../../../includes/Auth.php';
require_once __DIR__ . '/../../../includes/ClaudeClient.php';

$userId = Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::methodNotAllowed();
}

$body = request_body();
$missing = require_fields($body, ['message']);
if (!empty($missing)) {
    Response::error('Le message est requis.', 422, $missing);
}

$message = clean_str($body['message']);
if (mb_strlen($message) > 2000) {
    Response::error('Le message est trop long (2000 caracteres maximum).');
}

$conversationId = clean_str(input($body, 'conversation_id', ''));
if ($conversationId === '') {
    $conversationId = bin2hex(random_bytes(16));
}

$db = Database::getConnection();

$stmtHistorique = $db->prepare(
    'SELECT role, message FROM messages_support WHERE conversation_id = :conv AND user_id = :user_id ORDER BY created_at ASC LIMIT 20'
);
$stmtHistorique->execute(['conv' => $conversationId, 'user_id' => $userId]);
$historique = $stmtHistorique->fetchAll();

$messagesClaude = [];
foreach ($historique as $ligne) {
    $messagesClaude[] = ['role' => $ligne['role'], 'content' => $ligne['message']];
}
$messagesClaude[] = ['role' => 'user', 'content' => $message];

$systemPrompt = <<<PROMPT
Tu es l'assistant du service client de LivraisonCI, une plateforme de livraison et de mise en relation
entre clients, livreurs et commercants a Bouake et en Cote d'Ivoire. Tu aides les utilisateurs a :
- comprendre comment passer une commande, suivre une livraison, ou changer de mode de paiement (especes,
  Orange Money, MTN Mobile Money, Moov Money, Wave) ;
- resoudre des problemes courants (livreur en retard, commande annulee, probleme de paiement) ;
- rediger une reclamation claire si le probleme necessite une intervention humaine.
Reponds toujours en francais, de maniere courtoise, concise et pratique. Si tu ne peux pas resoudre le
probleme, invite l'utilisateur a soumettre une reclamation via le formulaire dedie.
PROMPT;

$reponse = ClaudeClient::chat($messagesClaude, $systemPrompt);

$stmtInsert = $db->prepare(
    'INSERT INTO messages_support (user_id, conversation_id, role, message) VALUES (:user_id, :conv, :role, :message)'
);
$stmtInsert->execute(['user_id' => $userId, 'conv' => $conversationId, 'role' => 'user', 'message' => $message]);
$stmtInsert->execute(['user_id' => $userId, 'conv' => $conversationId, 'role' => 'assistant', 'message' => $reponse]);

Response::success([
    'conversation_id' => $conversationId,
    'reponse' => $reponse,
]);
