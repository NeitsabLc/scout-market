# Premier déploiement sur web01

Cette procédure publie Scout Market à l’adresse
<https://scout-market.neitsab.net> selon l’architecture existante :

```text
Internet → proxy01 / Traefik (192.168.2.6) → web01:8082 (192.168.2.18)
```

Le déploiement ne comporte ni CI, ni registre d’images applicatives, ni sauvegarde
`age`. Les conteneurs sont construits localement sur `web01` depuis le tag GitHub.

## 1. Préparer le DNS

Créer l’enregistrement DNS `A` de `scout-market.neitsab.net` vers l’adresse IPv4
publique de `proxy01`. Ajouter un enregistrement `AAAA` uniquement si Traefik est
réellement joignable en IPv6. Les ports publics 80 et 443 doivent arriver sur
`proxy01` pour la redirection HTTP et le certificat Let’s Encrypt.

## 2. Installer Scout Market sur web01

Depuis un poste ayant accès au réseau `192.168.2.0/24` :

```shell
ssh web01
cd /srv/docker
git clone --branch v0.1.4 --depth 1 \
  https://github.com/NeitsabLc/scout-market.git scout-market
cd /srv/docker/scout-market
cp .env.simple-prod.example .env
cp deploy/web01/pg_hba.simple-prod.conf docker/postgres/pg_hba.prod.conf
chmod 600 .env
chmod 644 docker/postgres/pg_hba.prod.conf
```

Générer les secrets :

```shell
openssl rand -hex 32
openssl rand -hex 24
```

Dans `.env`, mettre le premier dans `APP_SECRET`, puis recopier exactement le second
dans `POSTGRES_PASSWORD`, `POSTGRES_APP_PASSWORD`, `POSTGRES_MIGRATOR_PASSWORD`,
`POSTGRES_HEALTHCHECK_PASSWORD` et `POSTGRES_BACKUP_PASSWORD`.

Les autres valeurs sont déjà préparées pour cette infrastructure : domaine public,
écoute Nginx sur `192.168.2.18:8082`, réseau Docker `172.31.0.0/24`, proxy de
confiance `192.168.2.6` et sauvegarde `age` désactivée.

Construire et démarrer :

```shell
make prod-config
make prod-build
docker compose -f compose.yaml -f compose.prod.yaml up -d --wait database
make prod-db-bootstrap
make prod-up
make prod-ps
curl --fail --silent --show-error \
  --header 'Host: scout-market.neitsab.net' \
  http://192.168.2.18:8082/login >/dev/null
```

Ne pas exécuter `prod-db-roles-prepare`, `prod-db-roles-finalize`, `backup-now` ou
`production-smoke` pour ce premier déploiement simplifié.

### Configurer l’envoi SMTP OVH MX Plan

Le compte d’envoi est `no-reply@neitsab.net`. OVH MX Plan Europe utilise
`smtp.mail.ovh.net`, le port `465` et SSL/TLS implicite. Le mot de passe doit être
encodé pour une URI avant d’être placé dans le DSN Symfony.

Sur `web01`, depuis `/srv/docker/scout-market` :

```shell
printf 'Mot de passe SMTP OVH : '
read -r -s scout_market_smtp_password
printf '\n'
export SCOUT_MARKET_SMTP_PASSWORD="$scout_market_smtp_password"
scout_market_smtp_password_encoded=$(php -r \
  'echo rawurlencode((string) getenv("SCOUT_MARKET_SMTP_PASSWORD"));')
sed -i \
  "s|^MAILER_DSN=.*|MAILER_DSN=smtps://no-reply%40neitsab.net:${scout_market_smtp_password_encoded}@smtp.mail.ovh.net:465|" \
  .env
unset SCOUT_MARKET_SMTP_PASSWORD scout_market_smtp_password \
  scout_market_smtp_password_encoded
chmod 600 .env
make prod-up
```

Tester ensuite le transport en envoyant un message au compte technique :

```shell
docker compose -f compose.yaml -f compose.prod.yaml exec php \
  php bin/console mailer:test no-reply@neitsab.net \
  --from=no-reply@neitsab.net --subject='Test SMTP Scout Market' \
  --body='Le transport SMTP OVH de Scout Market fonctionne.' \
  --env=prod --no-debug
```

## 3. Restreindre le port 8082 sur web01

Toujours depuis `/srv/docker/scout-market` :

```shell
sudo install -m 0755 deploy/web01/scout-market-docker-firewall \
  /usr/local/sbin/scout-market-docker-firewall
sudo install -m 0644 deploy/web01/scout-market-docker-firewall.service \
  /etc/systemd/system/scout-market-docker-firewall.service
sudo systemctl daemon-reload
sudo systemctl enable --now scout-market-docker-firewall.service
sudo systemctl status --no-pager scout-market-docker-firewall.service
```

Le script autorise le port Docker 8082 uniquement depuis `proxy01` (`192.168.2.6`).
PostgreSQL n’a aucun port publié dans la surcharge de production.

## 4. Ajouter la route dans Traefik sur proxy01

Le fichier à installer est `deploy/proxy01/scout-market.yml`. Sur `proxy01`, retrouver
d’abord le répertoire hôte monté comme répertoire dynamique de Traefik :

```shell
ssh proxy01
docker inspect traefik \
  --format '{{ range .Mounts }}{{ println .Source "->" .Destination }}{{ end }}'
```

Repérer la source correspondant à `/etc/traefik/dynamic`, puis y installer le fichier.
Exemple si la source affichée est `/srv/traefik/dynamic` :

```shell
scp bastien@192.168.2.18:/srv/docker/scout-market/deploy/proxy01/scout-market.yml \
  /tmp/scout-market.yml
sudo install -m 0644 /tmp/scout-market.yml /srv/traefik/dynamic/scout-market.yml
rm /tmp/scout-market.yml
docker logs --since 2m traefik
```

Traefik recharge normalement le fichier automatiquement. La configuration utilise les
entrypoints `web` et `websecure`, le résolveur `letsencrypt`, redirige HTTP vers HTTPS
et transmet le `Host` original à `web01:8082`.

## 5. Vérifier puis créer l’administrateur

Depuis n’importe quel poste :

```shell
curl --fail --show-error --silent \
  https://scout-market.neitsab.net/login >/dev/null
```

Puis sur `web01` :

```shell
cd /srv/docker/scout-market
make prod-create-admin \
  EMAIL=admin@neitsab.net \
  PRENOM=Bastien \
  NOM=Administrateur
```

La commande demande le mot de passe de façon masquée. Vérifier ensuite la connexion
depuis le navigateur.

## 6. Mise à jour ultérieure

```shell
ssh web01
cd /srv/docker/scout-market
git fetch --tags
git checkout v0.1.5
```

Mettre `APP_IMAGE_TAG=v0.1.5` dans `.env`, puis :

```shell
make prod-build
make prod-db-update
make prod-up
make prod-ps
```

Cette première installation ne dispose ni d’une sauvegarde hors serveur ni de rôles
PostgreSQL séparés. Ces protections devront être ajoutées avant de considérer
l’hébergement comme durable.
