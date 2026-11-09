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
php85 bin/console asset-map:compile --env=prod
php85 bin/console cache:clear --env=prod
```

Après chaque mise à jour : sauvegardez la base, déployez le code, lancez les migrations puis recompilez les assets.

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

Planifiez chaque minute `php85 bin/console app:notify-aired-episodes --env=prod`. Les imports et les notifications Push dépendent de ce worker.

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
