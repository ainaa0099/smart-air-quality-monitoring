# Gunakan base image Node.js versi 18 yang ringan
FROM node:18-alpine

# Tentukan direktori kerja di dalam container
WORKDIR /usr/src/app

# Salin package.json dan package-lock.json untuk menginstal dependensi
COPY package*.json ./
RUN npm install

# Salin sisa kode aplikasi
COPY . .

# Buka port yang digunakan oleh aplikasi
EXPOSE 3002

# Perintah untuk menjalankan aplikasi saat container dimulai
CMD [ "node", "src/app.js" ]