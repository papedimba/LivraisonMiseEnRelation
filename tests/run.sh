#!/usr/bin/env bash
#
# Lance la suite complete (unitaires + integration) :
#   - cree une base de test fraiche et importe le schema
#   - demarre un serveur PHP local
#   - execute tests/run.php avec TEST_BASE_URL
#   - nettoie a la fin
#
# Variables surchargeables : TEST_DB_HOST, TEST_DB_USER, TEST_DB_PASS,
# TEST_DB_NAME, TEST_PORT.
set -u

RACINE="$(cd "$(dirname "$0")/.." && pwd)"

DB_HOST="${TEST_DB_HOST:-127.0.0.1}"
DB_USER="${TEST_DB_USER:-root}"
DB_PASS="${TEST_DB_PASS:-}"
DB_NAME="${TEST_DB_NAME:-livraison_ci_test}"
PORT="${TEST_PORT:-8099}"

mysql_cmd() {
    if [ -n "$DB_PASS" ]; then
        mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$@"
    else
        mysql -h "$DB_HOST" -u "$DB_USER" "$@"
    fi
}

echo "==> Preparation de la base de test '${DB_NAME}'"
mysql_cmd -e "DROP DATABASE IF EXISTS ${DB_NAME}; CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4;" || {
    echo "Impossible de creer la base. Verifiez TEST_DB_USER / TEST_DB_PASS."; exit 1;
}
mysql_cmd "${DB_NAME}" < "${RACINE}/sql/schema.sql"

echo "==> Fichier .env de test"
ENV_TEST="${RACINE}/.env.test"
cat > "${ENV_TEST}" <<EOF
DB_HOST=${DB_HOST}
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASS=${DB_PASS}
APP_ENV=development
APP_NOM=LivraisonCI
SESSION_LIFETIME=7200
EOF

# On pointe l'application sur .env.test le temps des tests.
cp -f "${RACINE}/.env" "${RACINE}/.env.bak" 2>/dev/null || true
cp -f "${ENV_TEST}" "${RACINE}/.env"

echo "==> Demarrage du serveur de test sur le port ${PORT}"
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:${PORT} -t "${RACINE}/public" >/dev/null 2>&1 &
SERVER_PID=$!
sleep 2

echo "==> Execution des tests"
TEST_BASE_URL="http://127.0.0.1:${PORT}" php "${RACINE}/tests/run.php"
CODE=$?

echo "==> Nettoyage"
kill "${SERVER_PID}" 2>/dev/null
mysql_cmd -e "DROP DATABASE IF EXISTS ${DB_NAME};"
rm -f "${ENV_TEST}"
cp -f "${RACINE}/.env.bak" "${RACINE}/.env" 2>/dev/null || rm -f "${RACINE}/.env"
rm -f "${RACINE}/.env.bak"

exit ${CODE}
