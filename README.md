# Poznámky

Jednoduchá aplikace na správu poznámek. Poznámka má název, obsah a prioritu (nízká, střední, vysoká). Poznámky jde vytvářet, upravovat, mazat a filtrovat podle priority.

Backend je REST API v PHP (Symfony), data jsou v MariaDB, frontend je ve Vue 3. Celé se to spouští přes Docker Compose.

| Část | Technologie | Verze |
| --- | --- | --- |
| Backend | PHP, Symfony (LTS), Doctrine ORM a Migrations | PHP 8.5, Symfony 7.4 |
| Databáze | MariaDB (LTS) | 12.3 |
| Frontend | Vue 3, TypeScript, Vite, Vue Router | Node 24 LTS jen pro build |
| Infrastruktura | Docker Compose, nginx, PHP-FPM | nginx 1.30 |
| Kvalita | PHPUnit, PHPStan (level max), PHP-CS-Fixer, ESLint, vue-tsc, GitHub Actions | |

## Jak spustit

Potřebujete jen Docker s Compose v2.

```bash
docker compose up
```

První build trvá pár minut. Potom:

- **UI:** http://localhost:8080
- **API:** http://localhost:8080/api/notes

Při startu se spustí migrace a do prázdné databáze se nahraje šest ukázkových poznámek. Pokud je port 8080 obsazený, použijte jiný: `APP_PORT=8081 docker compose up`.

Užitečné příkazy:

```bash
docker compose up --build                     # po změně kódu (obrazy se sestaví znovu)
docker compose down -v                        # zastavit a smazat databázi (příště se nahrají demo data)

docker compose exec php composer test         # PHPUnit, funkční testy API proti testovací DB
docker compose exec php composer check        # CS-Fixer, PHPStan a testy, to samé co CI
docker compose exec php bin/console app:demo-data   # demo data ručně (jen do prázdné DB)
```

Frontend (potřebuje Node 24):

```bash
cd frontend
npm ci
npm run lint          # ESLint
npm run type-check    # vue-tsc
npm run build         # typová kontrola a produkční build
npm run dev           # vývojový server na http://localhost:5173, /api posílá na běžící Docker stack
```

## Přehled API

Všechny odpovědi jsou JSON. Tělo požadavku musí mít `Content-Type: application/json`.

| Metoda | Adresa | Popis | Úspěch |
| --- | --- | --- | --- |
| `GET` | `/api/notes` | Seznam poznámek, řazený podle priority a poslední změny | 200 |
| `GET` | `/api/notes?priority=high` | Seznam filtrovaný podle priority (`low`, `medium`, `high`) | 200 |
| `GET` | `/api/notes/{id}` | Detail poznámky | 200 |
| `POST` | `/api/notes` | Vytvoření poznámky | 201 + hlavička `Location` |
| `PUT` | `/api/notes/{id}` | Úprava poznámky (nahradí celý obsah) | 200 |
| `DELETE` | `/api/notes/{id}` | Smazání poznámky (nad rámec zadání) | 204 |

Pole v těle požadavku:

| Pole | Povinné | Pravidla |
| --- | --- | --- |
| `title` | ano | text, po oříznutí mezer neprázdný, nejvýše 200 znaků |
| `content` | ne | text, nejvýše 10 000 znaků, výchozí hodnota `""` |
| `priority` | ano | `low`, `medium` nebo `high` |

Příklad vytvoření:

```bash
curl -s -X POST http://localhost:8080/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"title": "Objednat se k zubaři", "content": "Ideálně dopoledne.", "priority": "high"}' | jq
```

```json
{
  "id": 7,
  "title": "Objednat se k zubaři",
  "content": "Ideálně dopoledne.",
  "priority": "high",
  "createdAt": "2026-10-19T10:15:54+00:00",
  "updatedAt": "2026-10-19T10:15:54+00:00"
}
```

Seznam vrací poznámky v obálce `{"data": [...]}`, aby šlo později přidat stránkování bez změny tvaru odpovědi.

### Chyby

Každá chyba pod `/api` má stejný tvar. Pole `fields` je jen u chyb validace a jde rovnou navázat na formulář.

```bash
curl -s -X POST http://localhost:8080/api/notes \
  -H 'Content-Type: application/json' \
  -d '{"title": "   ", "priority": "urgent"}' | jq
```

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Zkontrolujte zadané údaje.",
    "fields": {
      "title": ["Vyplňte název."],
      "priority": ["Neplatná priorita. Povolené hodnoty: \"low\", \"medium\", \"high\"."]
    }
  }
}
```

| Stav | `code` | Kdy |
| --- | --- | --- |
| 400 | `invalid_json` | Tělo není platný JSON |
| 400 | `validation_failed` | Neplatná hodnota filtru `?priority=` |
| 404 | `not_found` | Poznámka neexistuje (i pro nečíselné id) |
| 405 | `method_not_allowed` | Nepodporovaná metoda, např. `PATCH`; hlavička `Allow` říká, které jsou povolené |
| 415 | `unsupported_media_type` | Tělo není JSON (chybí `Content-Type: application/json`) |
| 422 | `validation_failed` | Neplatná pole v těle, detail v `fields` |
| 500 | `internal_error` | Neočekávaná chyba; podrobnosti jsou jen v logu (`docker compose logs php`) |

## Struktura projektu

```
backend/
  src/Controller/NoteController.php        HTTP vrstva: mapuje požadavek na DTO a volá službu
  src/Dto/NoteInput.php                    vstup pro POST a PUT, pravidla validace
  src/Dto/NoteListQuery.php                filtr seznamu (?priority=)
  src/Dto/NoteResponse.php                 tvar poznámky v API
  src/Service/NoteService.php              zápisy: vytvoření, úprava, smazání
  src/Entity/Note.php                      entita a její pravidla (ořez názvu, časy změn)
  src/Enum/Priority.php                    backed enum priority a její pořadí
  src/Repository/NoteRepository.php        dotaz na seznam s řazením a filtrem
  src/EventListener/ApiExceptionListener.php   jednotný JSON pro všechny chyby
  src/Command/LoadDemoDataCommand.php      demo data
  migrations/                              Doctrine migrace
  tests/Api/NoteApiTest.php                funkční testy API
frontend/src/
  api/notes.ts                             typovaný klient API a třída ApiError
  views/                                   seznam, vytvoření, úprava, 404
  components/                              formulář, karta poznámky, filtr, štítek priority
  composables/useNoteSave.ts               společné odeslání formuláře a zobrazení chyb
docker/                                    Dockerfile pro PHP a web, nginx, init SQL
compose.yaml
.github/workflows/ci.yml
```

## Klíčové designové volby

**Symfony 7.4 LTS a PHP 8.5.** Symfony je standard pro PHP API a odpovídá stacku firmy. Volím LTS řadu: opravy chyb do 11/2028, bezpečnostní do 11/2029. Novější Symfony 8.1 má podporu jen do 01/2027. PHP 8.5 je nejnovější stabilní verze. MariaDB 12.3 je aktuální LTS.

**Vrstvy bez zbytečných abstrakcí.** Controller jen převede HTTP na DTO a zpět. Zápisy dělá `NoteService`. Pravidla, která patří k poznámce samotné (ořez názvu, kdy se mění `updatedAt`), jsou v entitě. Čtení jde z controlleru rovnou do repozitáře; služba by tu jen přeposílala volání. Nejsou tu rozhraní pro jednu implementaci ani vlastní repozitářové vrstvy nad Doctrine.

**DTO a Symfony Validator.** `NoteInput` odděluje kontrakt API od entity. Entita tak nikdy není v nevalidním stavu a API se může měnit nezávisle na databázi. Mapování těla dělá `#[MapRequestPayload]`, takže controller je krátký. Chybové hlášky jsou česky přímo u pravidel, protože je UI ukazuje uživateli. Výstup řídí `NoteResponse`, ne serializace entity.

**PUT místo PATCH.** Formulář vždy posílá celou poznámku, takže PUT (nahrazení celého zdroje) přesně odpovídá použití. Vytvoření i úprava sdílí jedno DTO a stejnou validaci. PUT je idempotentní, opakované odeslání nic nerozbije. PATCH by musel rozlišovat chybějící pole od `null` a dává smysl až pro dílčí změny, například rychlou změnu priority ze seznamu.

**Jednotný formát chyb.** Jeden listener převádí všechny výjimky pod `/api` na stejný JSON. 422 znamená neplatná pole, 400 nečitelný požadavek. Zvažoval jsem RFC 9457 (Problem Details). Vlastní tvar je pro frontend jednodušší, protože `fields` jde rovnou navázat na formulář. Přechod na RFC 9457 by byl jen změna listeneru.

**Řazení: priorita, pak poslední změna.** Nejdůležitější a nejčerstvější poznámky jsou nahoře. Priorita je v DB jako čitelný text (`high`), abecední řazení by tedy bylo špatně. Repozitář proto řadí přes `CASE` sestavený z enumu, pořadí je definované na jednom místě (`Priority::sortRank()`). Při shodě rozhoduje `id`, aby pořadí bylo stabilní. U filtrovaného seznamu je priorita stejná, `CASE` se vynechá a index `(priority, updated_at)` obslouží filtr i řazení bez třídění v paměti (ověřeno přes `EXPLAIN`). Uložení beze změny neposouvá `updatedAt`, takže poznámka zbytečně neskočí nahoru.

**Čas přes Clock.** Služba bere čas z `ClockInterface`, ne z `new DateTime()`. V testech je čas pevný, takže testy řazení jsou deterministické. V DB je všechno v UTC, API vrací ISO 8601 s posunem a prohlížeč zobrazí místní čas.

**Docker.** Tři služby: `db` (MariaDB), `php` (PHP-FPM) a `web` (nginx). Nginx servíruje sestavený frontend a `/api` posílá do PHP. UI i API jsou na stejné adrese, takže není potřeba CORS. Obrazy jsou vícestupňové: Node je jen v build stupni. PHP image má cíl `dev` (s dev závislostmi, aby šly pustit testy přímo v kontejneru) a `prod` (bez nich, optimalizovaný autoloader); CI sestavuje oba. Aplikace v kontejneru neběží pod rootem; produkční obraz bez `APP_SECRET` odmítne nastartovat. Nginx překládá adresu PHP kontejneru při každém požadavku, takže restart PHP nerozbije API. Služby mají healthchecky a startují ve správném pořadí: web čeká na PHP, PHP na databázi. Nginx posílá bezpečnostní hlavičky včetně Content-Security-Policy. Migrace se spouští v entrypointu PHP kontejneru; při více instancích by to byl samostatný krok nasazení. Dvě úpravy MariaDB: healthcheck se přihlašuje aplikačním uživatelem přes TCP (projde až po dokončení inicializace) a TLS je na interní síti vypnuté (`--skip-ssl`), protože certifikát generovaný při startu občas shodil první inicializaci.

**Testy.** Funkční testy volají API přes HTTP kernel proti skutečné MariaDB, ne proti SQLite, aby testovaly stejné SQL jako produkce. Každý test běží v transakci, která se na konci vrátí (DAMA DoctrineTestBundle), takže jsou rychlé a nezávislé. Testy pokrývají řazení, filtr, validaci, chyby a 404. PHPStan běží na nejvyšší úrovni včetně rozšíření pro Symfony a Doctrine. CI spouští stejný Compose stack jako lokálně.

**Frontend.** Vue 3 s Composition API a TypeScriptem. Stav je lokální ve view, Pinia by tu nic neřešila. Klient API je malý typovaný modul nad `fetch`, chyby převádí na `ApiError`. Filtr je v URL (`/?priority=high`), takže jde sdílet a přežije obnovení stránky. Pravidla validace jsou jen na backendu (jeden zdroj pravdy), UI zobrazí chyby u polí a počítadlo znaků jen upozorní předem.

## Edge cases, které jsem řešil

- **Prázdný název nebo jen mezery:** 422, název se před validací i uložením ořízne.
- **Příliš dlouhý text:** limity 200 a 10 000 znaků se počítají ve znacích, ne v bajtech; čeština i emoji se počítají správně (pokryto testem).
- **Neplatná nebo chybějící priorita:** 422 se seznamem povolených hodnot.
- **Špatný typ hodnoty**, např. `"title": 42`: 422 u daného pole.
- **Nevalidní JSON, prázdné tělo:** 400 `invalid_json`. Formulářová data místo JSON: 415.
- **Neexistující poznámka nebo nečíselné id:** 404 v JSON, i u `PUT` a `DELETE`.
- **Neplatný filtr** `?priority=urgent`: API vrací 400, UI neplatnou hodnotu z URL tiše odstraní.
- **Uložení beze změny:** neposune `updatedAt`, pořadí v seznamu se nezmění.
- **Rychlé přepínání filtru:** starší požadavek se zruší (`AbortController`), pomalejší odpověď nepřepíše novější.
- **Smazání poznámky, kterou už smazal někdo jiný:** UI ji jen odebere ze seznamu, cíl je splněný.
- **Úprava neexistující poznámky v UI:** stav „poznámka neexistuje“ s odkazem zpět.
- **Výpadek API nebo sítě:** chybový stav s tlačítkem „Zkusit znovu“. Odpověď, která není náš JSON (třeba 502 z nginx při startu PHP), dostane obecnou hlášku.
- **HTML v obsahu:** Vue text vždy escapuje, `v-html` se nepoužívá, takže XSS přes obsah poznámky nejde.
- **Dvojí odeslání formuláře:** tlačítko je během ukládání zablokované.
- **Opakovaný start kontejneru:** demo data se nahrají jen do prázdné databáze. Když smažete všechny poznámky a restartujete stack, demo data se vrátí; vypnout to jde přes `LOAD_DEMO_DATA: "0"` v `compose.yaml`.
- **Smazání během načítání seznamu:** seznam se po smazání načte znovu, takže starší odpověď nevrátí smazanou poznámku zpět.

## Další vývoj

- **Uživatelé a oprávnění.** Přihlášení (session nebo tokeny), sloupec `owner_id` a všechny dotazy omezené na vlastníka. Cizí poznámka by vracela 404, ne 403, aby nešlo zjistit, že existuje. To je první krok před nasazením pro víc lidí.
- **Souběžné úpravy.** Teď vyhrává poslední uložení. Přidal bych verzi poznámky (Doctrine `#[Version]`), `ETag` v odpovědi a `If-Match` u `PUT`. Při konfliktu by API vrátilo 412 a UI nabídlo porovnání změn.
- **Stránkování a hledání.** Seznam teď vrací všechno. S rostoucím počtem poznámek kurzorové stránkování (stabilní i při změnách) a fulltextové hledání v názvu a obsahu.
- **Archivace místo mazání.** Smazání je nevratné. Archiv nebo koš s obnovením, případně „Zpět“ hned po smazání, chrání před omylem.
- **Štítky.** Vazba M:N a filtr podle štítků. Priorita řadí, štítky třídí podle témat.
- **Markdown v obsahu.** Uložit zdrojový text, vykreslit na frontendu přes knihovnu se sanitizací HTML.
- **Rychlé akce.** Změna priority přímo ze seznamu přes `PATCH` (JSON Merge Patch), upozornění na neuložené změny při odchodu z formuláře.
- **Dokumentace API.** OpenAPI generované z DTO (NelmioApiDocBundle), aby kontrakt a dokumentace nemohly utéct od sebe.
- **Provoz.** Produkční image už existuje; doplnil bych migrace jako samostatný krok nasazení, secrets mimo repozitář, TLS k databázi, strukturované logy a monitoring, a E2E testy UI (Playwright) v CI.
