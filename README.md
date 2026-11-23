# Farmaceut.hr

WordPress + WooCommerce + custom tema i pluginovi.

Razvoj lokalno; staging deploy ručno preko GitHub Actions.

Produkcija trenutno nije povezana u workflow.

## Testiranje

### Što se provjerava

- PHP sintaksa vlastite teme `farmaceut` i vlastitih `farmaceut-*` pluginova
- WordPress PHPUnit integracijski testovi (tema; kasnije i pluginovi)

### Kada se pokreću

GitHub Actions workflow `.github/workflows/provjera-koda.yml` na:

- `push` na `main`
- `pull_request` prema `main`
- ručno (`workflow_dispatch`)

Staging deployment ostaje odvojen i isključivo ručan.

### Gdje su testovi

- Zajednička infrastruktura: `tests/`, `phpunit.xml.dist`, `bin/install-wp-tests.sh`
- Testovi teme: `tests/integracijski/`
- Testovi plugina (kad nastanu): `wp-content/plugins/farmaceut-<naziv>/tests/`

### Izolirana testna baza

Testovi koriste **isključivo** bazu `farmaceut_test`.

Ne koriste LocalWP bazu (`local`), staging ni produkciju. Instalacijska skripta i bootstrap prekidaju rad ako naziv baze nije točan.

### Lokalno pokretanje (WSL2 / Linux)

Potrebni su PHP 8.2, Composer, MySQL/MariaDB i bash. LocalWP razvoj se ne mijenja.

```bash
composer install
composer test-instalacija
composer test
```

`composer test-instalacija` priprema privremeni WordPress 7.1.5 i bazu `farmaceut_test`. Na Windowsu je najjednostavnije koristiti WSL2.

Ako nemaš lokalno testno okruženje, dovoljno je push/PR na `main` — CI pokreće iste provjere.

### Novi plugin

1. Dodaj plugin u `wp-content/plugins/farmaceut-<naziv>/`
2. Whitelistaj ga u `.gitignore` i po potrebi u deploy workflowu
3. Stavi testove u `wp-content/plugins/farmaceut-<naziv>/tests/`
4. Zajednički `phpunit.xml.dist` ih automatski uključuje

Ako plugin ovisi o WooCommerceu, bootstrap treba učitati WooCommerce u izoliranom okruženju. Bez učitanog WooCommercea takvi testovi moraju biti skipped, nikad lažno zeleni.
