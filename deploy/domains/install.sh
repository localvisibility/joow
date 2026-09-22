#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Installation / mise à jour de l'agent « domaines personnalisés » sur le VPS.
#
#   Première installation (root) :
#       sudo bash /opt/joow/deploy/domains/install.sh votre@email.fr
#   Mise à jour silencieuse (appelée par le déploiement) :
#       sudo bash /opt/joow/deploy/domains/install.sh --update
#
# Installe certbot + jq si absents, la conf nginx, le service systemd (toutes les
# minutes) et lance un premier passage. Idempotent.
# ─────────────────────────────────────────────────────────────────────────────
set -euo pipefail
[ "$(id -u)" = 0 ] || { echo "À lancer en root (sudo)."; exit 1; }

HERE="$(cd "$(dirname "$0")" && pwd)"
CONF=/etc/joow-domains.conf
UPDATE=0; EMAIL=""
for a in "$@"; do case "$a" in --update) UPDATE=1;; *@*) EMAIL="$a";; esac; done

# Dépendances
if ! command -v certbot >/dev/null || ! command -v jq >/dev/null || ! command -v dig >/dev/null; then
  export DEBIAN_FRONTEND=noninteractive
  apt-get update -qq && apt-get install -y -qq certbot jq dnsutils >/dev/null
fi

# Configuration (créée une fois)
if [ ! -f "$CONF" ]; then
  IP="$(curl -4 -s --max-time 5 https://api.ipify.org || hostname -I | awk '{print $1}')"
  cat > "$CONF" <<EOF
# Agent domaines Joow
SERVER_IP=$IP
LE_EMAIL=${EMAIL}
SITES=/var/www/sites
WEBROOT=/var/www/letsencrypt
NG=/etc/nginx/joow
EOF
  echo "→ $CONF créé (IP $IP, email ${EMAIL:-non renseigné})"
elif [ -n "$EMAIL" ]; then
  sed -i "s|^LE_EMAIL=.*|LE_EMAIL=$EMAIL|" "$CONF"
fi

# Dossiers + fichiers inclus (vides au départ)
mkdir -p /etc/nginx/joow /var/www/letsencrypt/.well-known/acme-challenge /var/www/sites/_joow
chmod 777 /var/www/sites/_joow
[ -f /etc/nginx/joow/domains.map ] || : > /etc/nginx/joow/domains.map
[ -f /etc/nginx/joow/https.map ]   || : > /etc/nginx/joow/https.map
[ -f /etc/nginx/joow/names-http.conf ]  || echo 'server_name _joow-none.invalid;' > /etc/nginx/joow/names-http.conf
[ -f /etc/nginx/joow/names-https.conf ] || echo 'server_name _joow-none.invalid;' > /etc/nginx/joow/names-https.conf

# Script + conf nginx
install -m 755 "$HERE/joow-domains.sh" /usr/local/bin/joow-domains
install -m 644 "$HERE/nginx/joow-domains.conf" /etc/nginx/conf.d/joow-domains.conf
if nginx -t >/dev/null 2>&1; then
  systemctl reload nginx
else
  echo "⚠ nginx -t refuse la configuration : conf retirée, rien n'est cassé." >&2
  rm -f /etc/nginx/conf.d/joow-domains.conf; nginx -t; exit 1
fi

# Service systemd (toutes les minutes)
cat > /etc/systemd/system/joow-domains.service <<'EOF'
[Unit]
Description=Joow — domaines personnalisés (DNS, certificats, nginx)
After=network-online.target nginx.service
[Service]
Type=oneshot
ExecStart=/usr/local/bin/joow-domains
EOF
cat > /etc/systemd/system/joow-domains.timer <<'EOF'
[Unit]
Description=Joow — agent domaines (chaque minute)
[Timer]
OnBootSec=2min
OnUnitActiveSec=1min
AccuracySec=10s
[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now joow-domains.timer >/dev/null 2>&1 || true

[ "$UPDATE" = 1 ] || { /usr/local/bin/joow-domains || true; echo "✅ Agent domaines installé. IP serveur : $(. $CONF; echo $SERVER_IP)"; }
