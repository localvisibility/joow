#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Agent « domaines personnalisés » Joow — s'exécute sur l'HÔTE (root), chaque minute.
#
# Lit  <SITES>/_joow/domains.json   (écrit par l'application : domaine → slug)
# Fait : vérification DNS, certificat Let's Encrypt (webroot), table nginx, reload
# Écrit <SITES>/_joow/status.json   (relu par l'application : certificat OK / erreur)
#
# Configuration : /etc/joow-domains.conf  (SERVER_IP, LE_EMAIL, SITES, WEBROOT)
# ─────────────────────────────────────────────────────────────────────────────
set -uo pipefail

CONF=/etc/joow-domains.conf
[ -f "$CONF" ] && . "$CONF"
SITES="${SITES:-/var/www/sites}"
WEBROOT="${WEBROOT:-/var/www/letsencrypt}"
NG="${NG:-/etc/nginx/joow}"
LE_EMAIL="${LE_EMAIL:-}"
SERVER_IP="${SERVER_IP:-$(curl -4 -s --max-time 5 https://api.ipify.org || hostname -I | awk '{print $1}')}"
IN="$SITES/_joow/domains.json"
OUT="$SITES/_joow/status.json"
LOCK=/run/joow-domains.lock

exec 9>"$LOCK"; flock -n 9 || exit 0
mkdir -p "$NG" "$WEBROOT/.well-known/acme-challenge" "$SITES/_joow"
[ -f "$IN" ] || exit 0
command -v jq >/dev/null || { echo "jq manquant"; exit 1; }

resolves_to_us() { # $1 = hôte
  local ips; ips=$(dig +short A "$1" @1.1.1.1 2>/dev/null | grep -E '^[0-9.]+$' || true)
  [ -z "$ips" ] && ips=$(getent ahostsv4 "$1" 2>/dev/null | awk '{print $1}' | sort -u || true)
  grep -qx "$SERVER_IP" <<<"$ips"
}

MAP=""; HTTP_NAMES=""; HTTPS_NAMES=""; HTTPS_MAP=""; STATUS='{}'
now=$(date -u +%Y-%m-%dT%H:%M:%SZ)

while IFS=$'\t' read -r domain slug; do
  [ -z "$domain" ] || [ -z "$slug" ] && continue
  [[ "$domain" =~ ^[a-z0-9.-]+$ ]] || continue
  live="/etc/letsencrypt/live/$domain"
  ssl=false; err=""

  MAP+="$domain $slug;"$'\n'"www.$domain $slug;"$'\n'
  HTTP_NAMES+=" $domain www.$domain"

  if [ -f "$live/fullchain.pem" ]; then
    ssl=true
  elif resolves_to_us "$domain"; then
    names=(-d "$domain"); resolves_to_us "www.$domain" && names+=(-d "www.$domain")
    if [ -n "$LE_EMAIL" ]; then mail=(-m "$LE_EMAIL"); else mail=(--register-unsafely-without-email); fi
    if out=$(certbot certonly --webroot -w "$WEBROOT" "${names[@]}" --cert-name "$domain" \
              --non-interactive --agree-tos "${mail[@]}" --keep-until-expiring 2>&1); then
      [ -f "$live/fullchain.pem" ] && ssl=true
    else
      err=$(tail -n 3 <<<"$out" | tr '\n' ' ' | cut -c1-300)
    fi
  else
    err="Le DNS de $domain ne pointe pas encore vers $SERVER_IP"
  fi

  if $ssl; then
    # Le bloc nginx utilise $ssl_server_name : un alias www.<domaine> → <domaine>
    [ -e "/etc/letsencrypt/live/www.$domain" ] || ln -s "$live" "/etc/letsencrypt/live/www.$domain" 2>/dev/null || true
    HTTPS_NAMES+=" $domain www.$domain"
    HTTPS_MAP+="$domain 1;"$'\n'"www.$domain 1;"$'\n'
  fi
  STATUS=$(jq -c --arg d "$domain" --argjson s "$ssl" --arg e "$err" --arg t "$now" '.[$d] = {ssl:$s, error:$e, checked_at:$t}' <<<"$STATUS")
done < <(jq -r '.domains[]? | [.domain, .slug] | @tsv' "$IN")

# Fichiers nginx (inclus par /etc/nginx/conf.d/joow-domains.conf)
printf '%s' "$MAP" > "$NG/domains.map.new"
printf '%s' "$HTTPS_MAP" > "$NG/https.map.new"
printf 'server_name%s;\n' "${HTTP_NAMES:- _joow-none.invalid}" > "$NG/names-http.conf.new"
printf 'server_name%s;\n' "${HTTPS_NAMES:- _joow-none.invalid}" > "$NG/names-https.conf.new"

changed=0
for f in domains.map https.map names-http.conf names-https.conf; do
  if ! cmp -s "$NG/$f.new" "$NG/$f" 2>/dev/null; then mv "$NG/$f.new" "$NG/$f"; changed=1; else rm -f "$NG/$f.new"; fi
done
if [ "$changed" = 1 ]; then
  if nginx -t >/dev/null 2>&1; then systemctl reload nginx || nginx -s reload; else echo "nginx -t KO après mise à jour des domaines" >&2; fi
fi

tmp="$OUT.tmp"; jq -n --arg t "$now" --arg ip "$SERVER_IP" --argjson d "$STATUS" '{generated_at:$t, server_ip:$ip, domains:$d}' > "$tmp" && chmod 666 "$tmp" && mv "$tmp" "$OUT"
