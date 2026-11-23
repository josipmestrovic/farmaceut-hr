=== Farmaceut Cjenik ===
Dodaci prehrani: referentni datum bazne cijene 2. 5. 2025. (osnova_2025).

== Merchant prije produkcije ==
1. WooCommerce → Cjenik → upiši OIB i identifikatore datoteke.
2. Pripremi CSV baznih cijena (vidi assets/primjer-uvoz-baznih.csv) s dokazima.
3. Uvezi (dry-run pa commit) i potvrdi completeness = 0 nedostaje.
4. Tek nakon PR-a za javni cjenik: Regeneriraj i provjeri /cjenici.
5. Omnibus uključi tek kad želiš isticati akcije (−% / precrtano).

== Faze ==
1. Podaci + admin + uvoz
2. Prikaz na kartici/single
3. Javni CSV /cjenici
4. Omnibus 30 dana
