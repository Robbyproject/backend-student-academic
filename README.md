<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## KRS API

All KRS routes use Sanctum bearer tokens. Admin endpoints require a logged-in user with the `admin` role; student endpoints use the authenticated student's linked `mahasiswa` record.

### Development student test account

Run `StudentSeeder` to create the `robby@gmail.com` student account and its student profile. The seeder selects the existing Informatika (`IF`) department when available, otherwise the first department; if there is no department yet, it creates Informatika (`IF`). It assigns test NIM `TEST-ROBBY-2026` and cohort 2026. The development password for a newly created account is `password123`. Run it directly:

```powershell
php artisan db:seed --class=StudentSeeder
```

The default `php artisan db:seed` also runs `StudentSeeder`. It is safe to rerun and does not reset the password of an account that already exists.

Admin setup order:

1. Create an academic period with `POST /api/admin/krs/periods`, for example:
   ```json
   {
     "academic_year_start": 2026,
     "term": "ganjil",
     "is_active": true,
     "registration_open": false
   }
   ```
   The academic year is generated as `2026/2027`. Only one period can be active. Open or close registration with `PATCH /api/admin/krs/periods/{id}` and `{"registration_open": true}`.
2. Add courses using `POST /api/admin/matkul`:
   ```json
   {
     "kode_matkul": "IF201",
     "nama_matkul": "Struktur Data",
     "sks": 3,
     "jurusan_id": 1
   }
   ```
   Course codes must be unique, SKS must be from 1 to 6, and the department must exist. Newly created courses appear in `GET /api/admin/academic-data` and can be added to a curriculum package.
3. Create a department's semester package with `POST /api/admin/krs/packages`:
   ```json
   {
     "jurusan_id": 1,
     "semester": 3,
     "name": "Paket Semester 3",
     "matkul_ids": [4, 5]
   }
   ```
   Every course in a package must belong to the selected department. Use `GET /api/admin/krs/packages` to list packages and `PUT /api/admin/krs/packages/{id}` to replace one.
4. Create class sections for the active academic year and term. `POST /api/admin/academic-classes` requires `tahun_ajaran` in `YYYY/YYYY` format and `term` equal to `ganjil` or `genap`. Set or correct existing class offerings with `PATCH /api/admin/academic-classes/{id}/offering` and the same fields; legacy classes have no term until assigned.

Students can call `GET /api/krs/options` to receive their required package and available class sections, `GET /api/krs` to view their current KRS, and `PUT /api/krs` to submit or replace it:

```json
{
  "kelas_ids": [12, 18]
}
```

The submitted list must contain exactly one available class for every course in the package. The server derives semester as `2 * (academic_year_start - angkatan) + 1` for `ganjil`, or `+ 2` for `genap`. It checks package membership and schedule overlaps, then approves a valid KRS immediately. Replacement is allowed only while registration is open. Selected sections are also recorded in `peserta_kelas`.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
