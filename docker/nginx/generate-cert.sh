#!/bin/sh
# Génère un certificat auto-signé pour le réseau local
# Valide 10 ans — relancer uniquement si le cert expire ou si l'IP change
SSL_DIR="/etc/nginx/ssl"
CERT="$SSL_DIR/cert.pem"
KEY="$SSL_DIR/key.pem"

if [ -f "$CERT" ] && [ -f "$KEY" ]; then
    echo "Certificat SSL déjà présent — vérification..."
    # Renouveler si expire dans moins de 30 jours
    if openssl x509 -checkend 2592000 -noout -in "$CERT" 2>/dev/null; then
        echo "Certificat valide — aucune action requise."
        exit 0
    fi
    echo "Certificat bientôt expiré — renouvellement..."
fi

mkdir -p "$SSL_DIR"
SERVER_IP="${SERVER_IP:-192.168.0.9}"

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout "$KEY" \
    -out "$CERT" \
    -subj "/C=MG/ST=Local/L=Local/O=POS-System/CN=$SERVER_IP" \
    -addext "subjectAltName=IP:$SERVER_IP,IP:127.0.0.1" \
    2>/dev/null

echo "✅ Certificat SSL généré pour IP: $SERVER_IP (valide 10 ans)"
echo "   Pour éviter l'avertissement navigateur, importez le cert dans vos appareils:"
echo "   docker compose cp nginx:/etc/nginx/ssl/cert.pem ./pos-local-ca.crt"
