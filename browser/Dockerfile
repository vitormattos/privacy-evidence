FROM mcr.microsoft.com/playwright:v1.63.0-noble

WORKDIR /worker

COPY package.json ./
RUN npm install --omit=dev

COPY src ./src

USER pwuser

ENTRYPOINT ["node", "src/worker.mjs"]
