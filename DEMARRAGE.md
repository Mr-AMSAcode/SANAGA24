# DÉMARRAGE — Sanaga24

Guide de démarrage du projet. **Ce fichier est maintenu à jour automatiquement par Claude à chaque changement de configuration** (ports, variables d'environnement, version PHP, nouvelles commandes...).

Dernière mise à jour : 2026-08-07.

---

## 1. Prérequis

- Docker Desktop (ou Docker Engine + Docker Compose) installé et **lancé**.
- Rien d'autre n'est nécessaire — PHP, Composer, Node, PostgreSQL et Redis tournent tous dans des conteneurs. Le poste hôte n'a besoin de rien installer.

---

## 2. Lancer le projet

Depuis la racine du projet :

```bash
cd /home/amsa/Bureau/dev/SANAGA24/Sanaga24-main
./vendor/bin/sail up -d
```

`-d` = en arrière-plan (detached). Attendre ~10 secondes que PostgreSQL et Redis passent en état "healthy" :

```bash
./vendor/bin/sail ps
```

Puis ouvrir dans le navigateur :

**http://localhost:8080**

---

## 3. Arrêter le projet

```bash
./vendor/bin/sail down
```

Les données (articles, utilisateurs, commentaires...) sont conservées dans un volume Docker — elles survivent à `sail down`. Seul `sail down -v` supprimerait aussi les volumes (donc les données) : à éviter sauf si tu veux repartir de zéro.

---

## 4. Comptes de test

Mot de passe pour tous : `password`

| Rôle    | Email                       | Accès                                    |
|---------|------------------------------|-------------------------------------------|
| Admin   | admin@sanaga24.local         | `/admin` (utilisateurs, modération, newsletter) |
| Éditeur | editor@sanaga24.local        | `/editor` (créer/éditer des articles)     |

---

## 5. Commandes utiles au quotidien

Toutes les commandes `artisan`, `composer` et `npm` doivent passer par `sail` (elles s'exécutent **dans le conteneur**, pas sur le poste hôte) :

```bash
./vendor/bin/sail artisan migrate          # migrations
./vendor/bin/sail artisan tinker           # console interactive
./vendor/bin/sail artisan test             # suite de tests Pest
./vendor/bin/sail composer install         # dépendances PHP
./vendor/bin/sail npm run build            # compiler les assets (CSS/JS)
./vendor/bin/sail logs -f                  # logs en direct
```

### Recompiler les assets après une modif CSS/JS

```bash
./vendor/bin/sail npm run build
```

### Rebuild complet du conteneur (après un changement de version PHP ou de Dockerfile)

⚠️ Toujours utiliser `sail build`, jamais `docker compose build` directement (sinon erreur `groupadd --force -g  sail: invalid group ID`) :

```bash
./vendor/bin/sail build laravel.test
./vendor/bin/sail up -d
```

---

## 6. Configuration actuelle (`.env`)

| Service              | Port hôte | Détail                                  |
|-----------------------|-----------|------------------------------------------|
| Application (HTTP)    | `8080`    | http://localhost:8080                    |
| PostgreSQL            | `54329`   | base `sanaga_blog`, user `sail` / `password` |
| Redis                 | `63790`   |                                            |
| Reverb (WebSocket)    | `8082`    | temps réel (commentaires/likes en direct) |
| Vite (dev, si lancé)  | `5173`    |                                            |

- **PHP** : 8.4 (image `sail-8.4/app`, contexte `./docker/8.4`)
- **Langue par défaut** : anglais (`APP_LOCALE=en`) — un sélecteur FR/EN est disponible dans la barre de navigation du site, changeable par le visiteur à tout moment.
- **Processus internes** (gérés par supervisord dans le conteneur `laravel.test`) : serveur web, `queue:work` (files d'attente), `schedule:work` (tâches planifiées : publication programmée, archivage, newsletter hebdomadaire), `reverb:start` (WebSocket).

---

## 7. Problèmes fréquents

- **Le site affiche une erreur 500 / images d'articles en 404** → le lien symbolique de stockage a peut-être disparu (arrive après un rebuild) :
  ```bash
  ./vendor/bin/sail artisan storage:link
  ```
- **Port déjà utilisé au démarrage** (`port is already allocated`) → un autre programme occupe déjà 8080/54329/63790/8082. Vérifier avec `sudo lsof -i :8080` (remplacer le port) ou modifier le port dans `.env` puis relancer `sail up -d`.
- **Les conteneurs sont "Exited"** après une mise en veille du PC ou un redémarrage Docker → il suffit de relancer `./vendor/bin/sail up -d`, aucune donnée n'est perdue.
- **`./vendor/bin/sail` : command not found`** → toujours lancer les commandes depuis la racine du projet (`cd /home/amsa/Bureau/dev/SANAGA24/Sanaga24-main`).

---

## 8. Après un changement de code

- **Code PHP / Blade / Livewire** : rien à faire, les changements sont pris en compte immédiatement (juste recharger la page).
- **CSS / JS** : `./vendor/bin/sail npm run build` puis recharger la page.
- **Nouvelle migration** : `./vendor/bin/sail artisan migrate`.
- **Nouveau package Composer/NPM** : `./vendor/bin/sail composer install` ou `./vendor/bin/sail npm install`, puis rebuild si le Dockerfile a changé.

---

## 9. Mise en production (VPS)

Ce fichier ne couvre que l'environnement de développement local (Sail). Pour mettre le site en ligne sur un VPS avec ton propre nom de domaine et le HTTPS, voir **[DEPLOIEMENT.md](DEPLOIEMENT.md)** — guide complet pas-à-pas, avec sa propre configuration Docker de production (`docker-compose.prod.yml`, `docker/production/`), entièrement séparée de celle utilisée ici en local.
