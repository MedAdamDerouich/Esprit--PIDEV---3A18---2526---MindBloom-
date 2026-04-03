# 🚀 GUIDE COMPLET - CRÉATION DES TEMPLATES ÉVÉNEMENTS

## Étape 1: Créer la structure des répertoires

Ouvrez une invite de commande (CMD) et exécutez:

```batch
cd /d C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-
mkdir templates\admin\event
mkdir templates\patient\event
```

OU exécutez le script Python que j'ai créé:
```batch
python create_templates_structure.py
```

## Étape 2: Créer les fichiers templates

### 📄 FICHIER 1: templates\admin\event\index.html.twig
Copier le contenu dans un nouveau fichier `templates\admin\event\index.html.twig`

```twig
{% extends 'base.html.twig' %}
{% block title %}Gestion des Événements — MindBloom{% endblock %}
{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('css/events.css') }}">
{% endblock %}
{% block body %}
<div class="d-flex">
    {{ include('admin/_sidebar.html.twig') }}
    <div class="admin-content" style="flex:1;">
        <div class="event-top-bar">
            <div>
                <h4 class="page-title">📅 Gestion des Événements</h4>
                <span class="page-subtitle">Gérer tous les événements de la plateforme</span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="🔍 Rechercher un événement..." value="{{ search }}" class="search-input">
                </div>
                <a href="{{ path('app_admin_event_new') }}" class="btn-event-add">➕ Ajouter un événement</a>
            </div>
        </div>
        {% for label, messages in app.flashes %}
            {% for message in messages %}
                <div class="alert alert-{{ label == 'error' ? 'danger' : label }} alert-dismissible fade show">
                    {{ message }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            {% endfor %}
        {% endfor %}
        <div class="events-table-container">
            <table class="events-table">
                <thead>
                    <tr>
                        <th style="width:50px;">ID</th>
                        <th style="width:100px;">🖼️ Photo</th>
                        <th>Titre</th>
                        <th style="width:120px;">💰 Prix</th>
                        <th>Lieu</th>
                        <th style="width:90px;">Capacité</th>
                        <th>Organisateur</th>
                        <th style="width:100px;">Statut</th>
                        <th style="width:140px;">Date début</th>
                        <th style="width:200px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="eventsTableBody">
                    {% for event in events %}
                    <tr>
                        <td class="text-center">{{ event.id }}</td>
                        <td>
                            {% if event.photo %}
                                <img src="{{ asset('uploads/events/' ~ event.photo) }}" alt="{{ event.titre }}" class="event-thumbnail">
                            {% else %}
                                <div class="event-thumbnail-placeholder">📷</div>
                            {% endif %}
                        </td>
                        <td>
                            <strong>{{ event.titre }}</strong>
                            {% if event.description %}
                                <br><small class="text-muted">{{ event.description|length > 50 ? event.description|slice(0, 50) ~ '...' : event.description }}</small>
                            {% endif %}
                        </td>
                        <td class="text-center">
                            {% if event.isGratuit %}
                                <span class="price-gratuit">Gratuit</span>
                            {% else %}
                                <span class="price-paid">{{ event.prix|number_format(2, '.', ',') }} DT</span>
                            {% endif %}
                        </td>
                        <td>{{ event.lieu ?? '-' }}</td>
                        <td class="text-center">
                            {{ event.capaciteMax ?? '∞' }}
                            <br><small class="text-muted">({{ event.countParticipants }} inscrits)</small>
                        </td>
                        <td>{{ event.organisateur ?? '-' }}</td>
                        <td>
                            {% if event.statut == 'actif' %}
                                <span class="badge-status badge-actif">Actif</span>
                            {% elseif event.statut == 'annulé' %}
                                <span class="badge-status badge-annule">Annulé</span>
                            {% else %}
                                <span class="badge-status badge-termine">Terminé</span>
                            {% endif %}
                        </td>
                        <td class="text-muted" style="font-size:13px;">{{ event.dateDebut|date('d/m/Y H:i') }}</td>
                        <td>
                            <div class="action-buttons">
                                <a href="{{ path('app_admin_event_show', {id: event.id}) }}" class="btn-action btn-view" title="Voir">👁️</a>
                                <a href="{{ path('app_admin_event_participants', {id: event.id}) }}" class="btn-action btn-participants" title="Participants">👥</a>
                                <a href="{{ path('app_admin_event_edit', {id: event.id}) }}" class="btn-action btn-edit" title="Modifier">✏️</a>
                                <form method="post" action="{{ path('app_admin_event_delete', {id: event.id}) }}" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr ?');">
                                    <input type="hidden" name="_token" value="{{ csrf_token('delete' ~ event.id) }}">
                                    <button type="submit" class="btn-action btn-delete" title="Supprimer">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan="10" class="text-center" style="padding:40px;">
                            <div style="font-size:48px;">📭</div>
                            <p style="color:#7f8c8d;margin-top:10px;">Aucun événement trouvé</p>
                            <a href="{{ path('app_admin_event_new') }}" class="btn-event-add" style="margin-top:10px;">➕ Créer le premier événement</a>
                        </td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
        <div class="event-footer"><span>© 2026 MindBloom — Gestion des Événements</span></div>
    </div>
</div>
{% endblock %}
{% block javascripts %}
{{ parent() }}
<script>
document.getElementById('searchInput').addEventListener('input', function(e) {
    const searchValue = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#eventsTableBody tr');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchValue) ? '' : 'none';
    });
});
</script>
{% endblock %}
```

### 📄 FICHIER 2: templates\admin\event\new.html.twig
Déjà créé dans `new_event_template.twig` - le déplacer vers templates\admin\event\new.html.twig

### 📄 FICHIER 3: templates\admin\event\edit.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}Modifier {{ event.titre }} — MindBloom{% endblock %}
{% block stylesheets %}
    {{ parent() }}
    <link rel="stylesheet" href="{{ asset('css/events.css') }}">
{% endblock %}
{% block body %}
<div class="d-flex">
    {{ include('admin/_sidebar.html.twig') }}
    <div class="admin-content" style="flex:1;">
        <div class="event-form-header">
            <h4>✏️ Modifier l'événement</h4>
            <a href="{{ path('app_admin_event_index') }}" class="btn-back">⬅️ Retour</a>
        </div>
        {% for label, messages in app.flashes %}
            {% for message in messages %}
                <div class="alert alert-{{ label == 'error' ? 'danger' : label }} alert-dismissible fade show">{{ message }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            {% endfor %}
        {% endfor %}
        <div class="event-form-card">
            {{ form_start(form, {'attr': {'class': 'event-form'}}) }}
            <div class="row g-3">
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.titre) }}{{ form_widget(form.titre) }}{{ form_errors(form.titre) }}</div></div>
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.lieu) }}{{ form_widget(form.lieu) }}{{ form_errors(form.lieu) }}</div></div>
                <div class="col-12"><div class="form-group-event">{{ form_label(form.description) }}{{ form_widget(form.description) }}{{ form_errors(form.description) }}</div></div>
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.dateDebut) }}{{ form_widget(form.dateDebut) }}{{ form_errors(form.dateDebut) }}</div></div>
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.dateFin) }}{{ form_widget(form.dateFin) }}{{ form_errors(form.dateFin) }}</div></div>
                <div class="col-md-4"><div class="form-group-event">{{ form_label(form.capaciteMax) }}{{ form_widget(form.capaciteMax) }}{{ form_errors(form.capaciteMax) }}<small class="text-muted">Laisser vide pour illimité</small></div></div>
                <div class="col-md-4"><div class="form-group-event">{{ form_label(form.organisateur) }}{{ form_widget(form.organisateur) }}{{ form_errors(form.organisateur) }}</div></div>
                <div class="col-md-4"><div class="form-group-event">{{ form_label(form.prix) }}{{ form_widget(form.prix) }}{{ form_errors(form.prix) }}<small class="text-muted">0 = gratuit</small></div></div>
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.statut) }}{{ form_widget(form.statut) }}{{ form_errors(form.statut) }}</div></div>
                <div class="col-md-6"><div class="form-group-event">{{ form_label(form.photoFile) }}{{ form_widget(form.photoFile, {'attr': {'onchange': 'previewImage(event)'}}) }}{{ form_errors(form.photoFile) }}{% if event.photo %}<div class="mt-2"><img src="{{ asset('uploads/events/' ~ event.photo) }}" id="imagePreview" class="img-preview"></div>{% else %}<img id="imagePreview" class="img-preview" style="display:none;">{% endif %}</div></div>
            </div>
            <div class="form-actions">
                <a href="{{ path('app_admin_event_index') }}" class="btn-cancel">Annuler</a>
                <button type="submit" class="btn-submit">✅ Enregistrer</button>
            </div>
            {{ form_end(form) }}
        </div>
    </div>
</div>
{% endblock %}
{% block javascripts %}
{{ parent() }}
<script>
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('imagePreview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; preview.style.display = 'block'; }
        reader.readAsDataURL(file);
    }
}
</script>
{% endblock %}
```

### 📄 FICHIER 4: templates\admin\event\show.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}{{ event.titre }} — MindBloom{% endblock %}
{% block stylesheets %}{{ parent() }}<link rel="stylesheet" href="{{ asset('css/events.css') }}">{% endblock %}
{% block body %}
<div class="d-flex">
    {{ include('admin/_sidebar.html.twig') }}
    <div class="admin-content" style="flex:1;">
        <div class="event-top-bar">
            <h4>👁️ Détails de l'événement</h4>
            <a href="{{ path('app_admin_event_index') }}" class="btn-back">⬅️ Retour</a>
        </div>
        <div class="event-form-card">
            {% if event.photo %}<img src="{{ asset('uploads/events/' ~ event.photo) }}" alt="{{ event.titre }}" style="max-width:100%;border-radius:10px;margin:20px 0;">{% endif %}
            <h2>{{ event.titre }}</h2>
            <p><strong>Description:</strong> {{ event.description }}</p>
            <p><strong>Lieu:</strong> {{ event.lieu }}</p>
            <p><strong>Date début:</strong> {{ event.dateDebut|date('d/m/Y H:i') }}</p>
            <p><strong>Date fin:</strong> {{ event.dateFin ? event.dateFin|date('d/m/Y H:i') : 'Non définie' }}</p>
            <p><strong>Capacité:</strong> {{ event.capaciteMax ?? 'Illimitée' }}</p>
            <p><strong>Participants:</strong> {{ participants|length }}</p>
            <p><strong>Prix:</strong> {{ event.isGratuit ? 'Gratuit' : event.prix ~ ' DT' }}</p>
            <p><strong>Statut:</strong> {{ event.statut }}</p>
        </div>
    </div>
</div>
{% endblock %}
```

### 📄 FICHIER 5: templates\admin\event\participants.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}Participants - {{ event.titre }} — MindBloom{% endblock %}
{% block stylesheets %}{{ parent() }}<link rel="stylesheet" href="{{ asset('css/events.css') }}">{% endblock %}
{% block body %}
<div class="d-flex">
    {{ include('admin/_sidebar.html.twig') }}
    <div class="admin-content" style="flex:1;">
        <div class="event-top-bar">
            <h4>👥 Participants - {{ event.titre }}</h4>
            <a href="{{ path('app_admin_event_index') }}" class="btn-back">⬅️ Retour</a>
        </div>
        <div class="events-table-container">
            <table class="events-table">
                <thead><tr><th>Nom</th><th>Email</th><th>Date d'inscription</th><th>Statut</th><th>QR Code</th></tr></thead>
                <tbody>
                    {% for participation in participants %}
                    <tr>
                        <td>{{ participation.user.fullName }}</td>
                        <td>{{ participation.user.email }}</td>
                        <td>{{ participation.dateInscription|date('d/m/Y H:i') }}</td>
                        <td><span class="badge-status badge-actif">{{ participation.statut }}</span></td>
                        <td>{{ participation.qrCode ?? '-' }}</td>
                    </tr>
                    {% else %}
                    <tr><td colspan="5" class="text-center">Aucun participant inscrit</td></tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
```

### 📄 FICHIER 6: templates\patient\event\index.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}Événements — MindBloom{% endblock %}
{% block stylesheets %}{{ parent() }}<link rel="stylesheet" href="{{ asset('css/events.css') }}">{% endblock %}
{% block body %}
<div style="background:#f5f7fa;min-height:100vh;padding:30px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 style="font-weight:700;color:#2c3e50;">🎉 Événements Disponibles</h2>
                <p style="color:#7f8c8d;">{{ events|length }} événement(s) disponible(s)</p>
            </div>
            <a href="{{ path('app_patient_event_my_participations') }}" class="btn-event-add">📋 Mes Participations</a>
        </div>
        <div class="events-grid">
            {% for event in events %}
            <div class="event-card">
                {% if event.photo %}<img src="{{ asset('uploads/events/' ~ event.photo) }}" alt="{{ event.titre }}" class="event-card-image">{% else %}<div class="event-card-image-placeholder">🎉</div>{% endif %}
                <div class="event-card-body">
                    <h3 class="event-card-title">{{ event.titre }}</h3>
                    <div class="event-card-meta">
                        <div class="event-meta-item">📍 {{ event.lieu ?? 'Lieu non précisé' }}</div>
                        <div class="event-meta-item">📅 {{ event.dateDebut|date('d/m/Y à H:i') }}</div>
                        <div class="event-meta-item">👥 {{ event.countParticipants }}/{{ event.capaciteMax ?? '∞' }} participants</div>
                    </div>
                    {% if event.description %}<p class="event-card-description">{{ event.description|length > 100 ? event.description|slice(0, 100) ~ '...' : event.description }}</p>{% endif %}
                    <div class="event-card-footer">
                        <div class="event-price">{{ event.isGratuit ? 'Gratuit' : event.prix ~ ' DT' }}</div>
                        <a href="{{ path('app_patient_event_show', {id: event.id}) }}" class="btn-participate">Voir les détails</a>
                    </div>
                </div>
            </div>
            {% else %}
            <div class="col-12 text-center" style="padding:60px;">
                <div style="font-size:64px;">📭</div>
                <h4 style="margin-top:20px;color:#7f8c8d;">Aucun événement disponible</h4>
            </div>
            {% endfor %}
        </div>
    </div>
</div>
{% endblock %}
```

### 📄 FICHIER 7: templates\patient\event\show.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}{{ event.titre }} — MindBloom{% endblock %}
{% block stylesheets %}{{ parent() }}<link rel="stylesheet" href="{{ asset('css/events.css') }}">{% endblock %}
{% block body %}
<div style="background:#f5f7fa;min-height:100vh;padding:30px;">
    <div class="container">
        <a href="{{ path('app_patient_event_index') }}" class="btn-back" style="margin-bottom:20px;display:inline-block;">⬅️ Retour aux événements</a>
        {% for label, messages in app.flashes %}
            {% for message in messages %}
                <div class="alert alert-{{ label == 'error' ? 'danger' : label }} alert-dismissible fade show">{{ message }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            {% endfor %}
        {% endfor %}
        <div class="row">
            <div class="col-lg-8">
                <div class="event-form-card">
                    {% if event.photo %}<img src="{{ asset('uploads/events/' ~ event.photo) }}" alt="{{ event.titre }}" style="width:100%;border-radius:10px;margin-bottom:20px;">{% endif %}
                    <h1 style="font-weight:700;color:#2c3e50;margin-bottom:20px;">{{ event.titre }}</h1>
                    <div style="display:flex;gap:30px;margin-bottom:20px;flex-wrap:wrap;">
                        <div class="event-meta-item">📅 {{ event.dateDebut|date('d/m/Y à H:i') }}</div>
                        <div class="event-meta-item">📍 {{ event.lieu }}</div>
                        <div class="event-meta-item">👤 {{ event.organisateur }}</div>
                    </div>
                    <p style="line-height:1.8;color:#555;">{{ event.description }}</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="event-form-card" style="position:sticky;top:20px;">
                    <h3 style="font-size:28px;font-weight:700;color:#667eea;margin-bottom:20px;">{{ event.isGratuit ? 'Gratuit' : event.prix ~ ' DT' }}</h3>
                    <ul style="list-style:none;padding:0;margin-bottom:20px;">
                        <li style="padding:10px 0;border-bottom:1px solid #f0f0f0;"><strong>Capacité:</strong> {{ event.capaciteMax ?? 'Illimitée' }}</li>
                        <li style="padding:10px 0;border-bottom:1px solid #f0f0f0;"><strong>Participants:</strong> {{ participantsCount }}</li>
                        <li style="padding:10px 0;border-bottom:1px solid #f0f0f0;"><strong>Places restantes:</strong> {{ event.getPlacesRestantes ?? 'Illimité' }}</li>
                        <li style="padding:10px 0;"><strong>Statut:</strong> {% if event.statut == 'actif' %}<span class="badge-status badge-actif">Actif</span>{% else %}<span class="badge-status badge-termine">{{ event.statut|capitalize }}</span>{% endif %}</li>
                    </ul>
                    {% if isRegistered %}
                        <div class="alert alert-success">✅ Vous êtes déjà inscrit</div>
                    {% elseif not event.isPlacesDisponibles %}
                        <div class="alert alert-warning">⚠️ Complet</div>
                    {% elseif event.statut != 'actif' %}
                        <div class="alert alert-secondary">Événement {{ event.statut }}</div>
                    {% else %}
                        <form method="post" action="{{ path('app_patient_event_participate', {id: event.id}) }}">
                            <input type="hidden" name="_token" value="{{ csrf_token('participate' ~ event.id) }}">
                            <button type="submit" class="btn-participate" style="width:100%;padding:15px;font-size:16px;">🎉 S'inscrire maintenant</button>
                        </form>
                    {% endif %}
                </div>
            </div>
        </div>
    </div>
</div>
{% endblock %}
```

### 📄 FICHIER 8: templates\patient\event\my_participations.html.twig

```twig
{% extends 'base.html.twig' %}
{% block title %}Mes Participations — MindBloom{% endblock %}
{% block stylesheets %}{{ parent() }}<link rel="stylesheet" href="{{ asset('css/events.css') }}">{% endblock %}
{% block body %}
<div style="background:#f5f7fa;min-height:100vh;padding:30px;">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 style="font-weight:700;color:#2c3e50;">📋 Mes Participations</h2>
            <a href="{{ path('app_patient_event_index') }}" class="btn-event-add">⬅️ Retour aux événements</a>
        </div>
        {% for label, messages in app.flashes %}
            {% for message in messages %}
                <div class="alert alert-{{ label == 'error' ? 'danger' : label }} alert-dismissible fade show">{{ message }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
            {% endfor %}
        {% endfor %}
        <div class="events-table-container">
            <table class="events-table">
                <thead><tr><th>Événement</th><th>Date</th><th>Lieu</th><th>Inscription</th><th>QR Code</th><th>Actions</th></tr></thead>
                <tbody>
                    {% for participation in participations %}
                    {% set event = participation.evenement %}
                    <tr>
                        <td><strong>{{ event.titre }}</strong><br><small class="text-muted">{{ event.organisateur }}</small></td>
                        <td>{{ event.dateDebut|date('d/m/Y H:i') }}</td>
                        <td>{{ event.lieu }}</td>
                        <td>{{ participation.dateInscription|date('d/m/Y') }}</td>
                        <td><code>{{ participation.qrCode }}</code></td>
                        <td>
                            <a href="{{ path('app_patient_event_show', {id: event.id}) }}" class="btn-action btn-view">👁️</a>
                            <form method="post" action="{{ path('app_patient_event_cancel', {id: event.id}) }}" style="display:inline;" onsubmit="return confirm('Êtes-vous sûr ?');">
                                <input type="hidden" name="_token" value="{{ csrf_token('cancel' ~ event.id) }}">
                                <button type="submit" class="btn-action btn-delete">❌</button>
                            </form>
                        </td>
                    </tr>
                    {% else %}
                    <tr>
                        <td colspan="6" class="text-center" style="padding:40px;">
                            <div style="font-size:48px;">📭</div>
                            <p style="color:#7f8c8d;margin-top:10px;">Vous n'êtes inscrit à aucun événement</p>
                            <a href="{{ path('app_patient_event_index') }}" class="btn-event-add" style="margin-top:10px;">Découvrir les événements</a>
                        </td>
                    </tr>
                    {% endfor %}
                </tbody>
            </table>
        </div>
    </div>
</div>
{% endblock %}
```

## Étape 3: Exécuter la migration

```batch
php bin/console doctrine:migrations:migrate
```

Répondez "yes" quand demandé.

## Étape 4: Mettre à jour la navigation

Dans `templates\admin\_sidebar.html.twig`, ajouter:
```twig
<a href="{{ path('app_admin_event_index') }}" class="nav-link">
    📅 Événements
</a>
```

## ✅ C'est terminé!

Votre module événements est maintenant complet et fonctionnel!

Testez en accédant à:
- Admin: `/admin/evenements`
- Patient: `/patient/evenements`
