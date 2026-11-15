# Roadmap: PS Pen & Paper

## Vision

PS Pen & Paper wird ein universeller Baukasten für Pen-and-Paper-Rollenspiele
auf WordPress und ClassicPress. Spielleiter erschaffen eigene Kampagnen, Regeln,
Charakterbögen und Abenteuer. Das Plugin schreibt weder eine Spielwelt noch ein
bestimmtes Regelsystem vor.
Fachliche Optionen wählt die jeweilige Administration oder Spielleitung
innerhalb ihrer Rechte im Plugin, pro Vorgang beziehungsweise Kampagne.
Persönliche Entwicklungspräferenzen werden nicht als globale Spielregeln festgelegt.

Mehrere Kampagnen mit unterschiedlichen Regeln können auf derselben Website
nebeneinander bestehen. Das Plugin soll ohne externe Spieleplattform,
Echtzeitpflicht oder große Game-Engine nutzbar sein.

Diese Roadmap ist ein Arbeitsentwurf, keine Zusage für bestimmte Termine oder
einen endgültigen Funktionsumfang.

## Entwicklungsstand

- Kampagnenverwaltung mit kampagnenbezogenen Spielleiter- und Spielerrechten
  sowie ausdrücklich vergebener Erstellberechtigung ist vorhanden.
- Der erste Regelwerks-Editor unterstützt erlaubte Würfel und frei benennbare
  Charakterfelder mit Startwerten und Grenzen.
- Charaktere können aus der Vorlage erstellt werden. Spielleiter konfigurieren
  pro Kampagne direkte Spielerbearbeitung, Freigabepflicht oder ausschließliche
  Spielleiterbearbeitung. Gültige Werte und offene Anträge werden getrennt
  gespeichert; Charaktere sind nur für Besitzer und Spielleiter sichtbar.
- Änderungen an verwendeten Feldstrukturen sind zunächst gesperrt. Eine
  kontrollierte Übernahme solcher Änderungen folgt später.
- Die Verwaltung verwendet responsives CSS und Vanilla JavaScript; die
  Einrichtung auf Handy und Tablet wird bei weiteren Schritten mitgeprüft.
- Der geschützte Frontend-Spielbereich per `[ps_pen_paper]` zeigt Kampagnen,
  Regeln und Charaktere mit denselben Bearbeitungs- und Freigaberechten.
  Eine optionale Spielbereich-Seitenvorlage ist unabhängig vom Theme-Layout.
- Spielleiter passen Farben pro Kampagne über „Style“ an, einschließlich
  Live-Vorschau, Standardfarben und Kontrasthinweisen. Freies CSS ist dafür
  nicht nötig.
- Die erste Würfelmechanik unterstützt begrenzte W-/D-Ausdrücke, optionale
  freigegebene Attributwerte und ein geschütztes Wurfprotokoll. Spielleiter
  bestimmen erlaubte Gruppen-, Privat- und verdeckte Würfe pro Kampagne.
- SVG-Würfelformen und eine reduzierte Bewegung respektierende Wurfanimation
  visualisieren ausschließlich bereits gespeicherte Serverergebnisse.
- Ein gemeinsames Ereignisprotokoll verbindet bestehende Würfe mit neuen Regel-
  und Charakteränderungen, Anträgen und Freigabeentscheidungen. Vorher-/Nachher-
  Stände bleiben erhalten; Charaktereinträge sind nur für Besitzer und aktuelle
  Spielleiter sichtbar. Die zentrale Dokument-/Ereignisspeicherung verwendet
  inzwischen eine gemeinsame InnoDB-Transaktion; fehlende Voraussetzungen
  blockieren Änderungen. Unklare Abschlussbestätigungen führen ausdrücklich
  zur Administratorprüfung statt zu einer Erfolgsmeldung.
- Seit Version 0.8.0 gibt es einen digitalen Spieltisch mit Teilnehmerleiste,
  lesbaren Charakterbögen, mobilen Bereichen und getrenntem Würfel-/Protokollbereich.
  Freiwillige Anwesenheit, manuelles AFK und ein revisionsgeschützter,
  protokollierter Zugmarker sind vorhanden.
- PS Chat ist optional als einklappbare Chatbox eingebettet. Die ergänzten
  Chat-Schnittstellen prüfen aktuelle Kampagnenmitgliedschaften und vergeben
  Moderation an die Spielleitung, ohne globale Benutzerrollen zu erweitern.
- Version 0.9.0 trennt die große Erzählfläche, die kompakte Gruppenleiste und
  den gegliederten Charakterbogen. Übersicht, Kampagnen, Charakterverwaltung
  und Einstellungen sind eigene Verwaltungsbereiche.
- Zugang und optionaler öffentlicher Teaser sind ausdrücklich konfigurierbar.
  Angemeldete Website-Mitglieder können eine Aufnahme beantragen; nur die
  Spielleitung entscheidet. Die interne Beschreibung wird nie zum Teaser.
- Eigene Hintergrundbilder der Spielfläche werden ohne öffentliche Medien-URL
  geschützt gespeichert. Seit 0.14.0 sind auch private Charakterporträts und
  dekorative Bogenhintergründe vorhanden; öffentliche Charakteravatare bleiben offen.
- Netzwerk-Superadmins behalten auch ohne Unterwebsite-Mitgliedschaft Zugriff
  auf sämtliche dortigen Spielinhalte und die Chat-Moderation.
- Version 0.10.0 ergänzt die private Universumsverwaltung im Backend und Frontend.
  Universen besitzen Name, interne Weltbeschreibung und revisionsgeschützte
  Historie. Kampagnen lassen sich nach Vorschau und Bestätigung zuordnen oder
  lösen, ohne ihre Regeln, Bögen, Freigaben oder Rechte zu verändern.
- Die Textdomain `ps-pen-paper` ist eingebunden; ein POT-Katalog im Verzeichnis
  `languages/` dient als Grundlage für spätere PO/MO-Übersetzungen.
- Version 0.12.0 ergänzt gespeicherte Szenenentwürfe mit getrennten Geheimnissen,
  privater Inhaltsvorschau, ausdrücklicher Freigabe, Szenenauswahl und Rücknahme.
  Der optionale Kartentisch unterstützt geschützte Bilder, Zoom, Verschieben,
  Zwei-Finger-Zoom, bis zu 30 zuweisbare Marker, Raster-Einrasten und kurzlebige Pings.
  Spieler können nur ihre Marker bewegen und nur die aktive freigegebene Szene
  abrufen. Eine Aktualisierung erfolgt alle fünf Sekunden ohne WebSocket-Pflicht.
- Version 0.13.0 ergänzt die private Spielleitermappe mit Kapiteln, sortierten
  Szenenfolgen, Personen, Orten und Notizen sowie bewusst freigegebenen
  Text-/Bild-Handouts. Kapitel- und Szenenverknüpfungen vergeben keine Rechte.
  Neue Szenen beginnen als reine Erzählszenen; Kartenwerkzeuge bleiben optional.
- Version 0.14.0 ergänzt einen privaten PS-Community-Profil-Tab, lokale und
  Universums-Gegenstandskataloge, frei benannte Slots/Seltenheiten, konkrete
  Inventarexemplare und freigabefähige Ausrüstung mit additiven Boni.
  Ohne Community bleiben Bestände erhalten und Boni inaktiv.
  Handel ergänzt Version 0.20.0; ein grafischer Slotdesigner fehlt weiterhin.
- Version 0.15.0 ergänzt den privaten Abenteuerplan mit Kapitelreihenfolge,
  aktueller/nächster Szene und szenenbezogener Regie.
- Version 0.16.0 ergänzt Währungsname und Kürzel, ganzzahlige Guthaben,
  einstellbare getrennte oder gemeinsame Konten, zuständige
  Spielleiterbuchungen und eingefrorene Gegenstandsbindungen.
- Version 0.17.0 ergänzt strukturierte Gegner/NPCs, private Szenenregie,
  bestätigte Universumsaufnahme und unabhängige Kopien von Figurenvorlagen.
- Version 0.18.0 ergänzt lokale Beutetabellen, eingefrorene Würfelvorschläge
  und bestätigte einmalige Vergaben von Gegenständen und/oder Währung.
- Version 0.19.0 ergänzt das optionale öffentliche PS-Wiki-Weltlexikon:
  bewusst freigegebene Kategorien, getrennte Artikeltexte, stabile
  Figurenzuordnungen und ausdrücklich bestätigte Veröffentlichungen.
- Version 0.20.0 ergänzt unabhängig aktivierbare Geschenke, Verkäufe und
  Tauschangebote derselben Kampagne, konkrete Besitzerzustimmung,
  Exemplarreservierung und einstellbare zusätzliche Spielleiterfreigabe.
  Inventare, Geld und Pflichtereignisse werden atomar übertragen.
- Version 0.21.0 ergänzt den ersten isolierten Soloabenteuer-Ablauf:
  freigegebene Szenen, Entscheidungen, optionale Serverproben und private,
  revisionsgeschützte Einzeldurchläufe. Kampagnenszene, Charakterwerte,
  Ressourcen und Beute werden dabei nicht verändert.
- Version 0.22.0 ergänzt optionale Mindestwerte für Soloentscheidungen:
  Nicht erfüllte Voraussetzungen bleiben sichtbar und sperren die Auswahl.
  Der Server prüft freigegebene Zahlen-/Attributwerte erneut und protokolliert
  den verwendeten Wert zusammen mit dem Fortschritt.
- Version 0.23.0 ergänzt optionale Ressourcenkosten je Soloentscheidung:
  Ressource, Betrag und Kostenzeitpunkt sind ausdrücklich konfigurierbar.
  Verbrauch und Fortschritt werden atomar protokolliert; lokale Bögen und
  unabhängige Kopien sind unterstützt, gemeinsame Basisbögen noch nicht.
- Version 0.24.0 ergänzt optionale Soloentscheidungsbelohnungen:
  feste Gegenstände/Währung oder eingefrorene Beutetabellen, Auslöser je
  Entscheidung und atomare, einmalige Vergaben mit privatem Nachweis.
  Gemeinsame Guthaben behalten die Autorisierung durch die Universumsleitung.
- Version 0.25.0 ergänzt konfigurierbare Wertebedingungen mit UND/ODER:
  sechs Vergleiche für Zahlen, Attribute und Ressourcen (aktueller Wert oder
  Maximum), ohne Verbrauch und mit unveränderlichem privatem Nachweis.
  Ältere Mindestwert-Voraussetzungen bleiben gültig.
- Version 0.26.0 ergänzt einen privaten, versionierten JSON-Kampagnenexport:
  lokal mit ausgewiesenen Abhängigkeiten oder einschließlich benötigter
  Universumsdaten bei zusätzlicher Verwaltungsberechtigung. Andere Kampagnen
  und unbeteiligte Basisbögen bleiben ausgeschlossen.
- Version 0.27.0 ergänzt die private Importvorschau: Dateiprüfung,
  neue oder bestehende Zielkampagne, getrennte oder bestehende Universumsdaten,
  ausdrückliche Konten-/Basiszuordnung und Konfliktanzeige. Sie verändert
  keine Spielstände. Die schreibende Wiederherstellung folgt in 0.29.0.
- Version 0.28.0 ergänzt fachliche Dokumentprüfungen mit vorhandenen Regel-,
  Charakter-, Story- und Wirtschaftsvalidatoren. Ungültige Stände werden
  abgewiesen, noch ungeprüfte Metadaten ausdrücklich angezeigt. Protokolle,
  vollständige Referenzauflösung und schreibende Wiederherstellung folgen in 0.29.0.
- Version 0.29.0 ergänzt tatsächliche neue Kampagnen und bestätigte Ersetzungen
  mit typisierten Referenzen, atomarer Speicherung, privater Zielsicherung,
  Wiederholungsnachweisen und Schutz vor veralteten Zielständen/Formularen.
  Bekannte ungültige Daten werden nicht still repariert; unbekannte Metadaten
  bleiben nur bei ausdrücklicher Zustimmung privat archiviert.

### Universum vor Kampagne: Grundmodell seit Version 0.11.0

- Universen besitzen Regelvorlage und leitungsverwaltete Basisbögen.
  Leere Kampagnen übernehmen die Vorlage ausdrücklich mit stabilen Feldkennungen;
  spätere Regeländerungen werden nicht automatisch synchronisiert.
- Bei der Aufnahme eines bereits zugelassenen Besitzers wählt die Spielleitung
  **gemeinsamen Basisstand oder unabhängige Kampagnenkopie**. Beide Varianten sind
  vorhanden; bestehende Kampagnenbögen bleiben unverändert.
- Kampagnenleitungen können eigene ganzzahlige Attribut- und Zahlenauflagen
  unmittelbar freigeben. Ein frei benanntes Stufenfeld ist möglich, aber kein
  festes Levelsystem. Basiswerte und andere Kampagnen werden nicht überschrieben.
- Basisfortschritt und lokale Auflagen besitzen Revisionen und geschützte
  Historien. Würfe speichern tatsächlich verwendete Werte sowie Basis- und
  Kontextrevisionen; alte Würfe werden nicht nachträglich berechnet.
- Gemeinsame Basisbögen können nach Vorschau und Bestätigung als unabhängige
  Kampagnenbögen weitergeführt werden. Freigegebene Werte und Auflagen werden
  übernommen, Ausrüstung und persönliche Konten bleiben erhalten.
  Gemeinsame Universumskonten benötigen vor dem Ablösen eine Kontenmigration.
- Geschichten, Teilnehmer, Chat und Protokolle bleiben kampagnengetrennt.
  Universumsbesitz oder Bogenbesitz gewährt keine zusätzliche Teilnahme.

### Weitere strukturelle Schritte

- Kontrollierte Migration bestehender Kampagnen mit Vorschau, eindeutiger
  Zuordnung und Konfliktauflösung; keine stillschweigende Zusammenführung.
- Kontrollierte Rückverbindung unabhängiger Kopien mit Vorschau und
  Konfliktauflösung. Noch gemeinsame Bögen sperren einen Universumswechsel;
  das ausdrückliche Ablösen ist bereits möglich, sofern keine gemeinsamen
  Universumskonten daran gebunden sind.
- Konfigurierbare universumsweite Teilnahme und zusätzliche kampagnenbezogene
  Freigaben. Aktuell entscheidet weiterhin jede Kampagne über ihre Mitglieder;
  eine spätere Universumsmitgliedschaft darf keine Geschichten ungefragt öffnen.
- Spielerbearbeitung von Basisbögen mit passendem Antrags- und Freigabemodell.
- Ressourcenauflagen, optionaler XP-/Levelautomatismus und getrennte Spielsitzungen.

## Grundprinzipien

- **Kampagnen sind getrennte Spielräume:** Mitglieder, Regeln, Inhalte und
  Spielereignisse bleiben getrennt. Gemeinsame Basisbögen werden ausdrücklich
  eingebunden; kampagnenbezogene Auflagen ändern ihren Ursprung nicht.
- **Das Regelwerk ist konfigurierbar:** Begriffe wie Klasse, Rasse oder Mana
  sind nicht verpflichtend. Vorlagen sind Angebote, keine Vorgaben.
- **Felder sind umbenennbar:** Ein Ressourcenfeld kann beispielsweise „Mana“,
  „Wut“ oder „Glück“ heißen. Seine interne Kennung bleibt beim Umbenennen
  erhalten, damit Werte und Verknüpfungen bestehen bleiben.
- **Sichtbarkeit ist eine Berechtigung:** Geheime Inhalte werden nicht nur
  optisch versteckt, sondern bei jedem Zugriff geschützt.
- **Erweiterungen sind optional:** Das Grundsystem funktioniert auch ohne
  PS Chat, PS Wiki oder andere Plugins.
- **Gemeinsam und zeitversetzt spielbar:** Die Gruppe kann live spielen,
  Echtzeitkommunikation ist aber keine Voraussetzung.
- **Einfach bedienen:** Theme-unabhängige Frontend-Ausgabe, zugängliche
  Oberflächen und möglichst wenige zusätzliche Abhängigkeiten.

## Fachliche Anforderungen des weiteren Ausbaus

### 1. Regeländerungen ohne Datenverlust

Stabile Feldkennungen und eine Sperre für Änderungen an verwendeten
Feldstrukturen schützen bestehende Charaktere bereits. Umbenennungen ändern
keine Feldkennung; neue Startwerte überschreiben keine bestehenden Werte.
Neue Regeländerungen besitzen inzwischen zusätzlich einen Vorher-/Nachher-Verlauf.
Eine Änderungsvorschau und kontrollierte strukturelle Übernahme stehen noch aus.

- Vor Änderungen an Wertebereichen, Feldtypen oder der Feldliste betroffene
  Charaktere und offene Freigabeanträge in einer Änderungsvorschau zeigen.
- Ungültig werdende Werte ausdrücklich auflösen lassen, statt sie automatisch
  abzuschneiden, zurückzusetzen oder zu löschen. Bis eine kontrollierte
  Übernahme vorhanden ist, bleibt die bestehende Sperre erhalten.
- Regeländerungen mit Revision und verantwortlichem Spielleiter dokumentieren.
  Bereits gespeicherte Würfe behalten ihre damaligen Werte und Bezeichnungen.

### 2. Ein gemeinsames, geschütztes Spielprotokoll

Die gemeinsame Grundlage für Würfe, Charakter- und Regeländerungen ist
vorhanden. Weitere schreibende Module müssen daran anschließen, ohne den
Schutz bestehender Ereignisse aufzuweichen.

- Würfe, Charaktererstellung, Attribut- und Ressourcenänderungen,
  Freigabeanträge, Freigaben, Ablehnungen und Regeländerungen nachvollziehen.
  Seit 0.12.0 werden auch Szenenveröffentlichungen, Rücknahmen und Kartenbewegungen erfasst.
- Zeitpunkt, handelnde Person, betroffene Kennungen und Revisionen festhalten;
  bei Wertänderungen Vorher/Nachher und bei Freigaben die Entscheidung.
- Ereignisse erst für tatsächlich gespeicherte Aktionen erfassen.
  Fehler zwischen Änderung und Protokollierung dürfen nicht unbemerkt bleiben.
- Sichtbarkeit pro Ereignis durchsetzen: Ein privater Charakterwert oder eine
  Spielleiteraktion wird nicht durch einen allgemeinen Protokolleintrag öffentlich.
  Auch Filter, Seitenzahlen und Vorschauen dürfen keine Geheimnisse verraten.
- Historische Einträge nicht nachträglich überschreiben; Korrekturen als neue
  Ereignisse erfassen. Das Protokoll ergänzt Datensicherungen, ersetzt sie aber nicht.

### 3. Den Story Builder als Herzstück entwickeln

Seit Version 0.12.0 ist der erste durchgängige Szenenablauf vorhanden:
Szene vorbereiten, Inhaltsvorschau prüfen, freigeben, am Spieltisch anzeigen und
zurücknehmen. Seit 0.13.0 sind Kapitel mit Szenenfolge, eine kleine private
Spielleitermappe und geschützte Text-/Bild-Handouts vorhanden. Redaktioneller
Komfort, weitere Dateiformate und differenzierte Handoutempfänger bleiben offen.

- Spielertext und Spielleitergeheimnisse klar getrennt bearbeiten und speichern.
- Entwurf, Veröffentlichung und Rücknahme bewusst unterscheiden; Änderungen
  an einer bereits veröffentlichten Szene nicht versehentlich sofort ausliefern.
- Den ersten vollständigen Ablauf einschließlich Rechteprüfung und
  Protokolleintrag umsetzen, bevor NPC-Verwaltung oder komplexe Begegnungen folgen.
- Einrichtung und Bedienung auch auf Handy und Tablet ermöglichen, ohne
  verpflichtendes Drag-and-drop oder einen großen visuellen Abenteuergraphen.

## Der digitale Spieltisch und sein weiterer Ausbau

Oberfläche, Zugsteuerung, Anwesenheit, AFK, eigene Bildhintergründe und Chat-Anbindung
sind vorhanden. Seit 0.12.0 ist die Spielfläche mit freigegebenen Szenen und einem
schlanken Kartentisch verbunden. Bild-Avatare, Bogen-Hintergründe, optionale
Levelautomatik und der weitere Story-Builder-Ausbau bleiben Entwicklungsziele.
Vorbereitung und Spiel werden getrennt: Im Spielbereich stehen
Gruppe, eigener Charakterbogen, Würfel und die freigegebene Szene im
Vordergrund, nicht dauerhaft geöffnete Einstellungsformulare.

### Schlanker Kartentisch

Vorhanden seit 0.12.0: private Kartenbilder, Zoom und Verschieben, Zwei-Finger-Zoom,
optionales Quadratraster mit Einrasten, benannte und farbige Spieler-/NPC-/Objektmarker,
serverseitige Bewegungsrechte, gespeicherte Positionen und kurzlebige Pings.
Ziehen ist optional; Zielklick und tastaturbedienbare Positionsfelder sind Alternativen.
Ein Szenenentwurf bleibt privat, bis er ausdrücklich freigegeben und ausgewählt wird.

Kein geplanter Schwerpunkt: Nebel des Krieges, Zeichenwerkzeuge,
Entfernungsmessung und weitere taktische Kartenfunktionen. Der Fokus liegt
auf Erzählung, Bögen, Hinweisen und Spielleitervorbereitung, nicht auf einer
Tabletop-Engine. Keine dynamische Beleuchtung, Wegfindung, Kollisionen oder
regelabhängige Kampfautomatik.

### Aufgeräumte Spieloberfläche

- Teilnehmerleiste mit Spielleitung und Spielern; ausdrücklich freigegebene
  Charakternamen und Porträts ergänzen die Darstellung, ohne private Bögen
  für die ganze Gruppe zu öffnen.
- Eigener Charakterbogen mit lesbaren Attributfeldern und Ressourcenanzeigen.
  Eingaben erst beim bewussten Bearbeiten öffnen.
- Kompakte Würfelablage und bei Bedarf aufklappbares Spielprotokoll statt
  einer langen Seite mit allen Formularen und Ereignissen.
- Optionaler, gespeicherter Zugmarker: Die Spielleitung bestimmt, wer am Zug
  ist. Freies Spiel ohne feste Reihenfolge bleibt möglich.
- Auf dem Handy zwischen „Spiel“, „Mein Bogen“, „Würfel“ und „Gruppe“ wechseln,
  statt eine Desktop-Spaltenansicht lediglich schmaler zu machen.
- Die seit 0.12.0 angebundene Szenenfläche weiterentwickeln; private Entwürfe,
  Rechte und Freigaben beibehalten und keine Spielleitergeheimnisse in
  gemeinsam sichtbaren Beschreibungen ablegen.

### Eigene Hintergründe und Charaktergestaltung

- Spielleiter können eigene Bilder oder Grafiken für den Kampagnenhintergrund
  und die Gestaltung der Charakterbögen verwenden.
- Bildausschnitt, Position und eine abdunkelnde oder aufhellende Auflage
  vorsehen; Texte und Bedienelemente müssen unabhängig vom Bild lesbar bleiben.
- Eine schlichte Darstellung ohne Hintergrundbild anbieten. Kleine,
  passende Bildgrößen und mobile Alternativen vermeiden unnötige Ladezeiten.
- Ein internes Porträtsystem pro Charakter schaffen, unabhängig vom
  Benutzeravatar und ohne verpflichtenden externen Avatar-Dienst.
- Spieler dürfen im Rahmen der Kampagnenrechte eigene Porträts wählen;
  gegebenenfalls gilt die Freigabe durch die Spielleitung.
- Upload- und Auswahlrechte kampagnenbezogen prüfen, Dateitypen und Größen
  begrenzen. Nicht pauschal Zugriff auf die gesamte Website-Mediathek vergeben.
- Öffentlich verwendbare Dekoration von privaten Charakterbildern und
  Spielleitergrafiken unterscheiden. Normale Medien-URLs sind nicht privat;
  vertrauliche Bilder erst mit geschützter Auslieferung verwenden.

### Optionale Charakterentwicklung

- Level beziehungsweise frei benennbare Ränge als optionale Kampagnenregel
  anbieten; nicht jedes Spielsystem verwendet Stufen.
- Zunächst nachvollziehbare Änderungen durch die Spielleitung oder einen
  Freigabeantrag ermöglichen. Erfahrungspunkte und automatische Schwellen
  sind ein späterer Ausbau, keine fest eingebaute Fortschrittskurve.
- Entwicklung im Spielprotokoll dokumentieren; ein höherer Level verändert
  nicht automatisch Attribute, Ressourcen oder andere Regeln.

### Kampagnenbezogene Anwesenheit

- Mitgliedschaft, aktuelle Anwesenheit und „am Zug“ getrennt anzeigen.
  Ein WordPress-Login allein bedeutet nicht, dass jemand am Spieltisch ist.
- Anwesenheit anhand kürzlicher Meldungen aus dem geöffneten Kampagnenbereich
  bestimmen; nach Verbindungsabbruch oder geschlossenem Tab läuft der Status aus.
  Die Anzeige ist eine zeitlich angenäherte Information, keine Echtzeitgarantie.
- Manuell „AFK / kurze Pause“ und „Wieder da“ melden können. Eine optionale
  kurze Notiz wie „Küche“ erklärt der Gruppe die Pause, ist aber keine Pflicht.
  Eingaben begrenzen und als Text ausgeben.
- Solange die Verbindung aktiv ist, bleibt der bewusst gesetzte AFK-Status
  erhalten; Hintergrundaktualisierungen setzen ihn nicht auf „anwesend“ zurück.
  Läuft die Verbindung aus, nicht weiter eine aktive AFK-Verbindung behaupten.
- AFK sichtbar in der Teilnehmerleiste kennzeichnen. Ein aktiver Zug wird
  dadurch nicht automatisch weitergeschaltet; darüber entscheidet die Spielleitung.
- Status auch mit Text kennzeichnen, nicht allein durch farbige Punkte.
- Nur berechtigte Kampagnenmitglieder dürfen den Status ihrer Gruppe lesen;
  Benutzer dürfen ausschließlich ihre eigene Anwesenheit melden.
- Anwesenheitsanzeige optional halten. Wer keinen Status teilt, wird nicht
  fälschlich als offline dargestellt. Keine Bewegungsprofile oder dauerhaften
  Anwesenheitsverläufe sammeln.
- Ohne optionale Chat-Integration und ohne WebSocket-Pflicht funktionieren.
  Häufigkeit der Aktualisierung und Ablaufzeit vor der Umsetzung festlegen.

**Weiterer Ausbau:** Private Charakterporträts und Bogen-Hintergründe sind
seit 0.14.0 geschützt vorhanden; gemeinsame Teilnehmeravatare sowie ein
Bildpositions-/Ausschnitteditor bleiben offen. Bilder für die Spielfläche sind seit 0.9.0
geschützt möglich. Die zentrale Szenenfläche ist seit 0.12.0 angebunden;
Kapitel und Text-/Bild-Handouts sind seit 0.13.0 vorhanden. Das optionale
Levelsystem bleibt ein gesonderter Ausbau.
Das Handbuch beschreibt bereits die erste Spieltisch-Oberfläche.

## Module

| Modul | Aufgabe |
|---|---|
| Core | Installation, Kampagnen, Mitglieder, Rechte und Sitzungen |
| Character System | Charakterbögen, Felder, Attribute und Ressourcen |
| Dice Engine | Würfelwürfe, Modifikatoren und Ergebnisse |
| Story Builder | Kapitel, Szenen und freigegebene Inhalte |
| Game Master | Spielleitung, Freigaben und Kontrolle |
| Inventory | Gegenstände und Ausrüstung |
| Communication | Spielprotokoll und optionale Chat-Anbindung |
| Frontend / Templates | Spielbereich, Charakterbögen und Shortcodes |
| Integrationen | Optionale Anbindungen an PSOURCE-Plugins |

Die Module beschreiben fachliche Bereiche und müssen nicht als separate Plugins
oder als komplizierte technische Architektur umgesetzt werden.

## Aktuelle Priorität: Das Grundgerüst funktional vervollständigen und spielbar machen

Der erste kleine Schritt aus Phase 7 ist seit **0.21.0** umgesetzt. Der
Story-Builder bleibt Grundlage. Seit **0.16.0** stehen
Währungseinstellungen, getrennte oder gemeinsame Guthaben, protokollierte
Vergaben/Abzüge und eingefrorene Gegenstandsbindungen bereit. Seit **0.17.0**
ergänzen strukturierte Gegner/NPCs und bewusst aufgenommene Universumsvorlagen
die Spielwelt. Seit **0.18.0** stehen kontrollierte Beutetabellen auf Grundlage
der vorhandenen Würfelmechanik bereit. Seit **0.19.0** ergänzt eine erste
optionale Wiki-Anbindung das öffentliche Weltlexikon. Seit **0.20.0** ist
kontrollierter, konfigurierbarer Handel vorhanden.
Seit 0.24.0 sind konfigurierbare Soloentscheidungsbelohnungen vorhanden.
Seit 0.25.0 sind mehrere Wertebedingungen mit UND/ODER vorhanden.
Seit 0.26.0 steht der erste versionierte Kampagnenexport bereit.
Seit 0.27.0 steht die private Importvorschau mit frei wählbaren,
berechtigungsgeprüften Zuordnungen bereit. Seit 0.29.0 können die geprüften
Zuordnungen atomar als neue Kampagne oder bestätigter Ersatz gespeichert werden.

Import/Export ist abgeschlossen, nicht der gesamte geplante Funktionsumfang.
Vorhandene Funktionen müssen auch über ihre tatsächlichen Bedienelemente
nutzbar sein. Gemeldete Fehler in grundlegenden Abläufen, etwa der
Universumszuordnung, werden vor darauf aufbauenden Erweiterungen behoben.
Kampagnenanlage, Regelbearbeitung und persönliche Mitgliederverwaltung sind
jetzt auch direkt im Frontend-Spielbereich verfügbar. Die vorhandenen
Erstell- und Spielleiterrechte, Formularschutz und Revisionsprüfungen bleiben
verbindlich; Spieler erhalten dadurch keine Verwaltungsrechte.
Danach folgen Abenteuer-, Regel-/Universums- und Spielwelterweiterungen.
Qualitätsprüfungen begleiten diese Arbeit, ersetzen sie aber nicht.
Das Plugin unterstützt die Spielleitung; eine vollständige Game-Engine
ist weiterhin kein Ziel.

### Reihenfolge der nächsten Schritte

1. **Kampagnenexport und Wiederherstellung — abgeschlossen:** Der versionierte Export steht
   seit 0.26.0 in lokaler und vollständiger Variante bereit; seit 0.27.0
   ergänzt ihn eine private Importvorschau. Seit 0.29.0 stehen Fachdokumentprüfung
   und atomare Wiederherstellung einer einzelnen Kampagne bereit.
   Regeln, Charaktere, Inventare,
   Währung und Guthaben, Kapitel, Szenen, Handouts, NPCs, Beutetabellen,
   Ereignishistorien, Soloabenteuer und Durchläufe sowie geschützte Assets
   berücksichtigen. Konflikte bestehender IDs und Benutzerzuordnungen
   ausdrücklich behandeln; keine stillen Überschreibungen und keine Änderungen
   an anderen Kampagnen. Den Umgang mit gemeinsam genutzten Universumsdaten
   und optionalen Integrationen vor der Wiederherstellung festlegen; keine
   fremden oder nicht zum Export freigegebenen Daten übernehmen.
2. **Vorhandene Kernabläufe tatsächlich bedienbar machen — unmittelbar:**
   Gemeldete Zuordnungs-, Formular- und Navigationsfehler auf der Testseite
   nachvollziehen und beheben. Anlegen, Zuordnen, Bearbeiten, Freigeben und
   Spielen über echte Backend-/Frontend-Bedienelemente prüfen. Direkte
   PHP-Aufrufe und erfolgreiche HTTP-Anfragen allein belegen keine
   durchgängige Bedienbarkeit.
3. **Abenteuer weiter ausbauen — laufender Funktionsblock:** Textvergleiche,
   Voraussetzungen anhand vorheriger Entscheidungen und ausdrückliche
   Spielleiterfreigaben für Einzeldurchläufe sind implementiert und live geprüft.
   Gruppendurchläufe mit wählbaren Entscheidungsregeln, persönlichen Kosten und
   Belohnungen, Teilnahme, Pausen und Wiedereinstieg sind ergänzt; Import/Export
   erhält den gemeinsamen Fortschritt samt Stimmen und Freigaben.
   Noch offen sind Gegenstandsvoraussetzungen, weitere Ereignisarten,
   Begegnungsabläufe.
   Wiederverwendbare Abenteuerpakete von Kampagnensicherungen,
   Teilnehmerkonten und persönlichen Spielständen trennen.
4. **Regeln und Universen flexibilisieren:** Verwendete Feldstrukturen
   kontrolliert ändern, Kampagnenmigration mit Vorschau und Konfliktauflösung,
   Gemeinsame Basisbögen können bereits ausdrücklich unabhängig weitergeführt
   werden. Rückverbindungen und Spielerbearbeitung von Basisbögen mit Freigaben
   ermöglichen. Auswahlfelder, optionale Punkteverteilung
   und konfigurierbare XP-/Level-/Rangregeln ergänzen.
5. **Spielwelt und Integrationen vervollständigen:** Beute auf mehrere
   Empfänger aufteilen, Gegenstandsgruppen und verschachtelte Tabellen,
   weitere Würfelarten, Wiki-Verknüpfungen, geschützte Handoutformate und
   Empfängerkreise sowie Einladungen ergänzen.
6. **Qualität und Plattformprüfung begleitend:** Geänderte Abläufe live auf
   der Testseite und mit gezielten Regressionen prüfen. Unterstützte Versionen
   von WordPress, ClassicPress und PHP mit einer reproduzierbaren Testmatrix
   nachweisen; Betrieb mit/ohne Integrationen sowie Mehrprozess- und
   Zwei-Subsite-Szenarien abdecken. Fehlende Funktionen nicht als erledigt
   markieren, nur weil vorhandene Tests bestanden sind.
7. **Vorhandene Modulschnittstellen direkt mitdokumentieren:** Zuständigkeiten und
   interne PHP-Lese-/Schreibwege für Kampagnen, Universen, Charaktere,
   Ereignisse, Inventar und Story festhalten. Fachliche Änderungen über die
   zuständigen Writer und deren Sperr-/Transaktionsregeln führen. Bestehende
   Schnittstellen gezielt verbessern, statt eine neue API-Schicht auf Verdacht
   oder eine öffentliche REST-API einzuführen.
8. **Barrierefreiheit begleitend prüfen:** Zustände nicht nur über Farben
   vermitteln; Aktionen ohne Drag-and-drop ermöglichen. Fokussteuerung,
   dynamische Statusmeldungen, SVG-Beschriftungen und Bedienung ohne
   Animationen bei jeder betroffenen Oberflächenänderung mitprüfen.

### Entwicklungsdokumentation übersichtlicher trennen

- [ ] Produktziele und Prioritäten in dieser Roadmap bündeln.
- [ ] Technische Architektur mit Datenmodellen, Rechten, Transaktionen und
  vorhandenen Schnittstellen separat dokumentieren.
- [ ] Einen Changelog für tatsächlich veröffentlichte Änderungen führen;
  geplante Funktionen nicht als vorhandenen Funktionsumfang darstellen.

Die Trennung erfolgt schrittweise, ohne einen großen Dokumentations- oder
Refactoring-Umbau zur Voraussetzung für den nächsten nutzbaren Schritt zu machen.
Konzeptbewertungen allein sind kein Nachweis von Produktionsreife.

Seit **0.15.0** verbindet ein privater Abenteuerplan die bestehenden Werkzeuge:

- [x] Kapitel mit geordneter Szenenfolge, aktive Szene, nächste Szene im
  jeweiligen Kapitel und nicht zugeordnete Szenen gemeinsam anzeigen.
- [x] Private Entwürfe, freigegebene und aktive Stände unterscheiden;
  Wechsel nur zur bereits freigegebenen Fassung ermöglichen.
- [x] Geheimnisse und direkt szenenbezogene Mappeneinträge beim Leiten
  bündeln; private Handouts dort ausdrücklich und getrennt freigeben.
- [x] Den Ablauf in der bestehenden Nebelhafen-Demo fortsetzen und im
  bebilderten Handbuch erläutern.

### Abgesicherte Grundlagen und spätere Absicherung

Die erledigten Konsistenzkorrekturen bleiben Grundlage. Weitere Absicherung,
Plattformtests und Schnittstellendokumentation begleiten den Funktionsausbau;
sie stellen die noch fehlenden Kernfunktionen nicht pauschal zurück:

- [x] Lokale Änderungen an vorhandenen Charakterbögen, Freigaben,
  Kampagnenauflagen und Inventaren verwenden dieselbe Datenbanksperre je
  Charakter, bevor sie abhängige Stände lesen. Ein konkurrierender Vorgang
  wird ohne Wartezeit mit ausdrücklicher Konfliktmeldung abgewiesen.
  Regressionstests prüfen beide Änderungsrichtungen, veraltete Metadaten,
  konkurrierende Datenbankverbindungen und nicht verfügbare Sperren.
- [x] Manuelle Mitgliederaufnahme und -entfernung sowie Beitrittsanfragen
  einschließlich Annahme/Ablehnung werden mit Rollen- und Antragsständen
  ausschließlich für die aktuelle Spielleitung protokolliert. Mitgliedschaft,
  Antragsstatus und Ereignis werden gemeinsam gespeichert oder zurückgenommen.
  Verwaltungsformulare prüfen den Revisionsstand; bestehende Zuordnungen
  bleiben ohne nachträglich erfundene Historie erhalten.
- [ ] Einen bedienbaren Wiederherstellungsablauf für unklar bestätigte Vorgänge
  und unvollständige Altbestände schaffen (eigene Verwaltungsoberfläche
  für diese Sonderfälle vorerst zurückgestellt; nicht mit dem priorisierten
  Kampagnenimport verwechseln). Weitere protokollpflichtige
  Verwaltungsänderungen ausdrücklich festlegen.
- [x] Die zentrale Dokument-/Ereignisspeicherung hält Änderungen und Ereignisse
  mit einer gemeinsamen InnoDB-Transaktion zusammen. Ungeeignete Speicherwege
  und fremde Transaktionen werden abgewiesen; unbestätigte Cache-Ergänzungen
  und automatische Wiederverbindungen sind während der Speicherung gesperrt.
  Tests prüfen getrennte Leser, Fehler, Ausnahmen und separate Prozesse, die
  vor beziehungsweise nach dem Commit enden.
- [ ] Mehrparteientransfers und deren Wiederholbarkeit gesondert absichern.
  Die Dokumenttransaktion ist keine atomare Übertragung zwischen mehreren
  Charakteren und deckt keine externen Hook-Nebenwirkungen ab.
- [ ] Echte Mehrprozess- und Zwei-Subsite-Tests sowie die unterstützte
  WordPress-/ClassicPress-/PHP-Matrix ergänzen.
- [x] Seit 0.26.0 versionierten Kampagnenexport mit Regeln, Charakteren,
  Szenen, Inventaren, Guthaben, Handouts, Protokollen, Abenteuerdurchläufen
  und geschützten Assets bereitstellen. Lokale und vollständige Varianten,
  Berechtigungen für benötigte Universumsdaten, ausgewiesene Abhängigkeiten
  und Prüfsumme unterstützen; keine anderen Kampagnen oder unbeteiligten
  Basisbögen mitexportieren. Backend-/Frontend-Downloads sind geprüft.
- [x] Seit 0.27.0 private Importvorschau mit Format-/Prüfsummenprüfung,
  Ziel- und Universumswahl, ausdrücklichen Konten-/Basiszuordnungen,
  Konfliktanzeige, Zielsicherung und Verwerfen bereitstellen. Konten,
  Websitegrenzen, Ablauf, reale Uploads und unveränderte Spielstände prüfen.
- [x] Seit 0.29.0 schreibenden Import und die Wiederherstellung einzelner Kampagnen
  bereitstellen. Fachdokumentprüfung, atomare Speicherung,
  Änderungen zwischen Vorschau und Speicherung sowie Wiederholungsabwehr
  absichern. Benutzer-/Objektzuordnungen, gemeinsame Universumsdaten und
  externe Integrationen kontrolliert behandeln; keine stillen Überschreibungen
  oder erneuten wirtschaftlichen Vergaben. Eine erfolgreiche Wiederherstellung
  und sichere Ablehnung fehlerhafter Dateien nachweisen. Vor einem Ersatz
  private Zielsicherung und alte Datensätze erhalten; wiederholte Bestätigungen
  auch nach späteren Ersetzungen nicht erneut ausführen.
- [x] Seit 0.28.0 vorhandene Fachvalidatoren in die private Importvorschau
  einbinden, Dokumentfehler und verbleibenden Prüfbedarf ausdrücklich zeigen.
  Keine Werte still korrigieren und keine wirtschaftlichen Vorgänge ausführen.
- [ ] Interne Lese-/Schreibschnittstellen und kontrollierte Fach-Hooks
  dokumentieren; Tastatur- und Screenreader-Bedienung weiter prüfen.

Gemeinsamer Basisfortschritt aus einem Universum bleibt ein eigener Vorgang:
Er kann weiterhin eine lokale Ausrüstungsanpassung erfordern. Die Sperre
verspricht weder atomare universumsweite Änderungen noch einen sicheren
Geld- oder Gegenstandstransfer zwischen mehreren Charakteren.

## Phase 0: Grundlagen und Entscheidungen

- Unterstützte Versionen von WordPress, ClassicPress und PHP festlegen und
  anhand einer Testmatrix überprüfen.
- Kernablauf und Datenmodell für mehrere getrennte Kampagnen definieren.
- Berechtigungen und Sichtbarkeit für Kampagnen, Charaktere, Würfe und Inhalte
  festlegen.
- Datenhaltung anhand konkreter Anforderungen entscheiden: Inhaltstypen für
  geeignete redaktionelle Inhalte, eigene Tabellen dort, wo strukturierte
  Werte und Ereignisse sie sinnvoll machen.
- Installations-, Aktualisierungs-, Deinstallations- und Datensicherungskonzept
  festlegen. Nutzerdaten nicht ohne ausdrückliche Entscheidung löschen.
- Früh prüfen, wie Shortcodes, Templates und der klassische Editor auf beiden
  Plattformen funktionieren.

**Ergebnis:** Ein abgestimmtes Fundament und ein klarer Umfang für die erste
spielbare Version.

## Phase 1: Kampagnen und Rechte

- Kampagnen anlegen, bearbeiten und archivieren.
- Kampagnenmitglieder verwalten und Spieler einladen beziehungsweise aufnehmen.
- Website-Administration von kampagnenbezogenen Rollen trennen.
- Spielleiter und Spieler pro Kampagne zuordnen. Eine Person kann in
  verschiedenen Kampagnen unterschiedliche Rollen haben.
- Einen geschützten Kampagnenbereich im Frontend bereitstellen.

**Ergebnis:** Zwei Kampagnen können unabhängig voneinander betrieben werden;
Nichtmitglieder erhalten keinen Zugriff auf geschützte Inhalte.

## Phase 2: Regeln und Charaktere

- Pro Kampagne eine Charakterbogen-Vorlage erstellen und bearbeiten.
- Frei benennbare Felder für Texte, Zahlen, Auswahlmöglichkeiten, Attribute
  und Ressourcen vorsehen; bei Ressourcen aktuelle und maximale Werte
  unterstützen.
- Reihenfolge, Beschreibung, Pflichtangaben und zulässige Werte konfigurieren.
- Attribute mit Startwerten und optionalem Punktepool anlegen.
- Festlegen, ob Spieler Werte selbst verteilen oder ändern dürfen und ob eine
  Freigabe durch den Spielleiter nötig ist.
- Charaktere erstellen, ansehen und nach den Kampagnenregeln bearbeiten;
  Spielleiter können Werte korrigieren.
- Alle Eingaben und Punkteverteilungen serverseitig prüfen.
- Regeländerungen entsprechend den obigen Prioritäten nachvollziehbar machen.
  Umbenennungen von strukturellen Änderungen unterscheiden; bestehende Werte
  und offene Freigaben nicht stillschweigend verändern. Die Sperre verwendeter
  Feldstrukturen erst durch eine geprüfte Übernahme mit Änderungsvorschau ersetzen.

**Beispiel:** Eine Kampagne verteilt 30 Punkte auf vier selbst benannte
Attribute; eine andere nutzt stattdessen eigene Werte und eine Ressource „Wut“.

**Ergebnis:** Unterschiedliche Charakterbögen und Attributsysteme sind pro
Kampagne nutzbar, einschließlich kontrollierter Spieleränderungen.

## Phase 3: Würfel und Spielprotokoll

- W4, W6, W8, W10, W12, W20 und W100 unterstützen.
- Mehrere Würfel und einfache Ausdrücke wie `3W6 + 2` serverseitig auswerten.
- Würfe mit Attributen kombinieren; den verwendeten Wert zum Zeitpunkt des
  Wurfs dokumentieren.
- Öffentliche und private Würfe sowie Würfe des Spielleiters für Spieler
  ermöglichen. Gruppenwürfe sind für Kampagnenmitglieder sichtbar, private
  Würfe für Ausführenden, betroffenen Charakterbesitzer und Spielleiter;
  verdeckte Würfe ausschließlich für Spielleiter. Die erlaubten Varianten
  wählt der Spielleiter pro Kampagne.
- Einzelwürfe, Modifikatoren und Ergebnis nachvollziehbar im Spielprotokoll
  speichern.
- Das Wurfprotokoll zu einem gemeinsamen Ereignisprotokoll für Charakter-,
  Freigabe- und Regeländerungen ausbauen; Sichtbarkeit pro Ereignis erhalten.
- Eingaben und Umfang von Würfelwürfen begrenzen und ungültige Ausdrücke
  verständlich ablehnen.

**Ergebnis:** Die Gruppe kann Würfe sicher ausführen und entsprechend ihrer
Berechtigung nachvollziehen.

## Phase 4: Abenteuer und Szenen

**Seit 0.15.0:** Ein privater Abenteuerplan verbindet Kapitelreihenfolge,
Szenenstatus, ausdrückliche Szenenwechsel und szenenbezogene Spielleitungsnotizen
mit der gezielten Handoutfreigabe. Kein automatischer Szenenwechsel,
keine versteckte Veröffentlichung verknüpfter Inhalte.

- Kampagnen in Kapitel und Szenen gliedern.
- Beschreibungen und geeignete Medien einbinden.
- Szenen vorbereiten und gezielt für Spieler freigeben.
- Spielervorschau, geschützte Entwürfe für Überarbeitungen und bewusstes
  Zurückziehen veröffentlichter Szenen vorsehen.
- Spielleiternotizen und Geheimnisse getrennt von sichtbaren Inhalten halten.
- Szenenfreigaben im Spielprotokoll festhalten.

**Ergebnis – erste spielbare Version:** Ein Spielleiter legt eine Kampagne
und deren Regeln an, nimmt Spieler auf, prüft Charaktere und gibt eine Szene
frei. Die Gruppe kann die Szene ansehen und würfeln.

## Phase 5: PSOURCE-Integrationen

### PS Chat

PS Chat ist über ergänzte Zugangs- und Moderationsschnittstellen als
optionale Chatbox in den Spieltisch integriert. Die Runde entspricht derzeit
den eingetragenen Kampagnenmitgliedern, nicht einer separaten Sitzungsanmeldung.
Eine Übertragung freigegebener Gruppenwürfe ist noch nicht vorhanden.
PS Chat übernimmt Nachrichten und Moderation; das
Pen-&-Paper-Plugin ergänzt die grafische Einbettung und die Zuordnung der
Rundenteilnehmer.

- Die Spielleitung ist immer Moderator des zugehörigen Chatraums. Dafür die
  vorhandene PS-Chat-Moderation verwenden, kein zweites Moderationssystem bauen
  und keine pauschalen websiteweiten Moderationsrechte vergeben.
- Ausschließlich die für diese Runde angemeldeten Spieler als Teilnehmer
  hinzufügen, nicht sämtliche Website-Benutzer. Die Spielleitung erhält
  zusätzlich ihren Moderatorzugang.
- Änderungen an Rundenanmeldung und Spielleitung mit Raumzugang und
  Moderatorzuordnung abgleichen; entzogene Berechtigungen müssen auch im
  Chat wirksam werden. AFK oder vorübergehend offline zu sein beendet die
  Rundenmitgliedschaft nicht.
- Am Desktop eine kompakte Chatbox in einer Bildschirmecke vorsehen, ähnlich
  einem MMO-Chat: einklappbar, mit Hinweis auf ungelesene Nachrichten und ohne
  Charakterbogen, Würfelablage oder wichtige Bedienelemente zu verdecken.
- Auf Handy und Tablet ein passend großes, bewusst geöffnetes Panel statt
  eines dauerhaft über dem Spiel liegenden Chatfensters anbieten. Die
  Bildschirmtastatur berücksichtigen; Fokus beim Öffnen und Schließen führen
  und neue Nachrichten nicht ständig für Screenreader dazwischenrufen lassen.
- Vor der Umsetzung vorhandene PS-Chat-Schnittstellen für Einbettung,
  Teilnehmer und Moderatoren prüfen und verwenden, statt Nachrichtenverwaltung
  oder ein zweites Chatsystem nachzubauen.
- Vorher prüfen, ob und wie die Rundenmitgliedschaft bei **jedem**
  Chat-Zugriff durchgesetzt werden kann, auch bei direktem Abruf oder Senden.
  Eine geschützte Einbettungsseite oder ein schwer erratbarer Raumname allein
  genügt nicht.
- Private Würfe niemals in einen für andere sichtbaren Chat übertragen.
- Würfelergebnisse bleiben im Pen-&-Paper-Protokoll die maßgebliche Quelle;
  Chat-Nachrichten sind eine optionale Anzeige, kein Ersatz für echte Würfe.
- Ist PS Chat nicht verfügbar, bleibt das Grundsystem nutzbar.

### PS Community: Charakterprofil und optionale Ausrüstung

**Grundlage seit 0.14.0, Währung und Bindung seit 0.16.0, Handel seit 0.20.0:**

- Ein eigener Profil-Tab „Pen & Paper“ bündelt die eigenen Charaktere mit
  ausdrücklicher Auswahl des Kampagnenkontexts. Charakterbearbeitung verwendet
  weiterhin dieselben Besitzerrechte und Freigaben wie der Spielbereich.
- Profilbesucher und Freunde erhalten nicht automatisch Zugriff auf Bögen,
  Inventare oder private Spielinhalte. Freundschaften ersetzen keine
  Kampagnenmitgliedschaft oder Handelsberechtigung.
- Inventar, Ausrüstung, Guthaben und Handel setzen aktives PS Community mit den
  benötigten Schnittstellen und Modulen voraus. Verfügbarkeit auf jeder
  relevanten Lese- und Schreibschnittstelle prüfen, nicht nur Menüs ausblenden.
  Ohne die Integration bleiben Charaktere, Würfel, Szenen und Handouts nutzbar.
- Bei Deaktivierung Daten erhalten und Bestandsänderungen sperren. Noch offene
  Handelsvorgänge dürfen keine unvollständigen Übertragungen auslösen.
- Charakterporträts und grundlegende Bogengestaltung bleiben unabhängig von
  PS Community im Pen-&-Paper-Kern. Benutzeravatar und Charakterporträt sind
  getrennte Bilder.

Der eigene Profil-Tab, geschützte Inventare, Slotwahl und automatische additive
Boni sind vorhanden. Charakterbearbeitung verwendet dieselben Freigaben wie
der Spielbereich. Profilformulare benötigen keine Backend-Anmeldung.
Bei inaktiver Community werden keine Ausrüstungsboni angewandt; gespeicherte
Würfe behalten ihre damaligen Werte. Direkte und native Community-AJAX-Aufrufe
fremder Profilbereiche geben keine Inventare aus.

Universumswährung, getrennte oder gemeinsame Guthaben sowie handelbar
beziehungsweise charaktergebunden gekennzeichnete Exemplare sind seit
0.16.0 vorhanden. **Seit 0.20.0 vorhanden:** revisionsgeschützte Handelsangebote. Beteiligte
stimmen ausdrücklich zu; eine zusätzliche Spielleiterfreigabe ist einstellbar
und standardmäßig erforderlich. Handelsgrundregeln gehören zum Universum;
Kampagnen ohne Universum besitzen eine eigene Einstellung. Universumsregeln
werden standardmäßig übernommen; eine ausdrücklich lokale Wirtschaftszuordnung
erhält stattdessen die Kampagnenregeln. Zunächst ist nur
Handel derselben Kampagne zwischen unterschiedlichen Besitzern möglich.
Freundschaften dürfen höchstens die Auswahl unterstützen, nicht Kampagnen-
oder Handelsrechte ersetzen. Außerdem fehlen Entzug/Entfernen von Exemplaren,
Mengen und ein grafischer, frei positionierbarer Slotdesigner.
Die weiteren Grundlagen stehen in Phase 6, vorbereitete automatische
Abenteuerabläufe in Phase 7.

### PS Wiki

**Erster öffentlicher Ausbau seit 0.19.0.** PS Wiki liefert native Artikel,
Kategorien, Suche, Frontend-Bearbeitung und Revisionen. Es ist derzeit kein
privates Kampagnen-Wiki. Die Integration verwaltet ausschließlich separat
gewählte öffentliche Titel/Texte; Spielregeln und Werte bleiben im Kern.

- [ ] Wiki-Seiten mit einer Kampagne, einem Charakterfeld oder einer Szene
  verknüpfen, damit die passenden Erläuterungen schnell erreichbar sind.
- [x] Pro Universum bewusst ein Lexikon aktivieren. Zusätzlich vorhandene
  native Wiki-Veröffentlichungs- und Kategorienrechte verlangen; danach
  ausschließlich die Lexikonpflege an die Universumsleitung delegieren.
  Keine pauschalen Wiki-Rechte vergeben.
- [x] Einen separat gewählten öffentlichen Hauptkategorienamen und sechs
  benennbare Unterkategorien anlegen: Personen, Gegner, Orte, Fraktionen,
  Gegenstände und Regeln. Vor sofort öffentlich sichtbaren Namen warnen.
- [x] Bestätigte Universumsvorlagen stabil mit Artikelentwürfen verbinden,
  daneben freie Welt-/Regelartikel erlauben. Keine zweite Zuordnung bei
  Umbenennung oder erneutem Angebot derselben Vorlage.
- [x] Titel/Text ausdrücklich separat eingeben und Entwurf von bestätigter
  Veröffentlichung trennen. Keine automatischen Kopien privater Beschreibungen,
  Bilder, Geheimnisse, Werte, Eigenschaften oder Beutedaten.
- [x] Bestehende öffentliche Fassungen beim Vorbereiten von Änderungen
  erhalten. Direkt im Wiki redaktionell geänderte Artikel nicht überschreiben;
  diese werden vorerst dort weitergepflegt, ohne automatischen Abgleich.
- [x] Kategorien, native Artikel, Zuordnungen und Pflichtprotokolle atomar
  speichern. Wiki-Benachrichtigungen erst nach erfolgreichem Commit.
- [x] Anbindung pausieren und Zuordnungen bei fehlendem Plugin erhalten.
  Pausieren oder Deaktivieren löscht keine öffentlichen Inhalte.
- [ ] Freie zusätzliche Kategorieebenen, umfangreichere Vorlage-Assistenten
  und einen ausdrücklich geprüften Wiederabgleich redaktioneller Änderungen.
- [ ] Private oder kampagnenspezifische Wikis erst nach einer durchgängigen
  Leserechte-Schnittstelle in PS Wiki. Kategorien und Links sind kein Schutz.

**Ergebnis:** Geprüfte Integrationen ergänzen das Spiel, ohne das Kernsystem
oder seine Berechtigungen von ihnen abhängig zu machen.

## Phase 6: Spielwelt und Ausrüstung

Gegenstandskataloge, geschützte Grafiken, freie Eigenschaften, Seltenheiten,
konkrete Exemplare, konfigurierte Slots und additive Ausrüstungsboni sind
seit 0.14.0 umgesetzt. Die erste Oberfläche arbeitet mit Auswahlfeldern,
nicht mit einem grafischen Papierpuppen- oder Drag-and-drop-Editor.
Bestände sind auf 100 einzelne Exemplare pro Charakter begrenzt.
Grundwerte bleiben unverändert; unzulässige kombinierte Werte werden
ausdrücklich gemeldet und lassen sich durch Ablegen reparieren.

- Seit 0.13.0: private Personen-, Orts- und Notizkarten mit Kapitel- und
  Szenenverknüpfungen. Seit 0.17.0 ergänzen davon getrennte strukturierte
  NPCs und Gegner mit freien Eigenschaften und Werten die Spielleitermappe.
- Begegnungen, Gegner und Ereignisse für Szenen vorbereiten.
- Gegenstandsvorlagen in einem gemeinsamen Universumskatalog sowie ergänzend
  kampagneneigenen Katalogen verwalten. Kampagnen ohne Universum verwenden
  ausschließlich ihren eigenen Katalog. Gemeinsame Vorlagen erlauben keinen
  automatischen Handel zwischen Kampagnen.
- Die Spielleitung erstellt Gegenstände mit Name, Beschreibung, geschützter
  Grafik und frei definierbaren Eigenschaften beziehungsweise Zahlenwerten.
  Vorlagen, konkrete Inventargegenstände und angelegte Gegenstände unterscheiden;
  Vorlagenänderungen überschreiben ausgegebene Exemplare nicht ungefragt.
- Gegenstandstypen, etwa Kopfbedeckung oder Werkzeug, von frei benennbaren
  Seltenheitsstufen wie „Normal“ oder „Episch“ trennen. Die Spielleitung legt
  Bezeichnungen und Darstellungsfarben fest; Farben sind nicht die einzige
  Kennzeichnung. Keine fest eingebaute Seltenheitsskala oder automatische
  Wertevervielfachung durch die Seltenheitsstufe.
- Frei benennbare Ausrüstungsslots auf Charakterbögen konfigurieren, etwa
  Kopf, Arme, Brust oder eigene Begriffe. Gegenstandstypen bestimmen passende
  Slots. Inventare bleiben an Charakter und Spielkontext gebunden.
- Angelegte Gegenstände dürfen ausdrücklich konfigurierte automatische Boni
  auf Charakterwerte geben. Bonusregeln, belegte Slots und tatsächlicher Besitz
  werden serverseitig geprüft; Ausrüsten und Ablegen dürfen Grundwerte nicht
  dauerhaft überschreiben. Darstellung und Würfe müssen dieselben wirksamen
  Werte verwenden; alte Würfe behalten ihre gespeicherten Werte.
- Inventar, Ausrüstung und Handel sind PS-Community-gebundene Erweiterungen,
  keine Voraussetzung für das erzählerische Grundspiel.
- Seit 0.13.0: Text- und Bild-Handouts gezielt für die Runde freigeben und
  zurücknehmen, mit getrenntem Entwurfsstand und privaten Geheimnissen.
- Über ein geeignetes Schutzkonzept für private Dateien entscheiden: Normale
  Upload-URLs können direkt erreichbar sein und sind nicht automatisch privat.

### Währung und Gegenstandsbindung seit 0.16.0; Handel seit 0.20.0

**Währung, Guthaben, Bindung und erster kontrollierter Handel implementiert.**
Die Universumsleitung beziehungsweise Administration wählt in den Einstellungen
getrennte Kampagnenkonten oder ein gemeinsames Konto am Universumscharakter.
Kampagnen ohne Universum konfigurieren ihre Währung lokal.

- [x] Frei benennbare Währung mit Kürzel, ausschließlich ganze Einheiten.
  Kontostände von 0 bis 999999999, keine Nachkommastellen oder Überziehung;
  keine feste Gold-/Silber-/Kupfer-Währung und keine Echtgeldanbindung.
- [x] Beträge mit Begründung ausdrücklich vergeben oder abziehen und
  gemeinsam mit dem Ereignis speichern. Getrennte Konten verwaltet die
  Kampagnenleitung, gemeinsame Konten nur die Universumsleitung beziehungsweise
  Administration. Wiederholte veraltete Formulare buchen nicht erneut.
- [x] Das Kontomodell bleibt nach der ersten Buchung fest. Konten werden bei
  einer anderen Universumszuordnung nicht umgedeutet. Bestehende lokale Konten
  können bei einer ausdrücklich lokalen Wirtschaftszuordnung unverändert
  weitergeführt werden. Währungsumrechnungen und Übertragungen bestehender
  Universumskonten benötigen weiterhin eine gesonderte Migration.
- [x] Vorlagen als **handelbar** oder **charaktergebunden** kennzeichnen und
  die Eigenschaft im ausgegebenen Exemplar einfrieren. Vorlagenänderungen
  verändern keine bereits ausgegebenen Bindungen.

**Handel seit 0.20.0:**

- [x] Geschenk, Verkauf und Tausch unabhängig aktivieren; standardmäßig keine
  Art erlauben und zusätzliche Spielleiterfreigabe verlangen. Universumsregeln
  standardmäßig übernehmen oder bei ausdrücklich lokaler Wirtschaftszuordnung
  die Kampagnenregeln behalten.
- [x] Verschenken, Tauschen sowie Kaufen und Verkaufen mit vereinbartem Betrag
  ermöglichen. Beide Besitzer müssen den konkreten Bedingungen zustimmen;
  unbekannte Gegenleistungen dürfen nicht automatisch als angenommen gelten.
- [x] Handelsangebote reservieren die angebotenen Exemplare, nicht Geld. Besitz, Bindung,
  Guthaben, Reservierung, Mitgliedschaft und aktuelle Rechte serverseitig
  prüfen. Ein Angebot öffnet nicht das gesamte fremde Inventar.
- [x] Gegenstände und gegebenenfalls Geld gemeinsam übertragen oder gar nicht.
  Gleichzeitige Annahmen, wiederholte Anfragen und Neuladen dürfen weder
  Gegenstände vervielfachen noch Guthaben doppelt abbuchen oder auszahlen.
  Negative Guthaben nicht durch einen unvollständigen Handel erzeugen.
- [x] Ablehnen, Zurückziehen und Spielleiterentscheidungen nachvollziehbar
  behandeln. Bei fehlendem PS Community keine wirtschaftlichen Änderungen
  oder unvollständigen Übertragungen ausführen; vorhandene Daten erhalten.

**Erste Grenze:** Je ein Exemplar pro Seite innerhalb derselben Kampagne,
zwischen unterschiedlichen Besitzern. UUID und eingefrorener Stand bleiben
erhalten; auch offene Ausrüstungsanträge werden angepasst, nichts wird
automatisch angelegt. Regeländerungen sperren den Abschluss alter Angebote,
nicht deren Zurückziehen/Ablehnen. Gemeinsame Basisguthaben sind bei Verkäufen
ausdrücklich eingebunden, ohne neue lokale Ersatzkonten zu erzeugen.

**Noch offen:** Pakete, Mengen, reine Geldgeschenke, kampagnenübergreifende
Übertragungen und mögliche spätere lokale Abweichungen von Universumsregeln.
Gemeinsame Kataloge oder Guthaben allein erlauben keine Übertragung.

### Gegner, NPCs und Universumsvorlagen seit 0.17.0

- [x] Gegner und NPCs pro Kampagne mit Name, Beschreibung, geschütztem Bild,
  frei definierten Eigenschaften, Werten und getrennten Geheimnissen erstellen.
  Die vorhandenen Personenkarten bleiben erzählerische Notizen und werden
  nicht ungefragt in ein festes Gegner- oder Kampfsystem umgewandelt.
- [x] Szenen zuordnen und Figuren in der privaten Regie des Abenteuerplans
  anzeigen. Spieler erhalten dadurch keinen Zugriff auf Figuren oder Werte.
- [x] Über **Ins Universum aufnehmen** bewusst eine wiederverwendbare Vorlage
  erstellen beziehungsweise zuordnen. Die Universumsleitung entscheidet
  über einen privaten Vorschlag, falls der Ersteller diese Berechtigung
  nicht besitzt. Wiederholte Angebote und Umbenennungen erzeugen keine Duplikate.
- [x] Universumsvorlagen als unabhängige Kampagnenfiguren mit eingefrorenem
  Herkunftsstand und eigener geschützter Bildkopie übernehmen. Lokale
  Verletzungen und Ressourcen verändern weder Vorlage noch andere Figuren.
  Vorlagenänderungen werden nicht automatisch synchronisiert.
- [x] Private Figurenhistorie und atomare Speicherung mit Ereignis.
  Gegnerverwaltung funktioniert ohne PS Community oder PS Wiki.
- [x] Optionale lokale Beutetabellen an Gegnern/NPCs verknüpfen.
- [ ] Begegnungsabläufe ergänzen. Keine fest vorgeschriebenen Attribute,
  Gegnerklassen oder Kampfabläufe.
- [x] Freigegebene Lexikonbeschreibungen über die optionale PS-Wiki-Anbindung
  zugänglich machen; geheime Werte und Beuteinformationen bleiben geschützt.

### Würfelbasierte Beutetabellen seit 0.18.0

- [x] Kampagnenbezogene Tabellen mit einem erlaubten Standardwürfel und
  bis zu 30 frei definierten Ergebnisbereichen. Jeden Würfelwert genau einmal
  abdecken; Lücken und Überschneidungen werden abgelehnt. Chancen als
  Bereichsgröße geteilt durch Würfelgröße anzeigen.
- [x] Je Ausgang keine Beute, ein Gegenstandstyp, Währung oder beides.
  Mengen und Beträge als ganze Zahlen oder erlaubte Würfelausdrücke wie
  `2W6`; alle möglichen Ergebnisse müssen innerhalb der Grenzen liegen.
  Konkrete Exemplare erhalten eigene Identitäten und eingefrorene Bindungen.
- [x] Die Spielleitung wählt bewusst Anlass und Tabelle.
  Den Wurf einschließlich verwendetem Tabellenstand und ermittelter Beute
  speichern; Neuladen und dieselbe POST-Anfrage würfeln nicht erneut.
- [x] Zunächst nur einen Vorschlag anzeigen. Einen Empfänger für die gesamte
  Beute und die Vergabe ausdrücklich bestätigen. Bestehende Kontorechte gelten.
  Bestände, Beträge, Vergabezustand und Protokolle gemeinsam speichern oder
  zurücknehmen. Derselbe Vorgang kann nicht zweimal auszahlen.
- [x] Tabellen, Chancen, Anlässe und Vorschläge privat halten.
  Besitzer sehen lediglich tatsächliche Gegenstands- und Guthabenänderungen.
- [ ] Gegenstandsgruppen, Aufteilung auf mehrere Empfänger und ausdrücklich
  verwaltete Universums-Beutekataloge ergänzen.
- [ ] Begrenzte Verweise auf weitere Tabellen ohne Zyklen ergänzen.
- [ ] Weitere Würfelarten wie `W3` gesondert unterstützen; derzeit sind nur
  die vorhandenen Standardwürfel zulässig.
- [x] Seit 0.24.0 automatische Vergabe im ausdrücklich freigegebenen
  Soloabenteuerablauf ermöglichen, mit eingefrorenem Tabellenstand,
  ausreichender Kapazität vor dem Wurf und einmaligem Vergabenachweis.

### Geplante Umsetzungsreihenfolge

1. **Seit 0.16.0 umgesetzt:** Einstellbare Geltungsbereiche, passende
   Buchungsrechte, Währung, Guthaben und eingefrorene Gegenstandsbindung.
2. **Gegner-/NPC-Vorlagen seit 0.17.0 und erste kontrollierte Beutetabellen
   seit 0.18.0 umgesetzt.** Gruppen, verschachtelte Tabellen und Aufteilung
   bleiben spätere Erweiterungen.
3. **Seit 0.19.0 umgesetzt:** Optionale öffentliche PS-Wiki-Anbindung mit
   geschützter Verwaltung, stabiler Zuordnung und bewusst veröffentlichten Texten.
4. **Seit 0.20.0 umgesetzt:** Kontrollierter Handel mit konfigurierbaren Arten,
   verbindlicher Besitzerzustimmung und optionaler Spielleiterfreigabe.
5. **Soloausbau bis 0.25.0 vorhanden:** Mindestwerte, Ressourcenkosten,
   Belohnungen und UND-/ODER-Wertebedingungen. Kampagnenimport/-export ist
   seit 0.29.0 vorhanden, die funktionale Abenteuerplanung aber noch nicht fertig.
6. Als nächsten Funktionsblock Begegnungen sowie weitere Solo-, Gruppen- und
   hybride Abläufe ausbauen; Entscheidungsverfahren und Teilnahmeverhalten
   je Abenteuer konfigurierbar machen.

## Phase 7: Geleitete, hybride und automatisierte Abenteuer

**Erster Solo-Schritt seit 0.21.0 umgesetzt.** Die bestehende Spielleitung
behält die Kontrolle über Vorbereitung und Freigabe. Mindestwerte und
optionale Ressourcenkosten ergänzen den Ablauf seit 0.22.0 beziehungsweise
0.23.0; seit 0.24.0 folgen konfigurierbare einmalige Belohnungen.
Hybride Freigabeschritte und gemeinsame Gruppendurchläufe mit konfigurierbaren
Entscheidungen, Teilnahme und Wiedereinstieg sind ergänzt. Weitere Voraussetzungen,
Begegnungen und Abenteuerpakete bleiben Funktionsausbau;
sie werden nicht pauschal hinter eine abschließende Stabilisierungsphase zurückgestellt.

- [x] Pro Charakter einen eigenen Durchlauf aus einem ausdrücklich
  freigegebenen Szenenstand speichern; der Durchlauf friert Szenen und
  Entscheidungen samt Regelrevision ein. Die Gruppenszene bleibt unverändert;
  Charakterwerte ändern sich nur durch ausdrücklich konfigurierte Kosten.
- [x] Entscheidungen und optionale serverseitige Würfel-/Attributproben
  auswerten, den gewählten Pfad revisionsgeschützt protokollieren und
  wiederholte oder veraltete Aktionen ablehnen.
- [x] Neue Durchläufe pausieren oder wieder erlauben, ohne laufende Durchläufe
  oder historische Würfe umzuschreiben.
- [x] Seit 0.22.0 eine optionale Mindestwert-Voraussetzung je Entscheidung
  für ein Zahlen- oder Attributfeld anbieten. Aktuelle freigegebene Werte
  einschließlich wirksamer Ausrüstung prüfen; unerfüllte Entscheidungen
  sichtbar sperren. Voraussetzung und tatsächlichen Wert im privaten Verlauf
  speichern, ohne Charakterwerte zu verändern.
- [x] Seit 0.23.0 eine optionale Ressource mit ganzzahligen Kosten je
  Entscheidung anbieten. Zeitpunkt je Entscheidung wählen: jeden Versuch
  bezahlen oder nur bei Erfolg; ohne Probe immer. Ausreichenden freigegebenen
  Bestand vor jedem Versuch prüfen, Untergrenzen erhalten und Verbrauch,
  Fortschritt sowie private Pflichtereignisse gemeinsam speichern.
  Kostenpflichtige Versuche vorläufig auf lokale Bögen und unabhängige Kopien
  ohne offenen Werteantrag begrenzen; kostenfreie Alternativen bleiben möglich.
- [x] Teamdurchläufe mit eigenem gemeinsamen Fortschritt, frei wählbarer
  Einzelentscheidung, Mehrheit, Einstimmigkeit oder Spielleiterfreigabe anbieten.
  Echte Besitzer führen persönliche Aktionen aus; Teilnahmewechsel, Pausen und
  Wiedereinstieg erhalten den Verlauf und verwerfen offene Vorschläge.
  Import/Export bewahrt Teilnehmer, Stimmen und aktuelle/historische Freigaben,
  ohne Kosten oder Belohnungen erneut auszuführen.
- [x] Seit 0.24.0 feste Gegenstände/Währung und vorhandene Beutetabellen als
  optionale Alternativen je Soloentscheidung anbieten. Auch Misserfolg belohnen
  oder nur Erfolg; ohne Probe immer. Vorlagen und Tabellen einfrieren,
  ausreichende Inventar-/Guthabenkapazität vor dem Würfeln prüfen und Kosten,
  Vergaben sowie Fortschritt gemeinsam speichern. Bestehende Vergaberechte,
  Community-Bindung und private Protokolle erhalten; keine erneute Auszahlung.
- [x] Seit 0.25.0 bis zu 20 Zahlen-/Attribut-/Ressourcenbedingungen je
  Entscheidung mit UND oder ODER anbieten. Mindestens, größer, höchstens,
  kleiner, gleich und ungleich unterstützen; Ressourcenwert und Maximum
  unterscheiden. Aktuelle freigegebene Werte prüfen, offene Anträge nicht
  verwenden und alle Vergleichsergebnisse im privaten Verlauf einfrieren.
- [x] Textbedingungen mit gleich/ungleich und enthält/enthält nicht ergänzen.
- [x] Vorherige Entscheidungen im eigenen Solo- oder Gruppendurchlauf als
  Bedingungen prüfen; persönliche Nachweise nicht an fremde Teilnehmer ausliefern.
- [x] Hybride Abläufe mit ausdrücklich notwendigen Spielleiterfreigaben ergänzen.
  Anfrage/Vorschlag und Zustimmung verbrauchen nichts; der echte Besitzer führt
  nach erneuter Rechteprüfung aus. Entzogene Freigaben lassen sich zurücknehmen
  und durch eine aktuelle Entscheidung der Spielleitung ersetzen.
- [ ] Gegenstandsbedingungen, weitere Ereignisse und gesetzte Abenteuerzustände ergänzen.

Abenteuerautor und ausführende Spielleitung sind getrennte Zuständigkeiten.
Vorbereitete Abenteuer können von anderen Personen übernommen und ohne
Beteiligung des ursprünglichen Autors gespielt werden. Die verantwortliche
Administration beziehungsweise Spielleitung wählt den Modus innerhalb ihrer Rechte:

| Modus | Ablauf |
|---|---|
| Geleitet | Die Spielleitung erzählt, entscheidet und vergibt Belohnungen. |
| Hybrid | Vorbereitete Abschnitte laufen automatisch; festgelegte Entscheidungen benötigen die Spielleitung. |
| Automatisiert | Spieler durchlaufen ausdrücklich vorbereitete Abenteuer solo oder gemeinsam, auch während die Spielleitung abwesend ist. |

- Den vorhandenen kleinen Szenen- und Entscheidungsablauf ausbauen: Die
  Spielleitung definiert Auswahlmöglichkeiten, optionale Proben,
  Erfolgs-/Misserfolgsfolgen und erlaubte Übergänge zwischen freigegebenen
  Szenen. Mindestwerte stehen seit 0.22.0 bereit; mehrere Wertebedingungen mit
  UND/ODER seit 0.25.0. Text- und Fortschrittsbedingungen sind ergänzt;
  Gegenstandsbedingungen folgen später.
- Vorbereitete Komplikationen statt automatisch erfundener Ergebnisse
  verwenden. Beispiel: Ein Schloss untersuchen, eine Verstandprobe ausführen
  und je Ergebnis eine freigegebene Folgeszene öffnen.
- Eigene Abenteuer-Spielstände je Solodurchlauf beziehungsweise Gruppe
  führen. Der Fortschritt einer Gruppe darf keine andere Gruppe oder die
  gemeinsame Kampagnenszene ungefragt verändern.
- Für Teams ausdrücklich festlegen, wer Entscheidungen trifft, welche
  Figuren würfeln und wie Teilnahme, Wiedereinstieg sowie gemeinsame
  Belohnungen behandelt werden. Keine stillschweigende Mehrheits- oder
  Gruppenführerregel voraussetzen.
- Persönliche Charakterressourcen vom gemeinsamen Abenteuerfortschritt getrennt
  führen. Teilnehmerwechsel, Pausen und Wiedereinstieg dürfen den gemeinsamen
  Fortschritt nicht löschen; Entscheidungsverfahren und Umgang mit abwesenden
  Teilnehmern werden je Abenteuer konfiguriert.
- Voraussetzungen, Ressourcenverbrauch, Würfe, Fortschritt und Übergänge
  auf dem Server prüfen. Parallele Aktionen und wiederholte Anfragen dürfen
  keinen mehrfachen Verbrauch, übersprungene Voraussetzungen oder doppelte
  Belohnungen auslösen.
- Die Spielleitung legt fest, welche Belohnungen wann, wie oft und an wen
  automatisch vergeben werden dürfen. Keine unbegrenzten Geld- oder
  Gegenstandsquellen durch Wiederholen eines Abschnitts schaffen.
- Übernahme von Gegenständen, Währung und Fortschritt zwischen automatisierten
  und geleiteten Kampagnen nur nach ausdrücklich festgelegten Regeln und
  Berechtigungsprüfungen ermöglichen.
- Bei fehlenden Integrationen oder unvollständigen Änderungen keine
  erfolgreiche Ressourcen- oder Belohnungsvergabe vortäuschen.
  Wirtschaftliche Funktionen bleiben an PS Community gebunden; ein
  erzählerischer Ablauf ohne solche Funktionen benötigt es nicht.
- Spielleiterkorrekturen und bei Bedarf Pausieren/Freigeben des Ablaufs
  vorsehen, ohne historische Würfe oder Vergaben umzuschreiben.
- Keine automatische Kampf-Engine, Echtzeitpflicht oder KI-Abhängigkeit
  voraussetzen. Der Schwerpunkt bleibt ein systemoffener Pen-and-Paper-
  Baukasten, kein MMORPG und keine vollständige Tabletop-Engine.

### Wiederverwendbare, eigenständig spielbare Abenteuerpakete

- [ ] Versionierte Pakete aus Szenen, Entscheidungen, Voraussetzungen,
  möglichen Folgen und benötigten Regel-/Vorlagendaten anbieten.
  Abenteuerdefinitionen von persönlichen Spielständen und Teilnehmerkonten trennen.
- [ ] Autoren wählen ausdrücklich, ob und für wen ein Paket bereitgestellt
  wird. Geheimnisse für automatische Abläufe geschützt behalten; keine
  privaten Kampagnenprotokolle, Konten oder laufenden Durchläufe mitveröffentlichen.
- [ ] Übernommene Pakete als unabhängige, kontrolliert anpassbare Kopien verwenden.
  Solo- und Gruppendurchläufe benötigen den ursprünglichen Autor nicht.
  Hybride Pakete benötigen eine ausdrücklich zuständige örtliche Spielleitung.
- [ ] Paketversionen und laufende Durchläufe getrennt halten. Änderungen am
  Original dürfen laufende Abenteuer nicht ungefragt verändern.
- [ ] „Pen & Paper“ und „Abenteuer“ als unterschiedliche Bedienbereiche
  derselben Regel-, Charakter-, Rechte- und Speicherbasis ausbauen, nicht
  als zwei konkurrierende Engines. Keine Flowchart-, Echtzeit- oder KI-Pflicht.

Diese Erweiterung gehört zum Abenteuerausbau. Kampagnenimport/-export ist
die Speichergrundlage, ersetzt aber kein eigenständiges Abenteuerpaket.

## Phase 8: Mögliche spätere Erweiterungen

Diese weiterführenden Ideen sind nicht alle Voraussetzung für das geplante
Grundgerüst. Die ausdrücklich priorisierten Abenteuer-, Regel- und
Spielwelterweiterungen werden dadurch nicht wieder zurückgestellt:

- Einen universellen Katalog für Regelwerke, Charakterbögen, NPCs,
  Gegenstände, Szenen, Abenteuer und Beutetabellen schrittweise ausbauen.
  Vorlagen bewusst als anpassbare Kopien übernehmen, nicht automatisch
  synchronisieren. Versionierter Export und kontrollierter Import bilden
  dafür die Grundlage.
- Erweiterte Würfelmechaniken wie Erfolgswürfe, Würfelpools oder explodierende
  Würfel als optionale Regeln.
- Komfortfunktionen für den Vergleich von Regelversionen und die gebündelte
  Übernahme umfangreicher Änderungen. Die grundlegende Absicherung und
  Nachvollziehbarkeit von Regeländerungen gehört bereits in die frühen Phasen.
- Optionales Level- oder Rangsystem, Erfahrungspunkte und weitere
  Freigabeabläufe entsprechend dem gewählten Kampagnenregelwerk.
- Sitzungsnotizen, Terminübersicht und Zusammenfassungen vergangener Sitzungen.
- Import und Export eigener Kampagnen- oder Regelvorlagen mit klaren Optionen
  für private Inhalte und personenbezogene Daten.
- Druckansicht oder PDF-Export für Charakterbögen und Spielleiternotizen.
- Optionale Benachrichtigungen bei Einladungen, Freigaben und neuen Szenen.
- Den seit 0.12.0 vorhandenen Kartentisch mit Markern bei Bedarf um einen
  einfachen Initiative-Tracker ergänzen, ohne eine vollständige virtuelle
  Spieltisch-Engine vorauszusetzen.

## Übergreifende Qualitätsanforderungen

- Rechte auf jeder relevanten Lese- und Schreibschnittstelle prüfen, auch bei
  direkten URLs, Suche, Feeds, REST- und AJAX-Anfragen.
- Kampagnendaten konsequent voneinander trennen; private Würfe und
  Spielleitergeheimnisse nicht an Unberechtigte ausliefern.
- Daten validieren, Ausgaben passend absichern und Änderungen gegen
  unberechtigte Anfragen schützen.
- Konflikte bei gleichzeitigen Änderungen erkennbar behandeln, statt Werte
  unbemerkt zu überschreiben.
- Mobil nutzbare, barrierearme und übersetzbare Oberflächen anstreben.
- Funktionen auf den festgelegten WordPress- und ClassicPress-Versionen testen,
  einschließlich Betrieb mit und ohne optionale Integrationen.

## Konfiguration und verbleibende Umsetzungsaufgaben

Fachliche Entscheidungen treffen Administration, Spielleitung und Abenteuerautoren
innerhalb ihrer Rechte im Plugin. Die Entwicklung stellt die entsprechenden
Optionen bereit, statt eine einzige globale Spielweise festzulegen.

1. Welche Versionen von WordPress, ClassicPress und PHP sollen mindestens
   unterstützt werden?
2. Administratoren und ausdrücklich berechtigte Benutzer dürfen Kampagnen
   erstellen. Aufnahme und Beitrittsanfragen sind vorhanden; konfigurierbare
   Einladungen fehlen noch.
3. Charakterfreigaben und Würfelsichtbarkeit sind bereits pro Kampagne
   konfigurierbar. Weitere Bearbeitungs-/Freigaberechte für Basisbögen ergänzen.
4. Wie sollen Änderungen an bereits verwendeten Feldern und Regeln bestehende
   Charaktere und offene Freigaben beeinflussen?
5. Welche Wiki- und Chat-Zugriffsrechte sind bereits vorhanden, und wo werden
   gegebenenfalls Erweiterungen der anderen Plugins benötigt?
6. Welche weiteren Handoutformate und Empfängerkreise werden neben den
   geschützten Text-/Bild-Handouts für die gesamte Runde benötigt?
7. Handelsarten und zusätzliche Freigaben sind konfigurierbar. Bei der
   Universumszuordnung wählt die zuständige Person Universumseinstellungen
   oder ausdrücklich beibehaltene lokale Währungs-/Handelsregeln.
8. **Seit 0.16.0 entschieden:** Die Universumsleitung wählt getrennte
   Kampagnenkonten oder gemeinsame Universumscharakterkonten. Erstere
   verwaltet die Kampagnenleitung, letztere die Universumsleitung;
   Administration bleibt berechtigt. Das Modell ist nach Erstbuchung fest.
9. Welche Übertragungen von Gegenständen und Währung zwischen Kampagnen
   sind zulässig, insbesondere bei automatisierten Abenteuerdurchläufen?
10. Wie werden Entscheidungen, Proben, Wiedereinstieg und Belohnungen in
    automatisch spielenden Gruppen geregelt?
