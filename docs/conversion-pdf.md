# Accepter les PDF dans les champs image

Désactivé par défaut. Le [README](../README.md#accepter-les-pdf-dans-les-champs-image) donne le drapeau ; ce fichier-ci détaille le mécanisme et l'installation d'Imagick.

## Le drapeau

`PDF_CONVERSION_ENABLED` d'`app/env.php` prend trois valeurs :

```php
define("PDF_CONVERSION_ENABLED", false);       // personne
define("PDF_CONVERSION_ENABLED", 'preview');   // administrateurs seulement
define("PDF_CONVERSION_ENABLED", true);        // tout le monde
```

Tant que le drapeau est absent ou faux, les champs flyer et image n'annoncent pas le PDF, ne l'acceptent pas, et pdf.js n'est jamais chargé : le formulaire est exactement celui d'avant.

`'preview'` sert à éprouver une fonctionnalité conséquente sur le site en ligne sans l'exposer au public. Le texte d'aide signale alors qu'on est seul à la voir — sans quoi une préversion s'oublie et l'on croit la fonctionnalité livrée. Le mécanisme est générique (`Ladecadanse\FeatureFlag`) et se réutilise pour tout autre drapeau : voir la classe pour la marche à suivre, `dynamicConstantNames` de `phpstan.neon` compris.

> [!NOTE]
> Noter la **chaîne littérale** plutôt que `FeatureFlag::PREVIEW` : `app/env.php` est chargé avant l'autoloader, aucune classe n'y est encore connue.

## Deux voies de conversion

Le formulaire d'événement accepte alors les PDF de deux façons, dont une seule demande quelque chose au serveur :

| Voie | Conversion | Dépendance |
|---|---|---|
| Bouton « Envoyer » (champ fichier) | le navigateur, avec pdf.js | aucune |
| « ou coller une URL » | le serveur, avec Imagick | `imagick` + Ghostscript |

Le second cas ne peut pas être confié au navigateur : il lui faudrait lire une URL d'un autre domaine, ce que CORS et la CSP du site interdisent. Sans `imagick`, le site marche normalement et l'utilisateur reçoit un message qui le renvoie vers le bouton « Envoyer » — rien n'est cassé, la fonction est simplement absente.

## Installer Imagick

Avec Docker, tout est déjà dans `docker/php/Dockerfile`, y compris l'autorisation du coder PDF d'ImageMagick.

**Sur un poste Windows/Laragon**, trois pièces, à faire correspondre :

1. **Ghostscript** — [téléchargement](https://www.ghostscript.com/releases/gsdnld.html), version 64 bits. C'est lui qui décode réellement le PDF ; Imagick ne fait que l'appeler. Vérifier ensuite que `gswin64c.exe` répond depuis un terminal (l'installateur ajoute normalement son `bin` au `PATH`).
2. **ImageMagick** — l'archive *Windows binary release* correspondant à la version attendue par l'extension.
3. **L'extension PHP** — `php_imagick.dll` doit correspondre **exactement** au PHP de Laragon : version (8.4), architecture (x64) et surtout *thread safety*. Laragon sous Apache utilise un PHP **TS** (`php -i | findstr "Thread"` renvoie `Thread Safety => enabled`) ; prendre la DLL `ts-vs17-x64`. Copier `php_imagick.dll` dans `php/ext/`, les `CORE_RL_*.dll` dans le répertoire de `php.exe`, puis ajouter `extension=imagick` au `php.ini` et redémarrer Apache.

Contrôle, une fois le tout en place :

```bash
php -r "echo extension_loaded('imagick') ? implode(',', Imagick::queryFormats('PDF')) : 'absent', PHP_EOL;"
```

> [!TIP]
> La réponse attendue est `PDF`. Un `absent` signale que la DLL ne correspond pas au PHP en service — c'est de loin la cause la plus fréquente, et elle est silencieuse : PHP ne charge simplement pas l'extension.

> [!TIP]
> Si `imagick` répond mais que la conversion échoue sur `not authorized`, c'est la `policy.xml` d'ImageMagick qui refuse le coder PDF (héritage de CVE-2018-16509) : y passer `<policy domain="coder" rights="none" pattern="PDF" />` en `rights="read"`.
