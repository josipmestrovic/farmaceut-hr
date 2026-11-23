# Farmaceut Cjenik

Plugin za webshop koji uz današnju cijenu pokazuje **baznu cijenu**, po potrebi **cijeni za jedinicu mjere**, objavljuje **javni cjenik** (CSV) i — kad to uključite — **najnižu cijenu u 30 dana** uz akciju.

Za dodatke prehrani (hrana) referentni dan bazne cijene je **2. 5. 2025.**

Ovaj tekst nije pravni savjet. Povijesne iznose i klasifikaciju proizvoda potvrđuje trgovac (po potrebi s pravnim / računovodstvenim savjetnikom).

---

## Koje cijene postoje (ne miješati ih)

| Naziv | Što znači | Kad se vidi |
| --- | --- | --- |
| **Maloprodajna** | Što kupac plaća danas | Uvijek |
| **Bazna** | Povijesna cijena na referentni dan ili na dan prve ponude | Uvijek uz maloprodajnu |
| **Jedinična** | Npr. cijena po 100 g ili 100 ml | Kad je upisana neto količina |
| **Najniža u 30 dana** (Omnibus) | Referenca uz istaknutu akciju / sniženje | Samo ako je Omnibus uključen i proizvod je na akciji |

Važno:

- Današnja akcija **ne mijenja** baznu cijenu.
- „Najniža u 30 dana“ **nije** ista stvar kao bazna cijena.
- Bazna se **ne precrtava** i ne koristi za izračun postotka popusta.

---

## Pravni okvir

Plugin prati hrvatske propise o isticanju cijena i objavi cjenika. Ključni službeni tekstovi:

1. **[Pravilnik o isticanju bazne cijene proizvoda](https://narodne-novine.nn.hr/clanci/sluzbeni/2026_11_132_1583.html)** (NN 132/2026, br. 1583)  
   Referentni dani (za hranu, piće, kozmetiku… **2. 5. 2025.**; za ostalu robu **10. 9. 2026.**), pravila kad je na taj dan već bila akcija, i način isticanja u mrežnoj trgovini.

2. **[Pravilnik o načinu objave cjenika proizvoda](https://narodne-novine.nn.hr/clanci/sluzbeni/full/2026_11_132_1584.html)** (NN 132/2026, br. 1584)  
   Cjenik u CSV ili XML obliku na web stranici, izmjena najkasnije do **11:00** na dan izmjene, arhiva najmanje **15 dana**, nova datoteka na **istoj adresi (URL)**.

3. **[Pravilnik o načinu isticanja maloprodajne cijene i cijene za jedinicu mjere](https://narodne-novine.nn.hr/clanci/sluzbeni/full/2026_09_105_1270.html)** (NN 105/2026, br. 1270)  
   Obveza cijene za jedinicu mjere (npr. hrana), uz propisane iznimke.

4. **Najniža cijena u 30 dana prije sniženja** — Zakon o zaštiti potrošača, članak 19.  
   - [Izmjene Zakona (NN 59/2026)](https://www.zakon.hr/c/nn/956990/nn-59-2026-%289.6.2026.%29%2C-zakon-o-izmjenama-i-dopunama-zakona-o-zastiti-potrosaca)  
   - [Članak 19. (pregled)](https://zakoni.lexium.hr/zakon-o-zastiti-potrosaca/clanak/art19)

Dodaci prehrani tipično spadaju u skupinu **hrane** → bazna cijena prema danu **2. 5. 2025.** (ili danu prve ponude ako je proizvod uveden kasnije).

---

## Gdje kupac vidi cijene

- **Stranica proizvoda** — ispod maloprodajne cijene (npr. „Cijena na 2. 5. 2025.: …“ i jedinična cijena).
- **Popis proizvoda (grid)** — shop, kategorije i ostala mjesta gdje WooCommerce ispisuje cijenu na kartici.
- **Proizvodi s varijacijama** (npr. različita pakiranja) — bez odabira: raspon baznih cijena; nakon odabira: točna bazna te varijacije.
- **Javni cjenik** — stranica `/cjenici` s poveznicom na `/cjenici/roba.csv` i arhivom starijih verzija.

Uz akciju (samo kad je Omnibus uključen u postavkama): oznaka najniže cijene u 30 dana.

Plugin **ne mijenja** iznose u košarici ni na naplati — tamo ostaje uobičajena WooCommerce cijena narudžbe.

---

## Što možete raditi u administraciji

### WooCommerce → Cjenik

- Pregled koliko artikala ima potvrđenu / nedostajuću / neispravnu baznu cijenu.
- Postavke za naziv datoteke cjenika (**OIB**, oznaka prodajnog mjesta ili „isti cjenik za sva mjesta“).
- **Uvoz** baznih cijena iz CSV datoteke (najprije pregled / dry-run, zatim pravi uvoz). Primjer: `assets/primjer-uvoz-baznih.csv`.
- **Regeneriraj cjenik** — ručno osvježavanje javnog CSV-a.
- Uključivanje **Omnibusa** (prikaz najniže cijene u 30 dana uz akciju).

### Na pojedinom proizvodu ili varijaciji

- Bazna cijena (€), datum, osnova (npr. 2. 5. 2025. ili prva ponuda), napomena / izvor, potvrda.
- Neto količina i jedinica (za jediničnu cijenu).
- Razlog ispravka — obavezan kad mijenjate već **potvrđenu** baznu cijenu.

### Cjenik u pozadini

Kad mijenjate cijene ili podatke, cjenik se sam osvježava. Ako treba odmah: gumb **Regeneriraj cjenik**. Starija verzija ostaje u arhivi najmanje 15 dana.

---

## Prije puštanja u rad (checklist)

1. U postavkama upišite **OIB** i identifikatore za naziv datoteke.
2. Unesite ili uvezite **bazne cijene** za sve artikle u prodaji (s dokazima / potvrdom).
3. U Cjeniku completeness: **0 nedostaje**.
4. Otvorite `/cjenici` i preuzmite `/cjenici/roba.csv` (bez prijave).
5. **Omnibus** uključite tek kad ste spremni isticati akcije (− %, precrtana „redovna“ cijena). Dok nije uključen, ne oglašavajte takve popuste.

---

## Što plugin ne radi sam

- Ne izmišlja povijesnu baznu cijenu iz današnje WooCommerce cijene.
- Ne zamjenjuje pravni ili računovodstveni savjet.
- Ne popunjava podatke koje trgovac ne može potkrijepiti.
