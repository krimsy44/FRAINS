# Suivi du cahier des charges FRAINS Agro

Source : « Cahier des charges — Plateforme FRAINS Agro.pdf », version 1.0, 23 pages, relu intégralement le 13 septembre 2026.

Les exemples (85 producteurs, 1 250 clients, paliers 100/500/1 000 kg, dates, prix) ne sont pas des données réelles. Aucun chiffre d’exploitation ni contenu institutionnel ne doit être inventé. « Prévu » ne signifie ni développé ni testé.

**État actuel :** voir [la recette détaillée du 13 septembre](recette-2026-09-13.md). Le tableau ci-dessous conserve l’écart initial observé avant les développements de cette reprise ; les preuves et limites actuelles sont décrites dans la recette.

## Registre exhaustif des sections

| § | Exigences à couvrir | Lot / état initial de cette reprise |
|---|---|---|
| 1–4 | Identité du GIE, maraîchage Sénégal/Afrique de l’Ouest, gestion centralisée, cibles professionnelles et membres | Cadrage ; identité légale et contenus officiels à fournir |
| 5 | Public : accueil, à propos, activités, produits, gros, actualités, galerie, contact, connexion/inscription. Privé : agriculture, producteurs, parcelles, cultures, productions, récoltes, produits, stocks, clients, commandes, devis, factures, paiements, livraisons, livreurs, zones, publications, galerie, rapports, statistiques, utilisateurs, paramètres | Navigation et modules à compléter |
| 6 | Accueil : logo, menu, bannière, présentation, produits disponibles/à la une/en gros, chiffres réels, activités, dernières publications, galerie, témoignages, contact, coordonnées | Partiel ; contenu et médias réels requis |
| 7 | Histoire, mission, vision, valeurs, activités, zones de production, producteurs, objectifs, partenaires | Site public + paramètres éditoriaux |
| 8 | Nom, photo, description, catégorie, unité, prix, gros, minimum, stock, conditionnement, origine, disponibilité ; ajout/modification/retrait | Catalogue à enrichir |
| 9–10 | Quantité, conditionnement, lieu/date, calcul fixe ou devis ; paliers modifiables | Tarifs et devis B2B à réaliser |
| 11 | Identité, entreprise, téléphone, email, adresse, ville, zone ; tableau de bord client, commandes/devis/factures/paiements/livraisons/adresses/profil | Inscription et commandes présentes ; reste à compléter |
| 12 | Ajout, modification, retrait, total, livraison, confirmation | Présent ; conserver les tests |
| 13 | Numéro unique ; nouvelle, confirmée, préparation, prête, livraison, livrée, terminée, annulée, refusée, partiellement livrée | Livraison partielle et historique à ajouter |
| 14–15 | Zones et frais administrables ; livreurs (identité, téléphone, véhicule/type, zone, disponibilité), affectation, suivi client, livraisons en cours | Registre et espaces logistiques à compléter |
| 16 | Producteurs : identité, téléphone, adresse, zone, adhésion, statut, parcelles/cultures/productions/récoltes | Phase agricole |
| 17 | Parcelles : référence, producteur, localisation, superficie, culture, statut, exploitation | Phase agricole |
| 18 | Cultures : producteur/parcelle, superficie, plantation, récolte prévue, production estimée | Phase agricole |
| 19 | Production prévue/réelle, date, récolte, vendu, restant, pertes | Phase agricole ; traçabilité des quantités nécessaire |
| 20 | Entrées récolte/approvisionnement/retour ; sorties vente/perte/dommage/autre ; disponible/réservé/vendu/pertes/historique | Mouvements manuels et récoltes à relier |
| 21 | Faible stock, rupture, dépassement, paiement en retard, livraison en retard, nouveau devis | Alertes à compléter |
| 22 | Liste/types clients, commandes, achats cumulés, paiements, factures, historique | Gestion clients à compléter |
| 23 | Demande → analyse → proposition → acceptation → commande → préparation → livraison | Devis à réaliser |
| 24 | Livraison, virement, mobile, en ligne, partiel, différé ; total/payé/reste | Encaissement intégral manuel présent ; étendre le registre |
| 25 | PDF : logo, GIE, numéro, client, produits, quantités, prix, frais, total, payé, reste, date | Factures à réaliser ; identité officielle à fournir |
| 26 | Publications : actualité, activité, récolte, événement, annonce, information, conseil ; titre/image/contenu/catégorie/auteur/date/statut | CMS à réaliser |
| 27 | Galerie : champs, cultures, récoltes, producteurs, produits, activités, événements, livraisons | Galerie à réaliser ; photos réelles à fournir |
| 28 | Indicateurs producteurs/clients/produits/commandes/livraisons/stocks/ventes ; graphiques CA, ventes, commandes, produits, production, stocks, livraisons, clients | Tableaux de bord à étendre |
| 29 | Administrateur, gestionnaire, commercial, stock, responsable livraison, producteur (ses productions), livreur (ses livraisons), client (ses données) | Autorisations par module à étendre et tester |
| 30 | Événements client et administration ; email/SMS/WhatsApp possibles | Notifications internes puis canaux configurés ; aucun envoi externe sans autorisation |
| 31 | Filtres nom, catégorie, disponibilité, prix, zone de production, gros | Nom/catégorie présents ; autres à ajouter |
| 32 | Rapports commerciaux, agricoles, stock et livraison, performances livreurs | Rapports/export à réaliser |
| 33 | Authentification, rôles/permissions, mots de passe hachés, CSRF/XSS/SQLi, HTTPS, sauvegardes, journal d’actions, sessions | Protections Laravel + contrôle rôles présents ; audit et exploitation à compléter |
| 34 | Ordinateur/tablette/smartphone, priorité mobile | CSS existant ; nouveaux écrans à vérifier |
| 35 | Laravel/PHP/MySQL, HTML/CSS/JS, Bootstrap recommandé, Vue facultatif ; Linux/Apache ou Nginx/SSL/sauvegardes | Laravel/MySQL/Apache local ; hébergement de production à définir |
| 36 | Architecture API REST pour futurs clients mobiles et intégrations | Contrats et endpoints à prévoir avec permissions |
| 37 | Futur : Android/iOS, appli livreur, géolocalisation, marketplace, abonnements, contrats B2B | Évolutions explicitement futures, pas des écrans factices à déclarer livrés |
| 38 | Parcours complet visiteur → réception → commande terminée | Présent pour livraison/règlement intégraux ; étendre sans régression |
| 39 | Producteur → production → récolte → stock → réservation → préparation → livreur → livraison → paiement → facture → rapport | Intégration métier à réaliser |
| 40 | Phase 1 commercial, phase 2 agriculture, phase 3 automatisation | Ordre de réalisation à respecter |
| 41 | CA, commandes, panier moyen, clients/actifs, meilleures ventes, volumes vendus/produits, taux/délai livraison, annulations, pertes, évolution | Indicateurs réels ; ne pas additionner des unités incompatibles |
| 42–43 | Chaîne centralisée, plateforme professionnelle évolutive, pas seulement une vitrine | Critère de recette de bout en bout |

## Points dépendant du GIE et de l’exploitation

- Logo/photos/témoignages autorisés, histoire, partenaires, coordonnées et immatriculation.
- Tarifs et seuils de devis, zones/frais, conditionnements et unités : administrables, sans imposer les exemples.
- Règles de crédit, échéances, annulations/remboursements et livraisons partielles à rendre explicites.
- Fournisseur et identifiants de paiement en ligne ; SMTP, SMS et WhatsApp ; autorisation d’envoi réel.
- Domaine/hébergement/HTTPS, sauvegarde distante et essai de restauration.
- Applications mobiles, marketplace, abonnements, contrats et géolocalisation : phases futures spécifiées, périmètre à détailler avant réalisation.

## Recette

Chaque lot doit citer ses routes, ses règles, les tests exécutés et ses limites. Une compilation ou un lien de menu ne prouve pas qu’un module fonctionne. Les tests automatiques emploient SQLite en mémoire ; les migrations locales doivent être additives et préserver MySQL.
