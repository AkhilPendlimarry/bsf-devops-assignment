FROM node:22-alpine AS build
WORKDIR /src
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM node:22-alpine
WORKDIR /srv
ENV NODE_ENV=production
ENV HOSTNAME=0.0.0.0
COPY --from=build /src/.next/standalone ./
COPY --from=build /src/.next/static ./.next/static
EXPOSE 3000
CMD ["node", "server.js"]
