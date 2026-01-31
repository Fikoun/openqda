#!/bin/bash

# Creates a macOS Desktop shortcut for OpenQDA Update
# Run this script on any Mac to create the update app on Desktop

set -e

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Get the absolute path to the web directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_DIR="$(dirname "$SCRIPT_DIR")"

echo -e "${YELLOW}Creating OpenQDA Update desktop shortcut...${NC}"

# Create temporary AppleScript file
TEMP_SCRIPT=$(mktemp /tmp/openqda-update.XXXXXX.applescript)

cat > "$TEMP_SCRIPT" << EOF
-- OpenQDA Update Application
-- Double-click this to update OpenQDA

tell application "Terminal"
    activate
    do script "cd ${WEB_DIR} && ./scripts/build-herd-production.sh; echo ''; echo 'Press any key to close...'; read -n 1"
end tell
EOF

# Compile AppleScript to application on Desktop
osacompile -o ~/Desktop/OpenQDA-Update.app "$TEMP_SCRIPT"

# Clean up temp file
rm "$TEMP_SCRIPT"

echo -e "${GREEN}✓ OpenQDA-Update.app created on your Desktop!${NC}"
echo ""
echo "Double-click the app to pull updates and rebuild OpenQDA."
echo ""
echo -e "${YELLOW}Note: On first run, macOS may ask for permission.${NC}"
echo "Go to System Settings → Privacy & Security → Open Anyway"
