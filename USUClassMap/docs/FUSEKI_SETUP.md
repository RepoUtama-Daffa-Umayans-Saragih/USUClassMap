# Panduan Instalasi & Konfigurasi Apache Jena Fuseki untuk USUClassMap

Dokumen ini menjelaskan langkah-langkah setup **Apache Jena Fuseki** sebagai server SPARQL triplestore untuk aplikasi USUClassMap.

---

## 1. Apa itu Apache Jena Fuseki?
Apache Jena Fuseki adalah SPARQL server open-source yang mendukung protokol SPARQL 1.1 (Query, Update, dan Graph Store Protocol). Pada arsitektur USUClassMap:
- Laravel bertindak sebagai data producer yang mengekspor data jadwal ke format RDF/XML (`schedules.rdf`).
- Fuseki bertindak sebagai SPARQL query engine.
- Mahasiswa mencari jadwal via endpoint SPARQL yang dieksekusi oleh Fuseki.

---

## 2. Opsi Instalasi

### Opsi A: Menggunakan Docker (Direkomendasikan & Paling Cepat)

Jalankan perintah berikut di terminal:

```bash
docker run -d --name fuseki-usuclassmap \
  -p 3030:3030 \
  -e ADMIN_PASSWORD=admin \
  stain/jena-fuseki
```

Atau buat file `docker-compose.yml` di folder root proyek:

```yaml
version: '3.8'

services:
  fuseki:
    image: stain/jena-fuseki
    container_name: fuseki-usuclassmap
    ports:
      - "3030:3030"
    environment:
      - ADMIN_PASSWORD=admin
    volumes:
      - fuseki_data:/fuseki

volumes:
  fuseki_data:
```

Lalu jalankan:
```bash
docker-compose up -d
```

---

### Opsi B: Menggunakan Standalone Distribution (Java JAR)

1. **Prasyarat**: Pastikan Java (JDK 17+) sudah terinstal di sistem:
   ```bash
   java -version
   ```

2. **Download Fuseki**:
   Download Apache Jena Fuseki dari situs resmi:
   [https://jena.apache.org/download/index.cgi](https://jena.apache.org/download/index.cgi)
   (Pilih file `apache-jena-fuseki-x.x.x.zip` atau `.tar.gz`).

3. **Ekstrak dan Jalankan**:
   ```bash
   # Di Windows (PowerShell/CMD):
   ./fuseki-server.bat

   # Di Linux/macOS:
   ./fuseki-server
   ```
   Server akan aktif di `http://localhost:3030`.

---

## 3. Membuat Dataset `usuclassmap` di Fuseki

1. Buka browser dan akses: `http://localhost:3030`
2. Klik tombol **"manage datasets"** atau **"add one"**.
3. Masukkan konfigurasi dataset:
   - **Dataset name**: `usuclassmap`
   - **Dataset type**: `Persistent (TDB2)` atau `In-memory`
4. Klik **"Create dataset"**.

---

## 4. Sinkronisasi Data RDF dari Laravel ke Fuseki

Setelah Fuseki aktif dan dataset `usuclassmap` dibuat, jalankan perintah artisan di proyek Laravel:

```bash
php artisan rdf:generate --upload
```

Perintah ini akan:
1. Mengambil data dari MySQL.
2. Membangun graph RDF dan menyimpannya di `storage/app/rdf/schedules.rdf`.
3. Mengunggah graph RDF tersebut langsung ke dataset Fuseki melalui HTTP PUT ke `http://localhost:3030/usuclassmap/data?default`.

---

## 5. Menguji Endpoint SPARQL dengan cURL

### Contoh Query Pencarian Jadwal:
```bash
curl -X POST http://localhost:3030/usuclassmap/query \
  -H "Accept: application/sparql-results+json" \
  --data-urlencode "query=
    PREFIX usu: <http://usuclassmap.usu.ac.id/ontology#>
    SELECT ?hari ?jamMulai ?namaMk ?namaDosen ?namaRuangan ?namaGedung
    WHERE {
      ?jadwal a usu:Jadwal ;
              usu:hari ?hari ;
              usu:jamMulai ?jamMulai ;
              usu:memilikiMataKuliah ?mk ;
              usu:diajarOleh ?dosen ;
              usu:berlokasiDi ?ruangan .
      ?mk usu:namaMataKuliah ?namaMk .
      ?dosen usu:namaDosen ?namaDosen .
      ?ruangan usu:namaRuangan ?namaRuangan ;
               usu:beradaDiGedung ?gedung .
      ?gedung usu:namaGedung ?namaGedung .
    }
    ORDER BY ?hari ?jamMulai
  "
```

---

## 6. Konfigurasi `.env` Laravel

Pastikan baris berikut ada di file `.env` Laravel Anda:

```env
FUSEKI_URL=http://localhost:3030
FUSEKI_DATASET=usuclassmap
```

> **Catatan:** Jika server Fuseki sedang offline atau dalam mode maintenance, `SparqlService` pada Laravel secara otomatis beralih ke **MySQL Fallback Mode** tanpa menimbulkan error 500 pada pengguna.
