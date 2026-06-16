# Plan for LTS 2026

Bas: `1.4.3`. Nuvarande LTS-head: `f4f2427`. Ny upstream-bas: `3.18.3`.

## Slutsats

Upstream `3.18.3` är en större arkitekturförflyttning till
`api-event-manager`. Gamla REST-prefix- och importerpatchar ska inte flyttas som
filpatchar; LTS-arbetet bör lägga paketering och kompatibilitet ovanpå den nya
strukturen.

## Arbetsplan

- [ ] Starta från upstream `3.18.3`.
- [ ] Anpassa Composernamn, licens och installer-inställningar.
- [ ] Behåll upstreams nya serviceberoenden och REST/post type-modell.
- [ ] Verifiera event-endpoints och post type-namn mot LTS-bundlets
      förväntningar.
- [ ] Identifiera konsumenter som fortfarande använder gamla `/json/`-URL:er.

## Beslutstabell

| Område | Vår slutändring | Upstream-läge | Bedömning | Berörda commits |
| --- | --- | --- | --- | --- |
| Composer och paketering | Bytte till `municipio/wp-plugin-hbg-event-manager`, GPL och installer-konfiguration. | Upstream heter `helsingborg-stad/api-event-manager`, kräver PHP 8.1+ och nya servicepaket. | Återskapa smalare | `b1bb75b`, dokumentations-/licenscommits |
| Vendorhantering | Tog bort vendorkod och flyttade libphonenumber till dev/suggest. | Upstream har själv gått mot Composer-beroenden utan vendorkatalog. | Ersätt | `2a89f85` |
| REST-prefix | Tog bort `/json/`-prefix och återgick till `/wp-json/`. | Upstream har ny REST/post type-modell med `rest_base => events`; gamla klasser saknas. | Släpp | `42ae3c0` |
| Pluginheader och releaseinfo | Bytte namn, version och licens i gammal entrypoint. | Upstream entrypoint heter `api-event-manager.php`. | Återskapa metadata, inte gammal patch | `b1bb75b`, releasecommits |
| Dokumentation | LTS README/licens. | Ska skrivas om mot ny pluginstruktur. | Ej relevant | `bb7b0a1` och docs |

## Risker att verifiera

- Migrering från gamla post type-/API-kontrakt till nya `Event`-modellen.
- Att LTS-metapaketet tillhandahåller kompatibla forks för `wpservice`,
  `acfservice` och övriga servicepaket.
- Eventuella konsumenter som fortfarande anropar gamla `/json/`-URL:er.

## Analyskommandon

- `git diff --stat 1.4.3..HEAD`
- `git diff --stat 1.4.3..3.18.3`
- `git diff --stat HEAD..3.18.3`
- `git log --reverse --format='%h%x09%ad%x09%s' --date=short 1.4.3..HEAD`
- `git log --reverse --format='%h%x09%ad%x09%s' --date=short 1.4.3..3.18.3`
- Riktade `git diff`, `git show` och `git grep` för Composer, entrypoint,
  REST-filter, post type-registrering och nya serviceberoenden.
