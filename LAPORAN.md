# FINAL PROJECT PEMROGRAMAN WEB - KELOMPOK 15

**Dibuat oleh:**
* **Ary Pasya Fernanda** (5025241053)
* **Lucky Himawan Prasetya** (5025241147)

---

### 🔗 Link Proyek
> **[STUDYSYNC](https://lucky-himawan.rf.gd)**

---

## BAB 1: Pendahuluan

### Latar Belakang
Tantangan utama yang dihadapi pelajar adalah mengelola tugas akademik yang banyak dan efisiensi dalam proses belajar itu sendiri. Pelajar sering kesulitan melacak tenggat waktu (*due date*) dan memprioritaskan tugas, yang mengakibatkan tugas terlewat (*overdue*).

Di sisi lain, proses merangkum materi seringkali terpisah dari alat manajemen tugas. **StudySync** hadir sebagai solusi terpadu, menggabungkan manajemen tugas yang proaktif dengan alat pencatatan digital (**New Notes**) yang fokus pada rangkuman materi. Kami menciptakan sebuah platform di mana tugas diorganisir berdasarkan prioritas waktu dan rangkuman belajar disimpan terpusat.

### Tujuan Proyek StudySync
1.  **Sistem Tugas Intuitif**: Mengimplementasikan sistem manajemen tugas (CRUD) dengan penandaan status otomatis (*Today, Upcoming, Overdue*).
2.  **Modul Pencatatan**: Mengembangkan modul *New Notes* untuk membuat, menyimpan, dan mengelola rangkuman akademik.
3.  **Visualisasi Progres**: Menyediakan Dashboard untuk melacak statistik kinerja pengguna (Total Tugas dan Tugas Selesai).
4.  **Manajemen Grup Diskusi**: Mempertahankan modul *Study Group* sebagai wadah organisasi topik tanpa fitur *private chat*.
5.  **Autentikasi Fleksibel**: Mengimplementasikan *Login via Google Account* (SSO).

---

## BAB 2: Implementasi Teknis

### 1. Frontend & Backend Development
StudySync dibangun menggunakan arsitektur **3-Tier** dengan fokus pada *native web development*.

| Tingkat Sistem | Teknologi | Implementasi Kunci |
| :--- | :--- | :--- |
| **Presentasi (Frontend)** | HTML, CSS, Bootstrap | Antarmuka responsif dan estetis. |
| **Logika Bisnis (Backend)** | PHP & JavaScript (JS) | Menangani logika server, klasifikasi tugas, dan CRUD Notes. |
| **Transfer Data** | .json (JSON Format) | Komunikasi data efisien antara sisi klien dan server. |

### 2. Database Implementation
Database digunakan untuk menyimpan seluruh data persisten yang diperlukan sistem.
* **Teknologi**: MySQL (diadministrasi via phpMyAdmin).
* **Logika Kunci**: Menyimpan data Tugas, Notes, Grup, dan Akun dengan menjaga integritas relasi data melalui PHP.

### 3. Integrasi API
Fitur autentikasi menggunakan layanan eksternal untuk meningkatkan *User Experience*.
* **API yang Digunakan**: Google Account SSO (Single Sign-On).
* **Fungsi**: Memungkinkan pengguna masuk tanpa registrasi manual, sinkronisasi dilakukan di sisi Backend.

### 4. Pengujian (Testing)
Pengujian fungsional (*Black Box Testing*) dilakukan untuk memastikan setiap fitur inti bekerja:

| Fitur yang Diuji | Skenario Pengujian | Hasil yang Diharapkan | Status |
| :--- | :--- | :--- | :--- |
| Klasifikasi Otomatis | Input tugas DL 1 hari lalu | Tugas muncul di kategori *Overdue* | ✅ Berhasil |
| New Notes (CRUD) | Membuat/edit/hapus catatan | Catatan tersimpan & terhapus di DB | ✅ Berhasil |
| Edit Tugas Lanjutan | Ubah status *Complete* ke *Pending* | Metrik Dashboard berkurang | ✅ Berhasil |
| Login Google SSO | Autentikasi akun Google | Sesi login berhasil dibuat | ✅ Berhasil |

---

## B. Diagram Sistem

![3bd04862-7600-4d73-9775-3c81a926d013](https://github.com/user-attachments/assets/e91f4972-e4db-42c3-b47d-2b2f889d9807)

> **Logika Inti**: Backend (PHP) memproses data Tugas berdasarkan Waktu Sistem untuk menentukan status (*Today/Upcoming/Overdue*). Data *Notes* diproses melalui alur CRUD terpisah. Semua data disimpan secara terpusat dalam database MySQL.

---

## C. User Guide (Panduan Pengguna)

### 1. Mengelola Tugas (Task Management)
1.  **Tambah Tugas**: Klik `+ Add Task`. Tugas otomatis diklasifikasikan berdasarkan DL.
2.  **Status Fleksibel**: Gunakan `Mark Complete` atau `Edit` untuk mengubah status tugas kembali menjadi *Pending*.
3.  **Lacak Progres**: Dashboard menampilkan metrik kinerja Anda secara langsung.

### 2. Membuat Rangkuman (New Notes)
1.  **Akses Modul**: Klik menu `New Notes`.
2.  **Pencatatan**: Buat rangkuman materi Anda, lalu simpan.
3.  **Akses Data**: Catatan dapat diedit atau dihapus kapan saja melalui modul *Notes*.

---

## D. Pembagian Jobdesk

| Nama Anggota | NRP | Tanggung Jawab Utama | Kontribusi Teknis |
| :--- | :--- | :--- | :--- |
| **Lucky Himawan P.** | 5025241147 | Backend & Database | PHP Logic, MySQL, JS Async, Google SSO. |
| **Ary Pasya Fernanda** | 5025241053 | Frontend & Testing | UI Design (CSS/Bootstrap), Black Box Testing. |

---
*Postingan ini disusun untuk memenuhi tugas laporan proyek StudySync.*
