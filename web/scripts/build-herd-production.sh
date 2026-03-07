#!/bin/bash

# OpenQDA Build Script for Herd Production
# This script pulls the latest changes from origin and builds the codebase

set -e  # Exit on any error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Navigate to web directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WEB_DIR="$(dirname "$SCRIPT_DIR")"
cd "$WEB_DIR"

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  OpenQDA Herd Production Build Script ${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Step 1: Pull latest changes from origin
echo -e "${YELLOW}[1/7] Pulling latest changes from origin...${NC}"
git pull origin main
echo -e "${GREEN}✓ Git pull complete${NC}"
echo ""

# # Step 2: Install php82 dependencies (production mode)
# echo -e "${YELLOW}[2/7] Installing Composer dependencies...${NC}"
# composer install --no-dev --optimize-autoloader
# echo -e "${GREEN}✓ Composer dependencies installed${NC}"
# echo ""

# # Step 3: Install Node.js dependencies
# echo -e "${YELLOW}[3/7] Installing NPM dependencies...${NC}"
# npm ci
# echo -e "${GREEN}✓ NPM dependencies installed${NC}"
# echo ""

# Step 4: Build frontend assets
echo -e "${YELLOW}[4/7] Building frontend assets...${NC}"
npm run build
echo -e "${GREEN}✓ Frontend assets built${NC}"
echo ""

# Step 5: Clear and cache Laravel configuration
echo -e "${YELLOW}[5/7] Optimizing Laravel...${NC}"
php82 artisan config:cache
php82 artisan route:cache
php82 artisan view:cache
php82 artisan event:cache
echo -e "${GREEN}✓ Laravel optimization complete${NC}"
echo ""

# # Step 6: Run database migrations
# echo -e "${YELLOW}[6/7] Running database migrations...${NC}"
# php82 artisan migrate --force
# echo -e "${GREEN}✓ Database migrations complete${NC}"
# echo ""

# # Step 7: Clear application cache
# echo -e "${YELLOW}[7/7] Clearing application cache...${NC}"
# php82 artisan cache:clear
# echo -e "${GREEN}✓ Application cache cleared${NC}"
# echo ""

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Build complete! 🚀                   ${NC}"
echo -e "${GREEN}========================================${NC}"
