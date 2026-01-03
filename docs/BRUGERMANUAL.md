# Sikkerjob PTW-System - Brugermanual

## Velkommen til PTW-Systemet

PTW (Permit To Work) systemet er et digitalt værktøj til håndtering af arbejdstilladelser på industrianlæg. Systemet sikrer at alt arbejde godkendes korrekt før det påbegyndes.

**Webadresse:** https://ptw.interterminals.app

---

# Indholdsfortegnelse

1. [Login](#1-login)
2. [Navigation](#2-navigation)
3. [PTW-oversigt](#3-ptw-oversigt)
4. [Opret ny PTW](#4-opret-ny-ptw)
5. [Dashboard](#5-dashboard)
6. [Kortet](#6-kortet)
7. [PTW Detaljer](#7-ptw-detaljer)
8. [Godkendelsesprocessen](#8-godkendelsesprocessen)
9. [Brugerroller](#9-brugerroller)
10. [Stemmeassistent](#10-stemmeassistent)

---

# 1. Login

![Login-siden](screenshots/login.png)

## Sådan logger du ind:

1. Gå til **https://ptw.interterminals.app**
2. Indtast dit **brugernavn** (dit navn og efternavn)
3. Indtast din **adgangskode**
4. Klik på **"Log ind"**

## Ny bruger?
Klik på **"Opret ny bruger"** nederst på siden for at oprette en konto. Din konto skal godkendes af en administrator før du kan logge ind.

## Glemt adgangskode?
Kontakt din administrator for at få nulstillet din adgangskode.

---

# 2. Navigation

![Navigation](screenshots/navigation.png)

## Hovedmenu (øverst på siden)

| Menu-punkt | Beskrivelse |
|------------|-------------|
| **PTW-oversigt** | Se alle arbejdstilladelser |
| **Opret ny PTW** | Opret en ny arbejdstilladelse |
| **Kort** | Se PTW'er på kortet |
| **Dashboard** | Statistik og overblik |
| **Admin** | Brugeradministration (kun admin) |
| **SMS Notifikationer** | SMS-indstillinger |
| **Modulstyring** | Aktiver/deaktiver funktioner |

## Øverste højre hjørne
- **Brugerinfo** - Viser hvem du er logget ind som
- **Klokke-ikon** - Notifikationer
- **Spørgsmålstegn** - Hjælp
- **Log ud** - Log ud af systemet

---

# 3. PTW-oversigt

![PTW-oversigt Liste](screenshots/ptw-liste.png)

PTW-oversigten viser alle arbejdstilladelser i systemet.

## Visningstyper

### Listevisning
Viser PTW'er i en tabel med kolonner:
- **PTW NR.** - Unikt nummer
- **BESKRIVELSE** - Kort beskrivelse af arbejdet
- **INDKØBSORDRE** - P-nummer og beskrivelse
- **JOBANSVARLIG** - Ansvarlig person
- **ENTREPRENØR** - Firma og kontaktperson
- **STATUS** - Planlagt, Aktiv eller Afsluttet
- **GODKENDELSER** - Dagens godkendelsesstatus
- **HANDLINGER** - Vis, Rediger, Slet

### Boksvisning
![PTW Boksvisning](screenshots/ptw-boks.png)

Viser PTW'er som kort med:
- PTW-nummer og status
- Basisinformation (kan foldes ud)
- Godkendelsesproces (viser 2/3 godkendt osv.)
- Dokumentationsbilleder
- Handlingsknapper: **Vis**, **Rediger**, **Slet**

## Filtrering

### Hurtigfiltre (checkbokse)
| Filter | Farve | Beskrivelse |
|--------|-------|-------------|
| **VIS PLANLAGTE** | Blå | PTW'er der ikke er startet |
| **VIS AKTIVE** | Orange | PTW'er der er i gang |
| **VIS AFSLUTTEDE** | Grå | Færdige PTW'er |
| **IGANGVÆRENDE** | Grøn | Arbejde der foregår lige nu |

### Avanceret Filtrering
Klik på **"Avanceret Filtrering"** for at filtrere efter:
- Dato-interval
- Entreprenørfirma
- Jobansvarlig
- Godkendelsesstatus

## Handlinger

| Ikon | Handling |
|------|----------|
| 👁️ (Øje) | Vis PTW detaljer |
| ✏️ (Blyant) | Rediger PTW |
| 🗑️ (Skraldespand) | Slet PTW |
| ✅ **Godkend** | Godkend som din rolle |

---

# 4. Opret ny PTW

![Opret ny PTW](screenshots/opret-ptw.png)

*Kun tilgængelig for: Admin, Drift, Opgaveansvarlig*

## PDF Upload (Anbefalet)
1. Klik på **"Klik for at vælge PDF fil..."**
2. Vælg en arbejdsordre-PDF fra din computer
3. Klik **"Parse PDF"**
4. Systemet udfylder automatisk felterne
5. Gennemgå og ret eventuelle fejl

## Manuel udfyldning

### Grundlæggende oplysninger
| Felt | Beskrivelse |
|------|-------------|
| **PTW Nr.** | Unikt arbejdstilladelsesnummer |
| **Status** | Planlagt, Aktiv eller Afsluttet |
| **Beskrivelse** | Kort beskrivelse af arbejdet |

### Tekniske oplysninger
| Felt | Beskrivelse |
|------|-------------|
| **Indkøbsordre nummer** | P-nummer |
| **MPS-nr.** | Material Planning System nummer |
| **Indkøbsordre beskrivelse** | Detaljeret beskrivelse |
| **Komponent nr.** | Liste over komponenter |

### Ansvarlige og entreprenør
| Felt | Beskrivelse |
|------|-------------|
| **Jobansvarlig** | Navn på ansvarlig person |
| **Telefon** | Kontaktnummer |
| **Entreprenør firma** | Entreprenørens firmanavn |
| **Entreprenør kontakt** | Kontaktperson hos entreprenør |

### Placering på kort
- Klik på kortet for at vælge lokation
- Koordinaterne udfyldes automatisk

### Gem
Klik **"Gem Work Order"** for at oprette PTW'en.

---

# 5. Dashboard

![Dashboard](screenshots/dashboard.png)

Dashboard giver et hurtigt overblik over systemet.

## Statistikkort (øverst)

| Kort | Beskrivelse |
|------|-------------|
| **TOTAL PTW'ER** | Samlet antal arbejdstilladelser |
| **AKTIVE PTW'ER** | PTW'er der er i gang nu |
| **FULDFØRTE** | Afsluttede opgaver |

## Hurtige Handlinger
Fire knapper til hurtig navigation:
- **+ Opret ny PTW** - Gå til oprettelse
- **Ventende Godkendelser** - Se hvad der mangler godkendelse (viser antal)
- **Aktive i dag** - Se dagens aktive PTW'er (viser antal)
- **Vis Kort** - Gå til kortvisning

## Ventende Godkendelser i Dag
Viser status for de tre godkendelsesroller:
| Rolle | Status |
|-------|--------|
| **Opgaveansvarlig** | Antal ventende / ALLE GODKENDT |
| **Drift** | Antal ventende / ALLE GODKENDT |
| **Entreprenør** | Antal ventende / ALLE GODKENDT |

## Aktivitet - Sidste 7 Dage
Graf der viser PTW-aktivitet over den seneste uge.

## Status Distribution & Entreprenører
- Cirkeldiagram over PTW-statusfordeling
- Oversigt over entreprenørfirmaer og deres PTW'er

---

# 6. Kortet

![Kortet](screenshots/kort.png)

Det interaktive kort viser alle PTW'er på deres fysiske placering på anlægget.

## Søgefelt
Øverst kan du søge efter:
- Beskrivelse
- Jobansvarlig
- Entreprenør

## Forklaring (Legend)
| Symbol | Betydning |
|--------|-----------|
| ● | SJA tilknyttet |
| 🔧 | Arbejder |
| ⬛ | Stoppet |

## Filtre
Checkbokse til at vise/skjule:
- ☑️ **Planlagte** - Blå markører
- ☑️ **Aktive** - Orange markører
- ☑️ **Afsluttede** - Grå markører
- ☑️ **Igangværende** - Grønne pulserende markører

## Markørfarver

| Farve | Betydning |
|-------|-----------|
| 🟢 Grøn (pulserende) | Arbejde i gang lige nu |
| 🟢 Grøn | Aktiv PTW |
| 🔵 Blå | Planlagt PTW |
| ⚪ Grå | Afsluttet PTW |

## Interaktion
- **Klik på markør** - Se PTW-info popup
- **Zoom** - Brug + / - knapperne eller scroll
- **Panorér** - Træk i kortet

---

# 7. PTW Detaljer

![PTW Detaljer](screenshots/ptw-detaljer.png)

Når du klikker på en PTW, ser du alle detaljer.

## Øverste knapper
- **← Tilbage til oversigt** - Gå tilbage til listen
- **🖨️ Print** - Udskriv PTW'en

## Basisinformation
Viser alle PTW-oplysninger:
| Felt | Eksempel |
|------|----------|
| PTW NR. | 2560809 |
| BESKRIVELSE | D5 Out of service |
| INDKØBSORDRE NUMMER | P6251464 |
| INDKØBSORDRE BESKRIVELSE | Tomas Møller: Assister med udskiftning af tankbund |
| JOBANSVARLIG | Tim Marcher Andersen |
| TELEFON | 24664209 |
| PTW OPRETTET AF | 12817 |
| PTW OPRETTET DATO | 2025-06-19 |
| ENTREPRENØR FIRMA | Smed & Entreprenør Thomas Møll |
| KOMPONENT NR. | OIT36 EGD50BB001 TANK 5 D-GRUPPE 48000 m3x |
| STATUS | Aktiv |
| LOKATION (LAT,LNG) | 3436, 3267 |

## Sektioner (kan foldes ud/ind)

### Godkendelsesproces
Viser status: **Godkendt 2/3** betyder 2 af 3 roller har godkendt.

### Godkendelseshistorik
Viser hvem der har godkendt og hvornår.

### Dokumentationsbilleder
Upload og vis billeder fra arbejdet.

---

# 8. Godkendelsesprocessen

## Sådan fungerer godkendelse

En aktiv PTW kræver **daglig godkendelse** fra tre roller:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│ OPGAVEANSVARLIG │ →  │      DRIFT      │ →  │   ENTREPRENØR   │
│   (1. trin)     │    │    (2. trin)    │    │    (3. trin)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Godkendelsesstatus i listen

| Symbol | Betydning |
|--------|-----------|
| ✅ Grøn check | Godkendt i dag |
| ❌ Rød X | Ikke godkendt |
| **Godkend** knap | Din tur til at godkende |

## Daglig nulstilling
- Alle godkendelser nulstilles ved midnat
- Alle tre roller skal godkende **samme dag**
- Når alle har godkendt, starter arbejdet automatisk

## Sådan godkender du

### Fra PTW-oversigten:
1. Find PTW'en i listen
2. Se kolonnen **GODKENDELSER DAGENS STATUS**
3. Klik på **"Godkend"** knappen ud for din rolle
4. Godkendelsen registreres med det samme

### Fra PTW-detaljer:
1. Åbn PTW'en ved at klikke på øje-ikonet
2. Fold **"Godkendelsesproces"** ud
3. Klik **"Godkend"** ud for din rolle

---

# 9. Brugerroller

## Oversigt over roller og rettigheder

| Rolle | Kan se | Kan oprette | Kan godkende |
|-------|--------|-------------|--------------|
| **Admin** | Alle PTW'er | ✅ Ja | Alle roller |
| **Drift** | Alle PTW'er | ✅ Ja | Som Drift |
| **Opgaveansvarlig** | Alle PTW'er | ✅ Ja | Som Opgaveansvarlig |
| **Entreprenør** | Kun eget firma | ❌ Nej | Som Entreprenør |

## Administrator
- Fuld adgang til alle funktioner
- Kan oprette og godkende brugere
- Kan godkende som alle roller
- Adgang til Admin-panel og Modulstyring

## Drift
- Kan se alle PTW'er
- Kan oprette nye PTW'er
- Godkender som "Drift" (2. trin i processen)

## Opgaveansvarlig
- Kan se alle PTW'er
- Kan oprette nye PTW'er
- Godkender som "Opgaveansvarlig" (1. trin i processen)

## Entreprenør
- Kan **kun** se PTW'er for eget firma
- Kan **ikke** oprette eller redigere PTW'er
- Godkender som "Entreprenør" (3. trin)
- Kan starte/stoppe arbejde
- Kan uploade dokumentationsbilleder

---

# 10. Stemmeassistent

Systemet har en indbygget stemmeassistent (den blå cirkel nederst til højre).

## Sådan bruger du stemmeassistenten

1. Klik på **mikrofon-knappen** (blå cirkel)
2. Knappen bliver rød når den lytter
3. Tal din kommando
4. Klik igen for at stoppe

## Stemmekommandoer

### Navigation
| Kommando | Handling |
|----------|----------|
| "Dashboard" | Gå til dashboard |
| "Kort" | Gå til kortet |
| "Opret ny" | Gå til opret ny PTW |
| "Hjem" / "Oversigt" | Gå til PTW-oversigt |

### Filtrering
| Kommando | Handling |
|----------|----------|
| "Vis aktive" | Filtrer kun aktive PTW'er |
| "Vis planlagte" | Filtrer kun planlagte |
| "Vis afsluttede" | Filtrer kun afsluttede |
| "Vis alle" | Fjern alle filtre |

### Søgning
| Kommando | Handling |
|----------|----------|
| "Søg efter [tekst]" | Søg i PTW-listen |

### Spørgsmål
Du kan stille spørgsmål om systemet:
- "Hvad betyder status aktiv?"
- "Hvordan godkender jeg en PTW?"
- "Hvem kan oprette PTW'er?"

---

# Hurtig Reference

## Statusfarver

| Farve | Status |
|-------|--------|
| 🔵 Blå | Planlagt |
| 🟠 Orange/Grøn | Aktiv |
| ⚪ Grå | Afsluttet |

## Godkendelsesikoner

| Ikon | Betydning |
|------|-----------|
| ✅ | Godkendt i dag |
| ❌ | Ikke godkendt |
| **Godkend** | Din tur |

## Genveje

| Handling | Hvordan |
|----------|---------|
| Søg PTW | Brug søgefeltet øverst |
| Filtrer | Klik på status-knapperne |
| Se detaljer | Klik på øje-ikonet |
| Godkend | Klik "Godkend" knappen |
| Brug stemme | Klik på blå mikrofon-cirkel |

---

*Sikkerjob PTW-System*
*https://ptw.interterminals.app*
*Ved spørgsmål, kontakt din administrator*
