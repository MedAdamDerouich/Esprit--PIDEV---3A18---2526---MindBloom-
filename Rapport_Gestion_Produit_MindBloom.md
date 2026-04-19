# Rapport de Gestion : Module Produit & Stock MindBloom
**Objet** : Documentation fonctionnelle du catalogue produits  

---

## 1. Gestion du Catalogue (Côté Administrateur)
Le système permet un contrôle total sur l'inventaire de la parapharmacie.

### 📦 Opérations de Base (CRUD)
- **Création assistée** : Ajout de nouveaux produits avec téléchargement d'images et génération automatique de descriptions par l'intelligence artificielle Groq.
- **Modification rapide** : Mise à jour des prix, des quantités et des descriptions en temps réel.
- **Suppression sécurisée** : Option de suppression individuelle avec jeton de sécurité CSRF.

### 🚀 Gestion de Masse
- **Sélection multiple** : Possibilité de cocher plusieurs produits pour une suppression groupée, optimisant le nettoyage du catalogue.
- **Tout sélectionner** : Fonctionnalité de sélection globale pour des actions rapides sur l'ensemble de la page.

### 🔍 Recherche et Organisation
- **Pagination dynamique** : Affichage optimisé de 8 produits par page via KnpPaginator.
- **Live Search** : Barre de recherche instantanée pour retrouver un produit par son nom ou sa description.
- **Tri multicritères** : Organisation du catalogue par prix (ascendant/descendant) ou par date d'ajout.

---

## 2. Monitoring & Intelligence des Stocks
- **Indicateurs de Performance** : 
    - Calcul automatique de la **valeur totale** du stock.
    - Compteur de produits en **rupture totale**.
    - Système d'alertes visuelles pour les produits sous le **seuil critique**.
- **Analyse Prédictive Gemini** : Module IA analysant les stocks bas pour suggérer les quantités à commander en priorité auprès des fournisseurs.

---

## 3. Interface Boutique (Côté Utilisateur)
L'utilisateur dispose d'un catalogue ergonomique pour faciliter ses achats.

- **Catalogue Responsive** : Affichage optimisé sur mobile et desktop avec un design type "App mobile".
- **Filtres de Sécurité** : La barre de recherche utilisateur est protégée contre les entrées malveillantes.
- **Disponibilité en temps réel** : Affichage automatique des badges "En Stock" ou "Rupture de Stock" basé sur les données de l'inventaire.

---

## 4. Spécifications Techniques
- **Backend** : Symfony / Doctrine ORM.
- **Services IA** : Groq (Description), Gemini (Stock Analysis).
- **Frontend** : Twig / Bootstrap 5 / JavaScript natif.
- **Pagination** : KnpPaginatorBundle.

---
*Fin du rapport de gestion produit.*
