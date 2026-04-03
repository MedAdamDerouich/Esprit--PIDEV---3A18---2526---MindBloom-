import os

# Create directories
os.makedirs('templates/admin/event', exist_ok=True)
os.makedirs('templates/event', exist_ok=True)

# Define template files
template_files = [
    'templates/admin/event/index.html.twig',
    'templates/admin/event/new.html.twig',
    'templates/admin/event/edit.html.twig',
    'templates/admin/event/show.html.twig',
    'templates/admin/event/participants.html.twig',
    'templates/event/index.html.twig',
    'templates/event/show.html.twig',
    'templates/event/my_participations.html.twig'
]

# Create files with placeholder
placeholder = '{# Placeholder #}\n'
for file_path in template_files:
    with open(file_path, 'w', encoding='utf-8') as f:
        f.write(placeholder)
    print(f'✓ Created {file_path}')

print('\n✓ All directories and template files created successfully!')
