# Contribuer

Merci de vous intéresser à ce projet qui est d'une assez grande utilité dans la communication des événements de la région genevoise. Les contributions sont bienvenues car il y a actuellement pas mal à faire, surtout dans la rénovation. Les informations ici vous permettront de savoir plus clairement de quelle manière vous pouvez aider à l'amélioration du site.

Vous pouvez contribuer de plusieurs manières :
- reporter des bugs
- participer aux tests après que des changements ont été faits
- aider à l'administration du site actuel
- [faire un don](https://ladecadanse.darksite.ch/articles/faireUnDon.php)
et spécifiquement, si vous êtes développeur :
- compléter la documentation : le [README](README.md), le [Wiki](https://github.com/agilare/ladecadanse/wiki)...
- résoudre ou proposer des Issues (corrections, améliorations...)

## Contexte

À côté du travail régulier d'administration du site de prod (contenu et technique), ce projet demande une maintenance classique (mises à jour, documentation, etc.), essentiellement effectuée par son auteur, durant son temps libre et bénévolement. Quand du temps est davantage disponible, je fais des mises à jour plus conséquentes comme des corrections, du refactoring voire des améliorations.
Je m'occupe donc de gérer ce projet dans son ensemble et décide des lignes directrices à suivre. J'essaie de réagir promptement aux diverses demandes, mais en raison du peu de temps dont je dispose, cela peut demander un certain délai.

## Développer

La version actuelle a été créée en 2008 (donc avec les standards de l'époque et un modeste niveau de programmation) et est aujourd'hui assez legacy, malgré quelques modernisations et nettoyages effectués ces dernières années (voir le [CHANGELOG](CHANGELOG.md)). Aujourd'hui le but principal est de réduire cette dette technique afin de reprendre sur de bonnes bases l'amélioration de l'application. À cet effet, le **[projet de modernisation](https://github.com/users/agilare/projects/2/views/1)** a été conçu pour résorber pas à pas les parties les plus obsolètes.
Le mode de développement actuel du projet est brièvement décrit dans le [Wiki](https://github.com/agilare/ladecadanse/wiki#organisation)

### Tâches

Vous pouvez reprendre des Issues existantes — en choisissant de préférences les plus prioritaires (label _high_) — ou en créer de nouvelles.
Cela qui consiste à :
- spécifier, concevoir une amélioration
- reproduire, cerner des bugs
- développer et tester

Il est aussi possible de proposer des fonctionnalités, bien qu'en ce moment l'accent est mis surtout sur les _[bugs](https://github.com/agilare/ladecadanse/issues?q=is%3Aissue+is%3Aopen+label%3Abug)_ et le _[refactoring](https://github.com/agilare/ladecadanse/labels/refactoring)_ (notamment au sein du projet de modernisation) pour les raisons décrites ci-dessus.
Si vous ne connaissez pas encore bien le code, vous pouvez commencer par une _[Good first issue](https://github.com/agilare/ladecadanse/issues?q=is%3Aopen+is%3Aissue+label%3A%22good+first+issue%22)_.
Deux autres labels précisent leur domaine d'application :
- _[improve-information](https://github.com/agilare/ladecadanse/labels/improve-information)_ : amélioration du contenu (diffusion, accès, volume)
- _[edition](https://github.com/agilare/ladecadanse/labels/edition)_ : amélioration du "back-office", donc surtout pour les utilisateurs qui ajoutent des événements

Il y a des tests automatisés qui couvrent les fonctionnalités de base et ils peuvent être améliorés. Si cela vous intéresse, je vous invite à consulter leur [README](tests/README.md) qui décrit la stratégie suivie et les 2 types de tests existants : E2E (avec Selenium IDE) et fonctionnels pour l'API (avec Codeception)

### Démarrage

Pour aborder le travail, vous pouvez d'abord chercher à connaître suffisamment le fonctionnement du site, pour cela le Wiki apporte quelques infos dans :
- [Fonctionnement](https://github.com/agilare/ladecadanse/wiki#fonctionnement-de-lapplication)
- [Résumé conceptuel](https://github.com/agilare/ladecadanse/wiki#r%C3%A9sum%C3%A9-conceptuel)

Pour un abord plus pratique, vous pouvez aussi explorer le site actuel, voire [créer un compte](https://ladecadanse.darksite.ch/user-register.php) *Acteur culturel* qui vous montrera les fonctionnalités de back-office, utilisées quotidiennement pour enrichir le site.

Ensuite, si vous êtes intéressés au travail sur une Issue, je vous invite à la préciser si besoin (spécifications, conception... n'hésitez pas la commenter ou créer une [Discussion](https://github.com/agilare/ladecadanse/discussions)) puis la réaliser sous forme de [pull request](https://github.com/agilare/ladecadanse/pulls). Une fois acceptée, le changement sera intégrée dans une future release et déployé, selon mes disponibilités.
Les modifications doivent suivre dans la mesure du possible les [Commits Conventionnels](https://www.conventionalcommits.org/fr/v1.0.0/)

La mise en place de l'environnement de développement est décrite dans "Installation locale" du [README](README.md).
