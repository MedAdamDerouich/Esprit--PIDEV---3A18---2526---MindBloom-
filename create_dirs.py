import os

# Créer les répertoires
os.makedirs('templates/admin/event', exist_ok=True)
os.makedirs('templates/patient/event', exist_ok=True)

# Vérifier la création
print('✓ Répertoire templates/admin/event créé')
print('✓ Répertoire templates/patient/event créé')

# Lister la structure
print('\nVérification de la structure créée:')
for root, dirs, files in os.walk('templates'):
    level = root.replace('templates', '').count(os.sep)
    indent = ' ' * 2 * level
    print(f'{indent}{os.path.basename(root)}/')
    sub_indent = ' ' * 2 * (level + 1)
    for d in dirs:
        print(f'{sub_indent}{d}/')
