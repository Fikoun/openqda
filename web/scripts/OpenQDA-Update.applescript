-- OpenQDA Update Application
-- Double-click this to update OpenQDA

tell application "Terminal"
    activate
    do script "cd /Users/fikoun/Documents/Playground/OpenQDA/web && ./scripts/build-herd-production.sh; echo ''; echo 'Press any key to close...'; read -n 1"
end tell
