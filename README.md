<p align="center">
  <img src="public/images/zunatrack-banner-v2.png" alt="ZunaTrack — suivi de séries TV" width="768">
</p>

# ZunaTrack

Application Symfony auto-hébergée de suivi de séries TV, alimentée par TVmaze.

## Prérequis

PHP 8.5 avec Sodium, MariaDB 10.11+, Composer, Node.js et nginx. Configurez `DATABASE_URL`, un `APP_SECRET` aléatoire d’au moins 32 caractères, `DEFAULT_URI`, `MAILER_DSN` et `MESSENGER_TRANSPORT_DSN` dans `.env.local` ou les variables du serveur.

## Installation et mise à jour

```bash
php85 /usr/bin/composer install --no-dev --optimize-autoloader
php85 bin/console doctrine:migrations:migrate --no-interaction --env=prod
php85 bin/console tailwind:build --minify --env=prod
php85 bin/console asset-map:compile --env=prod
php85 bin/console cache:clear --env=prod
```

Après chaque mise à jour : sauvegardez la base, déployez le code, lancez les migrations puis compilez Tailwind avant AssetMapper. Sans cette étape, AssetMapper peut republier un ancien CSS malgré des sources à jour.

La vérification du catalogue depuis l’administration retélécharge aussi les images d’épisodes dont le fichier a disparu malgré une URL enregistrée, et recharge les snapshots incomplets. Les recherches conservent une affiche déjà connue si un nouveau téléchargement échoue. Pour un nouveau résultat, les données légères et l’URL d’affiche TVmaze sont conservées ; une URL HTTPS de static.tvmaze.com sert de repli si le cache local échoue. Une nouvelle recherche retente le téléchargement, sans ouverture de fiche ni import des saisons et épisodes.

Sur la page de connexion, « Rester connecté pendant 30 jours » active un cookie de connexion persistante uniquement si la case est cochée. Les cartes d’épisodes utilisent la couverture de saison, l’affiche de série puis le logo lorsque l’image d’épisode manque ; le compte à rebours reste visible avant diffusion.

## Configuration et publication

Copiez `.env.example` vers `.env` puis renseignez les valeurs propres à l'instance. Le fichier `.env` est ignoré par Git et ne doit jamais être versionné.

Le pipeline GitLab est conservé dans le projet privé `clinux/zunatrack-ci`, afin que le dépôt GitHub public ne contienne aucun fichier GitLab CI. Avant une publication, vérifiez l'état de travail puis poussez explicitement la branche et ses tags sur les deux forges :

```bash
git status --short
git push gitlab main --follow-tags
git push github main --follow-tags
```

Les déploiements de production restent manuels depuis le pipeline GitLab et sont exécutés par le runner auto-hébergé `ams3`.

## Tâches de fond

Exécutez un worker permanent : `php85 bin/console messenger:consume async --time-limit=3600 --memory-limit=128M --env=prod`.

Planifiez chaque minute `php85 bin/console app:notify-aired-episodes --env=prod`. Cette commande récupère aussi les images manquantes des épisodes diffusés des bibliothèques actives, même sans appareil Push. Un snapshot antérieur à la dernière diffusion concernée est renouvelé ; sinon le cache de 24 heures est conservé. Les images sont téléchargées avant la mise en file des notifications : la couverture de l’épisode est envoyée dans `image`, avec l’affiche de série en repli. Si TVmaze échoue, une erreur est journalisée et les notifications restent envoyées avec les données locales. L’affichage de la grande image dépend du navigateur et du système. Les imports et les notifications Push dépendent du worker ; les notifications nécessitent aussi cette commande de découverte.

Pour un serveur systemd avec l'utilisateur `zunatrack` et le code dans `/home/zunatrack/www`, installez les unités de [`deploy/systemd/`](deploy/systemd/) :

```bash
sudo cp deploy/systemd/zunatrack-* /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now zunatrack-messenger.service zunatrack-notify.timer
systemctl status zunatrack-messenger.service zunatrack-notify.timer
journalctl -u zunatrack-messenger.service -u zunatrack-notify.service -n 100 --no-pager
```

Vérifiez que `php85` est accessible dans le PATH du service (`command -v php85`), que les variables de production sont disponibles pour `zunatrack` et que l'utilisateur peut écrire dans `var/`. PHP-FPM sert les requêtes web ; les unités lancent PHP en CLI. Si un cron appelle déjà `app:notify-aired-episodes`, désactivez-le pour éviter un doublon.

## Sauvegarde et restauration

Sauvegardez MariaDB et `public/media/posters/`; les clés VAPID sont chiffrées dans la base et nécessitent le même `APP_SECRET` pour être restaurées. Testez une restauration sur une instance isolée avant toute mise à jour majeure.

## Nginx et sécurité

Servez exclusivement `public/`, forcez HTTPS et ajoutez au minimum `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `X-Frame-Options: DENY` et une CSP adaptée aux assets locaux. Ne versionnez jamais `.env.local`, les clés VAPID ou les sauvegardes.

## Vérification

```bash
php85 /usr/bin/composer cs-check
php85 /usr/bin/composer stan
php85 /usr/bin/composer test
php85 bin/console lint:yaml config
php85 bin/console lint:twig templates
php85 /usr/bin/composer audit --locked
```

Pour appliquer le formatage PSR-12/Symfony aux fichiers PHP de `src/` et `tests/`, lancez `php85 /usr/bin/composer cs-fix`. Le contrôle statique PHPStan démarre au niveau 1 sans baseline ; augmentez son niveau au fur et à mesure des corrections de typage.
