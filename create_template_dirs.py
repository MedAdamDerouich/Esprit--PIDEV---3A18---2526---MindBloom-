import os

base_path = r'c:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-'

# Create directories
dir1 = os.path.join(base_path, 'templates', 'admin', 'event')
dir2 = os.path.join(base_path, 'templates', 'event')

try:
    os.makedirs(dir1, exist_ok=True)
    os.makedirs(dir2, exist_ok=True)
    print('✓ Directories created successfully!')
    print()
    print('Created:')
    print(f'  1. {dir1}')
    print(f'  2. {dir2}')
    print()
    print('Directory structure:')
    print()
    
    # List the structure
    templates_path = os.path.join(base_path, 'templates')
    for root, dirs, files in os.walk(templates_path):
        level = root.replace(templates_path, '').count(os.sep)
        if level == 0:
            print(f'templates/')
        indent = '  ' * level
        relative_path = os.path.relpath(root, templates_path)
        if relative_path != '.':
            print(f'{indent}{os.path.basename(root)}/')
        for dir_name in sorted(dirs):
            print(f'{indent}  {dir_name}/')
except Exception as e:
    print(f'Error: {e}')
