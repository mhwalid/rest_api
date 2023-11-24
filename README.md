# Installation

**Créer un fichier .env.local** (en fonction de votre chaine de connexion) : 

```
DATABASE_URL="mysql://login:mdp@127.0.0.1:3306/db-name"
```

Assurer vous de créer la DB en amont

**Installer les dépendances du projet :** 

```
composer install
```

**Lancer les migrations :** 

```
php bin/console doctrine:migrations:migrate
```

**Lancer le serveur :** 

```
symfony server:start
```

Vous trouverez un postman.json pour tester l'API dans Postman plus facilement.