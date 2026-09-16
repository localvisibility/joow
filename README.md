# Joow SaaS

Plateforme SaaS de génération et d'hébergement de sites vitrine, **internalisée** (sans n8n ni Supabase). Reprend et modernise ce qui existait sur Local Visibility, sur une base **Laravel** unique, déployable en une pile Docker sur le VPS `joow.fr` — aux côtés des autres apps, sans les gêner.

## Architecture

| Domaine | Techno |
|---|---|
| App web + API | Laravel 13 (PHP 8.4) |
| Front dashboard / éditeur | Inertia + Vue + Tailwind (objectif design *Awwwards*) |
| Base de données | PostgreSQL 16 (self-hosted) |
| File d'attente / jobs | Redis + Laravel Horizon |
| Paiements / abonnements / factures | Laravel Cashier (Stripe) |
| Auth | Laravel (email + reset), migrable depuis Supabase (hash bcrypt) |
| Hébergement sites clients | nginx → `/var/www/sites/<slug>`, TLS auto (Caddy/certbot) |
| Génération (ex-n8n) | Jobs Horizon : Google Places → LLM → templates secteur → déploiement |

### Services externes conservés (par nature, pas de l'infra à self-héberger)
Stripe · un LLM (Gemini/OpenAI/Claude) · Google Places · un envoi d'emails (Brevo/SMTP) · registrar DNS + Let's Encrypt.

## Démarrer en local

```bash
cp .env.example .env
docker compose build
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
# App : http://localhost:8090   ·   Horizon : http://localhost:8090/horizon
```

## Pile Docker (`docker-compose.yml`)

`app` (php-fpm) · `web` (nginx, exposé sur `127.0.0.1:8090`) · `postgres` · `redis` · `horizon` (workers) · `scheduler`. En production, le reverse-proxy de l'hôte (nginx/Caddy) route les domaines vers le service `web`. Limites mémoire/CPU posées pour ne pas impacter les autres apps du VPS.

## Modèle de données (Phase 0)

- `sites` — site généré/hébergé (slug, secteur, statut, domaine, abonnement, contenu, modules).
- `invoices` — factures internalisées (checkout **et** renouvellements), idempotentes via `payment_id`.
- `generation_jobs` — suivi des générations (polling côté client), le travail tournant via Horizon.

## Roadmap

- **Phase 0 — Fondations** *(en cours)* : squelette Laravel + Docker + Postgres/Redis + Cashier/Horizon + 1ʳᵉ migration.
- **Phase 1 — Données** : import Supabase → Postgres + migration des comptes.
- **Phase 2 — App** : auth, dashboard & éditeur (design *Awwwards*), factures, checkout, webhooks Stripe.
- **Phase 3 — Génération** : portage du pipeline n8n en Jobs (templates secteur natifs).
- **Phase 4 — Bascule** : DNS, endpoints Stripe, auth → extinction de n8n / Supabase / OVH.
