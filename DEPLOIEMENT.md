# DÉPLOIEMENT — Sanaga24 en production

Guide pas-à-pas pour mettre Sanaga24 en ligne sur ton VPS, avec ton nom de domaine et un certificat SSL (HTTPS) gratuit et automatique.

**Principe** : tout tourne dans Docker (l'application, PostgreSQL, Redis, le serveur temps réel Reverb), sauf Nginx et Certbot qui sont installés directement sur le VPS — c'est Nginx (VPS) qui reçoit le trafic public sur le port 443 (HTTPS) et le redirige vers l'application dans son conteneur. C'est le montage le plus simple et le plus fiable pour gérer le certificat SSL automatiquement.

Dernière mise à jour : 2026-08-07.

---

## 0. Ce qu'il te faut avant de commencer

- Un VPS sous **Ubuntu 22.04 ou 24.04**, avec un accès SSH (adresse IP, utilisateur, mot de passe ou clé SSH).
- Un **nom de domaine** dont tu as la main sur les DNS.
- Chez ton registrar (ou ton fournisseur DNS), crée deux enregistrements **A** pointant vers l'adresse IP de ton VPS :

  | Type | Nom              | Valeur              |
  |------|------------------|----------------------|
  | A    | `@` (ou vide)    | `<IP_DE_TON_VPS>`   |
  | A    | `www`            | `<IP_DE_TON_VPS>`   |

  La propagation DNS peut prendre de quelques minutes à quelques heures. Tu peux vérifier avec `dig ton-domaine.com` ou sur [dnschecker.org](https://dnschecker.org).

Tout ce qui suit se fait **en SSH sur le VPS**, pas sur ton PC.

---

## 1. Connexion et mise à jour du VPS

```bash
ssh root@<IP_DE_TON_VPS>
apt update && apt upgrade -y
```

---

## 2. Installer Docker

```bash
curl -fsSL https://get.docker.com | sh
```

Vérifier que ça fonctionne :

```bash
docker --version
docker compose version
```

---

## 3. Installer Nginx et Certbot (sur le VPS, hors Docker)

```bash
apt install -y nginx certbot python3-certbot-nginx
systemctl enable nginx
```

---

## 4. Pare-feu (UFW)

N'autoriser que ce qui doit être public — tout le reste (PostgreSQL, Redis, l'app Docker, Reverb) n'écoute que sur `127.0.0.1` et n'a de toute façon pas besoin d'être ouvert :

```bash
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw enable
```

---

## 5. Récupérer le code sur le VPS

Deux façons de faire, choisis celle qui te convient.

**Option A — avec Git** (si le projet est sur GitHub/GitLab) :

```bash
mkdir -p /var/www && cd /var/www
git clone <URL_DE_TON_DEPOT> sanaga24
cd sanaga24
```

**Option B — transfert direct depuis ton PC**, si le code n'est pas encore sur un dépôt distant. Depuis ton PC (pas le VPS) :

```bash
rsync -avz --exclude 'node_modules' --exclude 'vendor' --exclude '.git' \
    /home/amsa/Bureau/dev/SANAGA24/Sanaga24-main/ root@<IP_DE_TON_VPS>:/var/www/sanaga24/
```

---

## 6. Configurer l'environnement de production

Sur le VPS, dans `/var/www/sanaga24` :

```bash
cp .env.production.example .env
nano .env
```

Remplace chaque `<CHANGE_ME_...>` :

- `APP_URL`, `SESSION_DOMAIN`, `REVERB_HOST`, `MAIL_FROM_ADDRESS` → ton nom de domaine réel.
- `DB_PASSWORD` → un mot de passe fort. Tu peux en générer un avec :
  ```bash
  openssl rand -base64 32
  ```
- `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` → trois chaînes aléatoires distinctes, par exemple :
  ```bash
  openssl rand -hex 16   # à lancer trois fois, une valeur par variable
  ```

Puis génère la clé de chiffrement de l'application (`APP_KEY`) :

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --force
```

Cette commande construit l'image une première fois (peut prendre quelques minutes) et écrit directement `APP_KEY=...` dans ton `.env`.

---

## 7. Construire et démarrer l'application

```bash
docker compose -f docker-compose.prod.yml up -d --build
```

Au premier démarrage, le conteneur `app` exécute automatiquement : le lien de stockage (`storage:link`), les migrations, le seed des rôles/permissions, et la mise en cache de la config — pas besoin d'y toucher.

Vérifier que tout tourne :

```bash
docker compose -f docker-compose.prod.yml ps
```

Tu dois voir `app`, `queue`, `scheduler`, `reverb`, `pgsql` et `redis` avec le statut `Up` (ou `healthy` pour `pgsql`/`redis`).

À ce stade, le site répond déjà en local sur le VPS :

```bash
curl -I http://127.0.0.1:8000
```

(Une réponse `HTTP/1.1 200 OK` ou une redirection confirme que l'app tourne — il ne manque plus que Nginx + le domaine + le HTTPS.)

---

## 8. Configurer Nginx (le reverse proxy public) et le certificat SSL

Copie le modèle fourni et remplace `<CHANGE_ME_DOMAIN>` par ton vrai domaine :

```bash
cp docker/production/host-nginx.conf.example /etc/nginx/sites-available/sanaga24
sed -i 's/<CHANGE_ME_DOMAIN>/ton-domaine.com/g' /etc/nginx/sites-available/sanaga24
ln -s /etc/nginx/sites-available/sanaga24 /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx
```

`nginx -t` doit afficher `syntax is ok` / `test is successful`. Si une erreur apparaît, relis le message — c'est presque toujours une faute de frappe dans le domaine.

Maintenant, le certificat SSL — Certbot modifie automatiquement le fichier Nginx pour ajouter le HTTPS et la redirection HTTP→HTTPS :

```bash
certbot --nginx -d ton-domaine.com -d www.ton-domaine.com
```

Certbot demande une adresse email (pour les alertes d'expiration) et propose d'activer la redirection HTTPS — accepte. Le renouvellement automatique est déjà configuré par le paquet `certbot` (vérifiable avec `systemctl list-timers | grep certbot`), rien à faire de plus.

---

## 9. Vérifier que le site est en ligne

Ouvre `https://ton-domaine.com` dans un navigateur. Le cadenas HTTPS doit apparaître.

Si une erreur s'affiche, va directement à la section **Dépannage** plus bas.

---

## 10. Créer ton premier compte administrateur

En production, aucun compte de démonstration n'est créé automatiquement (contrairement à l'environnement de développement). Crée ton compte admin réel :

```bash
docker compose -f docker-compose.prod.yml exec app php artisan tinker
```

Puis dans la console qui s'ouvre :

```php
$user = \App\Models\User::create([
    'name' => 'Ton Nom',
    'email' => 'toi@ton-domaine.com',
    'password' => \Illuminate\Support\Facades\Hash::make('UN_MOT_DE_PASSE_FORT'),
    'email_verified_at' => now(),
]);
$user->assignRole('admin');
exit
```

Connecte-toi ensuite sur `https://ton-domaine.com/login`.

---

## 11. Email (optionnel mais recommandé)

Par défaut (`MAIL_MAILER=log`), les emails — par exemple la notification "quelqu'un a répondu à votre commentaire" — sont écrits dans les logs au lieu d'être réellement envoyés. Le site fonctionne très bien sans, mais personne ne recevra ces notifications par email tant que ce n'est pas configuré.

Pour activer l'envoi réel, configure un service SMTP (ton hébergeur email, ou un service comme celui de ton registrar) dans `.env` :

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.ton-fournisseur.com
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_SCHEME=tls
```

Puis :

```bash
docker compose -f docker-compose.prod.yml up -d --force-recreate app queue
```

---

## 12. Mettre à jour le site après une modification du code

Sur le VPS, depuis `/var/www/sanaga24` :

```bash
git pull                                   # ou re-transférer via rsync (étape 5, option B)
docker compose -f docker-compose.prod.yml up -d --build
```

La reconstruction réexécute automatiquement les migrations et régénère les caches — aucune autre action nécessaire.

---

## 13. Sauvegardes

**Base de données** (le plus important — articles, utilisateurs, commentaires) :

```bash
docker compose -f docker-compose.prod.yml exec pgsql pg_dump -U sanaga24 sanaga24 > sauvegarde-$(date +%Y%m%d).sql
```

**Photos et vidéos** uploadées (volume `app-storage`) :

```bash
docker run --rm -v sanaga24_app-storage:/data -v $(pwd):/backup alpine \
    tar czf /backup/storage-$(date +%Y%m%d).tar.gz -C /data .
```

Pense à copier régulièrement ces fichiers hors du VPS (vers ton PC, ou un stockage externe) — une sauvegarde qui reste sur le même serveur ne protège pas contre une panne du serveur lui-même.

---

## 14. Commandes utiles au quotidien

```bash
docker compose -f docker-compose.prod.yml logs -f app        # logs en direct de l'app
docker compose -f docker-compose.prod.yml logs -f queue       # logs des jobs en file d'attente
docker compose -f docker-compose.prod.yml ps                  # état des conteneurs
docker compose -f docker-compose.prod.yml restart app         # redémarrer juste l'app
docker compose -f docker-compose.prod.yml exec app php artisan tinker   # console interactive
```

---

## 15. Dépannage

- **Le site affiche une erreur 502 Bad Gateway** → l'application Docker n'a probablement pas démarré. Vérifier avec `docker compose -f docker-compose.prod.yml ps` et `docker compose -f docker-compose.prod.yml logs app`.
- **`nginx -t` échoue** → vérifier qu'il n'y a pas de faute de frappe dans le nom de domaine dans `/etc/nginx/sites-available/sanaga24`.
- **`certbot --nginx` échoue avec une erreur de validation** → le DNS n'a probablement pas encore fini de se propager (attendre et réessayer), ou le pare-feu bloque le port 80 (vérifier `ufw status`).
- **Images ou vidéos en 404 après une mise à jour** → le lien symbolique de stockage a disparu :
  ```bash
  docker compose -f docker-compose.prod.yml exec app php artisan storage:link --force
  ```
- **Les commentaires/likes en direct ne se mettent plus à jour** → vérifier que le conteneur `reverb` tourne (`docker compose -f docker-compose.prod.yml ps`) et que la section `/app/` du fichier Nginx (étape 8) est bien présente.
- **Erreur 500 après une mise à jour du code** → consulter les logs :
  ```bash
  docker compose -f docker-compose.prod.yml logs --tail=100 app
  ```

---

## 16. Ce que fait chaque service

| Service     | Rôle                                                                 |
|-------------|-----------------------------------------------------------------------|
| `app`       | Nginx interne + PHP-FPM — sert le site. Seul service exposé (en local, sur 127.0.0.1:8000). |
| `queue`     | Traite les jobs en file d'attente (emails de notification).          |
| `scheduler` | Exécute les tâches planifiées chaque minute (publication programmée, archivage). |
| `reverb`    | Serveur WebSocket auto-hébergé pour les commentaires/likes en temps réel. |
| `pgsql`     | Base de données PostgreSQL — jamais exposée publiquement.            |
| `redis`     | Cache/sessions — jamais exposé publiquement.                         |

Aucun de ces services (à part `app` et `reverb`, uniquement sur `127.0.0.1`) n'est accessible depuis l'extérieur du VPS — seul le Nginx du VPS (étape 8) parle au monde extérieur, en HTTPS.
