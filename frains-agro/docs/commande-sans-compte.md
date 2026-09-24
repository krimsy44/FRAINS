# Commande publique sans compte

Le panier public recueille uniquement prénom, nom, téléphone et zone de livraison. Les frais sont affichés avant confirmation. Le règlement est à la livraison ; adresse précise et date sont à confirmer par téléphone par l’équipe.

POST /panier/commander utilise une session, un jeton de validation, un verrou de session, une limitation de débit et la transaction de réservation des stocks. Les prix et frais sont recalculés côté serveur. Une fiche client sans utilisateur est créée dans la transaction, sans e-mail, mot de passe ni rapprochement automatique par téléphone.

La confirmation /commande/confirmation est limitée à la commande enregistrée dans la session du navigateur. Les anciens comptes, commandes et accès protégés sont conservés pour préserver les données existantes, mais l’inscription et la connexion client ne sont plus proposées dans le menu public.


La migration 2026_09_13_200000_allow_guest_customers rend customers.user_id facultatif et ajoute les coordonnées de contact. Ne pas annuler cette migration après réception de commandes invitées : son rollback retire ces coordonnées.