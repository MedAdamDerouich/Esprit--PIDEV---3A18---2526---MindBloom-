import os
import shutil

# Base path
base_path = r"C:\Users\pc\Desktop\Esprit--PIDEV---3A18---2526---MindBloom-"

# Create directories
os.makedirs(os.path.join(base_path, "templates", "admin", "event"), exist_ok=True)
os.makedirs(os.path.join(base_path, "templates", "patient", "event"), exist_ok=True)

print("✅ Directories created successfully!")

# Move the template file
src = os.path.join(base_path, "new_event_template.twig")
dst = os.path.join(base_path, "templates", "admin", "event", "new.html.twig")

if os.path.exists(src):
    shutil.move(src, dst)
    print(f"✅ Moved new.html.twig to {dst}")

print("\n📁 Directory structure:")
print(f"  templates/admin/event/")
print(f"  templates/patient/event/")
print("\n✅ Setup complete! Now you can create the remaining templates.")
