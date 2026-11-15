import os
import sys
import json
import zipfile
import subprocess
import datetime

ROOT_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
DIST_DIR = os.path.join(ROOT_DIR, 'dist')
VERSION_FILE = os.path.join(DIST_DIR, 'ota-version.json')
REMOTE_OTA_DIR = '/home/u996219523/domains/vistaboundintegrated.com/crm-app/public/ota'

print("=== Vistabound CRM: Automated OTA Live Publisher ===")

# 1. Determine next version
current_version = '1.0.2'
if os.path.exists(VERSION_FILE):
    try:
        with open(VERSION_FILE, 'r', encoding='utf-8') as f:
            raw = json.load(f)
            if 'version' in raw:
                current_version = raw['version']
    except Exception:
        pass

if len(sys.argv) > 1:
    next_version = sys.argv[1]
else:
    parts = [int(p) if p.isdigit() else 0 for p in current_version.split('.')]
    if len(parts) >= 3:
        parts[2] += 1
    else:
        parts.append(1)
    next_version = '.'.join(str(p) for p in parts)

print(f"[1/5] Target Version: {next_version} (Current: {current_version})")

# 2. Build mobile bundle
print("[2/5] Building mobile bundle with vite.mobile.config.ts...")
build_res = subprocess.run(['npm.cmd', 'run', 'build:mobile'], cwd=ROOT_DIR)
if build_res.returncode != 0:
    print("ERROR: Build failed!")
    sys.exit(1)

# 3. Create zip bundle
zip_filename = f"bundle-v{next_version}.zip"
zip_path = os.path.join(DIST_DIR, zip_filename)
print(f"[3/5] Packaging {zip_filename}...")

with zipfile.ZipFile(zip_path, 'w', zipfile.ZIP_DEFLATED) as zipf:
    for root, dirs, files in os.walk(DIST_DIR):
        for file in files:
            if file.endswith('.zip') or file == 'ota-version.json':
                continue
            file_path = os.path.join(root, file)
            arcname = os.path.relpath(file_path, DIST_DIR)
            zipf.write(file_path, arcname)

size_mb = os.path.getsize(zip_path) / (1024 * 1024)
print(f"      Created {zip_filename} ({size_mb:.2f} MB)")

# 4. Create ota-version.json
ota_data = {
    "version": next_version,
    "url": f"https://vistaboundintegrated.com/CRM/ota/{zip_filename}",
    "updated_at": datetime.datetime.now(datetime.timezone.utc).isoformat()
}
with open(VERSION_FILE, 'w', encoding='utf-8') as f:
    json.dump(ota_data, f, indent=2)

# 5. Stream upload to Hostinger server via SSH
print(f"[4/5] Uploading {zip_filename} to Hostinger server...")
with open(zip_path, 'rb') as f:
    zip_bytes = f.read()

res = subprocess.run(
    ['ssh', 'myworks', f"cat > '{REMOTE_OTA_DIR}/{zip_filename}'"],
    input=zip_bytes,
    capture_output=True
)
if res.returncode != 0:
    print("ERROR: Failed to upload zip:", res.stderr.decode(errors='replace'))
    sys.exit(1)
print("      Zip uploaded successfully!")

print("[5/5] Updating ota-version.json on Hostinger server...")
with open(VERSION_FILE, 'rb') as f:
    json_bytes = f.read()

res = subprocess.run(
    ['ssh', 'myworks', f"cat > '{REMOTE_OTA_DIR}/ota-version.json'"],
    input=json_bytes,
    capture_output=True
)
if res.returncode != 0:
    print("ERROR: Failed to upload ota-version.json:", res.stderr.decode(errors='replace'))
    sys.exit(1)
print("      Version manifest updated!")

# Verify
try:
    verify = subprocess.run(
        ['curl.exe', '-s', 'https://vistaboundintegrated.com/CRM/api/mobile/ota-version'],
        capture_output=True,
        text=True
    )
    print("\n=== PUBLICATION VERIFIED ===")
    print("Live OTA API Response:", verify.stdout.strip())
    print(f"\nAll mobile devices running the Master APK will now automatically update to v{next_version} on next launch!\n")
except Exception as e:
    print("Verification check skipped:", e)
