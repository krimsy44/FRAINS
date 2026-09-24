# Connexions client et administration

Le guard `web` identifie le client, le guard `admin` identifie le personnel. Les deux identités peuvent être connectées dans le même navigateur. Le cookie de session Laravel reste commun, mais les clés de connexion et les destinations après connexion sont distinctes. Une déconnexion retire uniquement l'identité de l'espace concerné.

Le middleware `AuthenticationSpace` sélectionne le guard avant l'authentification : `admin` pour `/admin` et `/admin/*`, `web` ailleurs. Les devis, notifications et factures du personnel ont leurs propres routes sous `/admin`. Les liens de notification adressés aux clients restent publics ou propres à leur espace.

Une ancienne connexion de gestion enregistrée dans `web` est retirée lors de la visite du site public ; le personnel doit se reconnecter une fois via `/admin/connexion` après cette évolution. Les comptes et données ne changent pas.

Vérification : `IndependentLoginTest` utilise les deux formulaires de connexion, recharge les identités depuis la session et vérifie les deux sens de déconnexion, les redirections séparées et la conservation des formulaires ouverts de l'autre espace.
