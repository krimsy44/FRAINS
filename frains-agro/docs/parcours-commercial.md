# Utiliser FRAINS Agro

## Client

1. Consulter le catalogue, utiliser les filtres et ajouter les quantités au panier.
2. Se connecter ou créer un compte. Le panier est conservé.
3. Choisir l’adresse, la zone, la date souhaitée et le mode de règlement, puis confirmer.
4. Suivre la commande, consulter les règlements et ouvrir la facture PDF depuis Mon espace.
5. Pour une demande en gros, choisir Demander un devis, préciser les produits, quantités, conditionnements et livraison. Consulter puis accepter ou décliner la proposition reçue.

## Équipe du GIE

1. Se connecter à l’administration. Le menu dépend du rôle.
2. Renseigner les paramètres officiels et le logo, les catégories, les zones/frais et les comptes internes.
3. Créer les produits. Depuis Modifier un produit, ouvrir Photo, conditionnement, origine et tarifs.
4. Alimenter les stocks par approvisionnement ou par récolte ; renseigner les seuils d’alerte.
5. Traiter les demandes de devis et les commandes : confirmer, préparer, puis déclarer prêtes.
6. Affecter un livreur et la date. Le responsable livraison ou le livreur affecté peut confirmer le départ.
7. Enregistrer les quantités cumulées réceptionnées, partiellement ou intégralement.
8. Enregistrer uniquement les règlements réellement reçus. Les versements partiels et le reste à payer sont conservés. Un crédit professionnel nécessite un plafond suffisant et une échéance.
9. Terminer la commande après livraison et règlement intégral.
10. Consulter les rapports, alertes, notifications et exports ; publier les actualités et photos du GIE.

## Agriculture

Créer le producteur, sa parcelle, puis sa culture. Enregistrer la récolte brute et les pertes : le net entre en stock. Les lots suivent les sorties FIFO, permettant de voir le vendu et le restant d’une récolte. Un producteur connecté ne voit que ses cultures et récoltes.

## Vérification et exploitation

Voir [la recette détaillée](recette-2026-09-13.md), [le registre des 43 sections](suivi-cahier-des-charges.md) et [l’exploitation/API](exploitation-et-api.md).

Les tests utilisent SQLite en mémoire. Les migrations locales sont additives. Ne pas lancer migrate:fresh sur la base du GIE.

La phase d’automatisation externe reste conditionnée au choix des prestataires, aux identifiants et à la configuration de production décrits dans la recette.
