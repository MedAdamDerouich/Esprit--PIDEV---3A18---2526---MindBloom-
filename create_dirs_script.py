#!/usr/bin/env python3
"""Create required template directories."""

import os
from pathlib import Path

def main():
    # Base path
    base_path = r'c:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-'
    
    # Directories to create
    dirs_to_create = [
        os.path.join(base_path, 'templates', 'admin', 'event'),
        os.path.join(base_path, 'templates', 'event')
    ]
    
    print("Creating directories...")
    print("=" * 60)
    
    # Create directories
    for directory in dirs_to_create:
        try:
            Path(directory).mkdir(parents=True, exist_ok=True)
            print(f"✓ Created/Verified: {directory}")
        except Exception as e:
            print(f"✗ Error creating {directory}: {e}")
            return False
    
    # Verify they exist
    print("\n" + "=" * 60)
    print("Verification:")
    print("=" * 60)
    all_exist = True
    for directory in dirs_to_create:
        exists = os.path.isdir(directory)
        status = "✓ EXISTS" if exists else "✗ NOT FOUND"
        print(f"{status}: {directory}")
        if not exists:
            all_exist = False
    
    # Show directory structure
    print("\n" + "=" * 60)
    print("Final Directory Structure:")
    print("=" * 60)
    templates_path = os.path.join(base_path, 'templates')
    if os.path.isdir(templates_path):
        for root, dirs_list, files in os.walk(templates_path):
            level = root.replace(templates_path, '').count(os.sep)
            indent = '  ' * level
            print(f'{indent}{os.path.basename(root)}/')
            subindent = '  ' * (level + 1)
            for directory_name in sorted(dirs_list):
                print(f'{subindent}{directory_name}/')
    
    print("\n" + "=" * 60)
    if all_exist:
        print("✓ All directories created successfully!")
        return True
    else:
        print("✗ Some directories could not be created.")
        return False

if __name__ == "__main__":
    success = main()
    exit(0 if success else 1)
