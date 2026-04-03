# GUIDE DE DÉPANNAGE - MODULE ÉVÉNEMENTS

## Problème: Rien ne s'affiche quand on clique sur Événements

### Solution rapide (À FAIRE DANS L'ORDRE):

#### Étape 1: Tester le diagnostic
```bash
cd C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-
php test_events.php
```

OU

```bash
diagnostic_events.bat
```

Cela va vous montrer EXACTEMENT quel est le problème.

---

#### Étape 2: Créer la base de données (si elle n'existe pas)
```bash
php bin/console doctrine:database:create
```

#### Étape 3: Exécuter les migrations (IMPORTANT!)
```bash
php bin/console doctrine:migrations:migrate
```
**Répondez "yes" quand demandé**

#### Étape 4: Vider le cache
```bash
php bin/console cache:clear
```

#### Étape 5: Redémarrer le serveur
```bash
symfony server:stop
symfony server:start
```

---

## Vérifications supplémentaires:

### A. Vérifier que vous êtes bien connecté en tant qu'ADMIN
Les routes `/admin/evenements` nécessitent le rôle ADMIN.

**Pour tester:**
1. Allez sur `/admin` - si ça marche, vous êtes admin
2. Si vous voyez "Access Denied", connectez-vous avec un compte admin

### B. Vérifier les logs d'erreur
```bash
# Voir les dernières erreurs
tail -n 50 var/log/dev.log

# OU sous Windows
type var\log\dev.log
```

### C. Tester les routes directement
```bash
php bin/console debug:router | findstr event
```

Vous devriez voir:
```
app_admin_event_index        GET      /admin/evenements
app_admin_event_new          GET|POST /admin/evenements/nouveau
app_admin_event_show         GET      /admin/evenements/{id}
app_admin_event_edit         GET|POST /admin/evenements/{id}/modifier
app_admin_event_delete       POST     /admin/evenements/{id}/supprimer
app_admin_event_participants GET      /admin/evenements/{id}/participants
app_patient_event_index      GET      /patient/evenements
app_patient_event_show       GET      /patient/evenements/{id}
app_patient_event_participate POST    /patient/evenements/{id}/participer
...
```

---

## Messages d'erreur courants et solutions:

### "Table 'test.evenement' doesn't exist"
→ **Solution:** Exécutez la migration
```bash
php bin/console doctrine:migrations:migrate
```

### "Access Denied" ou erreur 403
→ **Solution:** Vous n'êtes pas connecté avec le bon rôle
- Pour `/admin/evenements` → besoin de ROLE_ADMIN
- Pour `/patient/evenements` → besoin de ROLE_PATIENT

### "No route found for GET /admin/evenements"
→ **Solution:** Videz le cache
```bash
php bin/console cache:clear
```

### Page blanche sans erreur
→ **Solution:** Activez le mode debug
1. Dans `.env`, vérifiez: `APP_ENV=dev`
2. Videz le cache: `php bin/console cache:clear`
3. Rechargez la page

---

## Test final:

Une fois tout fait, testez:

**Admin:**
```
http://localhost:8000/admin/evenements
```
Vous devriez voir un tableau vide avec "Aucun événement trouvé" et un bouton "Ajouter un événement"

**Patient:**
```
http://localhost:8000/patient/evenements
```
Vous devriez voir "Aucun événement disponible"

---

## Si ça ne marche toujours pas:

Envoyez-moi la sortie de:
```bash
php test_events.php
```

Cela me dira exactement quel est le problème!
