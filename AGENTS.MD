# AGENTS.md – Farmaceut.hr

## 1. Projekt

Farmaceut.hr je WordPress projekt s prilagođenom temom, WooCommerce webshopom i vlastitim pluginovima. Razvoj i testiranje su lokalno i na stagingu; produkcijski deployment slijedi nakon završetka razvoja.

Cilj: kvalitetan, modularan i održiv kod bez nepotrebne kompleksnosti. Projekt razvija jedan developer uz AI agente.

## 2. Stack

- WordPress, WooCommerce
- LocalWP + Cursor (lokalno)
- GitHub + GitHub Actions (ručni deploy)
- SiteGround (staging: `https://staging.farmaceut.hr`)

Ne uvoditi nove frameworke, biblioteke ili build alate bez opravdanog razloga.

## 3. Jezik

Cijeli projekt na hrvatskom: UI, kod, komentari, dokumentacija, commitovi, PR-ovi.

- Identifikatori u kodu (funkcije, varijable, datoteke, slugovi) na hrvatskom **bez dijakritika**.
- Tekstovi za ljude s pravilnim hrvatskim znakovima (č, ć, ž, š, đ).
- WordPress/WooCommerce/API nazive i obavezne WP datoteke ne prevoditi.

## 4. Arhitektura

**Tema** `wp-content/themes/farmaceut/` — izgled, predlošci, navigacija, UI interakcije.

**Pluginovi** `wp-content/plugins/` — svaka veća funkcionalnost je zaseban `farmaceut-*` plugin. Nema centralnog/core plugina. Manje povezane stvari grupirati u postojeći odgovarajući plugin; ne stvarati plugin za svaku sitnicu.

Poslovna logika i trajni podaci ne ovise o aktivnoj temi. Prezentacija pripada temi. Pluginovi trebaju biti što neovisniji.

## 5. Standard novog plugina

1. Direktorij: `wp-content/plugins/farmaceut-<naziv>/`
2. Bootstrap: `farmaceut-<naziv>.php` s WordPress plugin headerom; Text Domain = slug foldera.
3. Prefiksi: funkcije `farmaceut_<naziv>_`, handleovi/slugovi `farmaceut-<naziv>-`.
4. Struktura po potrebi: root bootstrap, `includes/` (logika), `assets/` (CSS/JS), admin samo ako treba.
5. Dijeljeni kod samo kad je stvarno potreban — ne uvoditi “core zbirku” plugina.
6. Pri dodavanju plugina: whitelist u `.gitignore` (`!/wp-content/plugins/farmaceut-<naziv>/`) **i** proširenje deployment workflowa. Novi plugin se ne prenosi na staging automatski.

## 6. Konvencije i implementacija

- Datoteke/direktoriji: `kebab-case`. PHP: `snake_case`. JS: `camelCase`.
- Vlastite globalne PHP simbole prefiksirati. Ista stvar = isti naziv svugdje. Bez `helper` / `misc` / `temp` bez razloga.
- Prije izmjena proučiti postojeći kod. Koristiti službene WP/WC API-je. Ne mijenjati WordPress core ni vanjske pluginove.
- Validacija, sanitizacija, escaping; ovlasti i nonceovi gdje treba. Izbjegavati nepotrebne DB upite.
- Ne nepovezano refaktoriranje tijekom zadatka. Ne komentari koji ponavljaju očito.
- WooCommerce: paziti na cijene, popuste, poreze, košaricu, naplatu, narudžbe. Ne mijenjati podatke proizvoda/narudžbi/baze bez jasnog zahtjeva.

## 7. Git i deployment

- Repo: `josipmestrovic/farmaceut-hr`, grana `main`.
- Commit/push/deploy samo na izričit zahtjev. Ne destruktivne Git naredbe bez odobrenja. Ne dirati produkciju. Ne tajne u repo.
- Staging deploy: ručno preko GitHub Actions (dry-run ili deploy odabranog commita).
- Trenutno workflow deploya **samo temu** `farmaceut`. Novi pluginovi zahtijevaju izmjenu `.gitignore` i workflowa.
- Prije promjene deploy konfiguracije ili destruktivnih DB promjena: objasniti posljedice / upozoriti na backup.

## 8. Commitovi

Conventional Commits; opis nakon prefiksa na hrvatskom.

`feat`, `fix`, `refactor`, `perf`, `docs`, `test`, `chore`, `ci`, `style`

Jedan commit = jedna logička cjelina.

## 9. Rad s AI agentom

- Ne donositi važne produktne odluke bez konteksta.
- Ne postavljati nepotrebna pitanja ako je odluka očita.
- Ako postoje dva razumna pristupa, predložiti kvalitetniji i ukratko obrazložiti.
- Ne tvrditi da je nešto testirano ako nije.
- Ne mijenjati nepovezane dijelove projekta.
