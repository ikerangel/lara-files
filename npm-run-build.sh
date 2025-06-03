#!/bin/bash

# Run npm commands
docker run -it --rm \
  -v "$(pwd):/app" \
  -v "/app/node_modules" \
  -w /app \
  node:18 \
  sh -c "npm install && npm run build"

# Fix ownership and permissions for the ENTIRE build directory
sudo chown -R $USER:www-data ./public/build
sudo chmod -R 775 ./public/build
