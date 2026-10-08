#!/bin/bash
set -euo pipefail

echo "=== Déploiement EGAM - $(date) ==="

# Couleurs pour les logs
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Configuration
PROJECT_DIR="/home/ubuntu/sites/egam/drupal-files"
BRANCH="${1:-main}"  # Branche à déployer (défaut: main)
EMAIL="gustavI0@proton.me"

cd "$PROJECT_DIR"

# Avant la récupération du code : un nouvel index.php peut dépendre de fichiers générés par composer install.
echo -e "${YELLOW}🔧 Mise en mode maintenance...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush state:set system.maintenance_mode 1

echo -e "${YELLOW}📥 Récupération des dernières modifications (branche: $BRANCH)...${NC}"
git fetch origin
git checkout "$BRANCH"
git pull origin "$BRANCH"

echo -e "${YELLOW}📦 Installation des dépendances Composer...${NC}"
docker exec -u www-data egam_drupal composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}🗄️  Déploiement...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush deploy

echo -e "${YELLOW}🔓 Désactivation du mode maintenance...${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush state:set system.maintenance_mode 0

echo -e "${GREEN}✅ Déploiement terminé avec succès!${NC}"

# Vérifier l'état du site
echo -e "${YELLOW}📊 État du site:${NC}"
docker exec -u www-data egam_drupal vendor/bin/drush status

# Notification email
echo "Déploiement EGAM réussi
Branche: $BRANCH
Date: $(date)
" | mail -s "✅ Déploiement EGAM réussi" "$EMAIL"
