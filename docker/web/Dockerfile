# syntax=docker/dockerfile:1

# --- build the Vue app (Node is needed only here) ------------------------------
FROM node:24-alpine AS build

WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

COPY frontend/ ./
RUN npm run build

# --- serve static files and pass /api to PHP-FPM -------------------------------
FROM nginx:1.30-alpine

COPY docker/web/default.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html
