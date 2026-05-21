#!/bin/sh
SSL_DIR="/etc/nginx/ssl"
CERT="$SSL_DIR/cert.pem"
KEY="$SSL_DIR/key.pem"

if [ -f "$CERT" ] && [ -f "$KEY" ]; then
    openssl x509 -checkend 2592000 -noout -in "$CERT" 2>/dev/null && exit 0
fi

mkdir -p "$SSL_DIR"
SERVER_IP="${SERVER_IP:-192.168.0.9}"

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
    -keyout "$KEY" -out "$CERT" \
    -subj "/C=MG/ST=Local/L=Local/O=POS-System/CN=$SERVER_IP" \
    -addext "subjectAltName=IP:$SERVER_IP,IP:127.0.0.1" 2>/dev/null

echo "✅ Certificat SSL frontend généré pour IP: $SERVER_IP"
