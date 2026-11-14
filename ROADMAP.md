# Roadmap: PS Pen & Paper

## Vision

PS Pen & Paper wird ein universeller Baukasten für Pen-and-Paper-Rollenspiele
auf WordPress und ClassicPress. Spielleiter erschaffen eigene Kampagnen, Regeln,
Charakterbögen und Abenteuer. Das Plugin schreibt weder eine Spielwelt noch ein
bestimmtes Regelsystem vor.

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
  Handel und ein grafischer Slotdesigner sind noch nicht vorhanden.

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
- Geschichten, Teilnehmer, Chat und Protokolle bleiben kampagnengetrennt.
  Universumsbesitz oder Bogenbesitz gewährt keine zusätzliche Teilnahme.

### Weitere strukturelle Schritte

- Kontrollierte Migration bestehender Kampagnen mit Vorschau, eindeutiger
  Zuordnung und Konfliktauflösung; keine stillschweigende Zusammenführung.
- Kontrollierter Wechsel zwischen gemeinsamem Stand und Kopie sowie Ablösen
  gemeinsamer Bögen. Solange das fehlt, sperren gemeinsame Bögen einen
  Universumswechsel beziehungsweise das Lösen der Zuordnung.
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

## Prioritäten für die nächsten Entwicklungsschritte

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

## Aktuelle Priorität: Stabilisierung

Vor Handel, Währung und automatisierten Abenteuern werden die vorhandenen
Schreibwege und ihre Konsistenz abgesichert:

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
  und unvollständige Altbestände schaffen. Weitere protokollpflichtige
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
- [ ] Vollständigen, versionierten Kampagnenexport mit Regeln, Charakteren,
  Szenen und geschützten Assets sowie die Wiederherstellung einzelner
  Kampagnen vorziehen. Universumsbezüge und Benutzerzuordnungen ausdrücklich
  behandeln; keine fremden oder nicht freigegebenen Daten mitexportieren.
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

**Grundlage seit 0.14.0 implementiert; Währung, Bindung und Handel sind geplant:**

- Ein eigener Profil-Tab „Pen & Paper“ bündelt die eigenen Charaktere mit
  ausdrücklicher Auswahl des Kampagnenkontexts. Charakterbearbeitung verwendet
  weiterhin dieselben Besitzerrechte und Freigaben wie der Spielbereich.
- Profilbesucher und Freunde erhalten nicht automatisch Zugriff auf Bögen,
  Inventare oder private Spielinhalte. Freundschaften ersetzen keine
  Kampagnenmitgliedschaft oder Handelsberechtigung.
- Inventar, Ausrüstung und Handel setzen aktives PS Community mit den
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

**Geplanter Ausbau:** Universumswährung, handelbare beziehungsweise
charaktergebundene Exemplare und revisionssichere Handelsangebote. Beteiligte
stimmen ausdrücklich zu; eine zusätzliche Spielleiterfreigabe ist einstellbar
und standardmäßig erforderlich. Handelsgrundregeln gehören zum Universum;
Kampagnen ohne Universum benötigen eine eigene Einstellung. Vererbung,
Abweichungsrechte und kampagnenübergreifender Handel sind noch festzulegen.
Freundschaften dürfen höchstens die Auswahl unterstützen, nicht Kampagnen-
oder Handelsrechte ersetzen. Außerdem fehlen Entzug/Entfernen von Exemplaren,
Mengen und ein grafischer, frei positionierbarer Slotdesigner.
Die weiteren Grundlagen stehen in Phase 6, vorbereitete automatische
Abenteuerabläufe in Phase 7.

### PS Wiki

PS Wiki eignet sich als optionales **Regel- und Weltlexikon**: Es bietet laut
Dokumentation unter anderem Frontend-Bearbeitung, Kategorien, Suche und einen
Verlauf mit Revisionen. Denkbare Inhalte sind Hausregeln, Beschreibungen von
Attributen, Orte, Fraktionen, Gegenstände und bekannte Figuren.

- Wiki-Seiten mit einer Kampagne, einem Charakterfeld oder einer Szene
  verknüpfen, damit die passenden Erläuterungen schnell erreichbar sind.
- Universen automatisch eindeutig mit ihrem Lexikon verknüpfen. Nach
  ausdrücklicher Freigabe der Wiki-Anbindung Kategorien und Unterkategorien
  für beispielsweise Personen, Fraktionen, Gegner, Gegenstände und Orte
  anlegen; Bezeichnungen und Gliederung bleiben konfigurierbar.
- Kampagnen-NPCs und Gegner nach bewusster Aufnahme ins Universum als
  wiederverwendbare Vorlagen mit Wiki-Artikeln verbinden. Stabile Zuordnungen
  verhindern Duplikate bei Umbenennung oder erneuter Synchronisierung.
- Automatische Artikelentwürfe von bewusster Veröffentlichung trennen.
  Bereits private Universums- und Kategorienamen dürfen nicht ungefragt
  öffentlich werden. Redaktionelle Ergänzungen nicht still überschreiben.
- Freigegebene Beschreibungen von geheimen Identitäten, Gegnerwerten,
  Beutetabellen und Lootchancen trennen. Mechanische Daten bleiben im
  Pen-&-Paper-Plugin; Wiki-Artikel sind keine zweite Regel-Datenquelle.
- Klar trennen: **PS Wiki erklärt Regeln in Textform; die überprüfbaren
  Spielregeln und Charakterwerte liegen im Pen-&-Paper-Plugin.** Eine Änderung
  eines Wiki-Artikels verändert nicht automatisch die Spielmechanik.
- Vor einer privaten oder kampagnenspezifischen Nutzung prüfen, ob PS Wiki die
  notwendigen Lese- und Bearbeitungsrechte auf allen Zugriffswegen unterstützt.
  Kategorien oder Links allein schützen keine Geheimnisse.
- Ist PS Wiki nicht verfügbar, bleibt die Kampagne spielbar; Wiki-Verknüpfungen
  sind dann nicht nutzbar.

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
  Szenenverknüpfungen. Strukturierte NPC-Eigenschaften bleiben ein späterer Ausbau.
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

### Geplant: Universumswährung, Gegenstandsbindung und Handel

**Noch nicht implementiert.** Die seit 0.14.0 vorhandenen Inventare und
Ausrüstungsboni sind dafür nur die Grundlage.

- Pro Universum eine frei benennbare Spielwährung mit Kürzel festlegen.
  Keine feste Gold-/Silber-/Kupfer-Währung oder Echtgeldanbindung voraussetzen.
  Stückelung, Genauigkeit und Betragsgrenzen vor der Umsetzung bestimmen.
- Die berechtigte Spielleitung kann Beträge ausdrücklich vergeben und
  Korrekturen nachvollziehbar protokollieren. Vergaberechte müssen zum
  Geltungsbereich des Guthabens passen; eine Kampagnenleitung erhält nicht
  automatisch Zugriff auf die gesamte Universumswirtschaft.
- Gegenstandsvorlagen als **handelbar** oder **charaktergebunden** kennzeichnen.
  Die Eigenschaft beim Ausgeben im konkreten Exemplar festhalten.
  Vorlagenänderungen dürfen bestehende Bindungen nicht still verändern.
  Den Umgang mit bereits vorhandenen Exemplaren ausdrücklich festlegen.
- Verschenken, Tauschen sowie Kaufen und Verkaufen mit vereinbartem Betrag
  ermöglichen. Beide Besitzer müssen den konkreten Bedingungen zustimmen;
  unbekannte Gegenleistungen dürfen nicht automatisch als angenommen gelten.
- Handelsangebote reservieren die angebotenen Exemplare. Besitz, Bindung,
  Guthaben, Reservierung, Mitgliedschaft und aktuelle Rechte serverseitig
  prüfen. Ein Angebot öffnet nicht das gesamte fremde Inventar.
- Gegenstände und gegebenenfalls Geld gemeinsam übertragen oder gar nicht.
  Gleichzeitige Annahmen, wiederholte Anfragen und Neuladen dürfen weder
  Gegenstände vervielfachen noch Guthaben doppelt abbuchen oder auszahlen.
  Negative Guthaben nicht durch einen unvollständigen Handel erzeugen.
- Ablehnen, Zurückziehen und Spielleiterentscheidungen nachvollziehbar
  behandeln. Bei fehlendem PS Community keine wirtschaftlichen Änderungen
  oder unvollständigen Übertragungen ausführen; vorhandene Daten erhalten.

**Noch offen:** getrennte Guthaben je Kampagnencharakter oder gemeinsames
Guthaben je Universumscharakter; erlaubte Abweichungen von Universumsregeln;
Handel nur innerhalb einer Kampagne oder auch zwischen Kampagnen desselben
Universums. Gemeinsame Kataloge allein erlauben keine Übertragung.

### Geplant: Strukturierte Gegner, NPCs und Universumsvorlagen

- Gegner und NPCs pro Kampagne mit Name, Beschreibung, geschütztem Bild,
  frei definierten Eigenschaften, Werten und getrennten Geheimnissen erstellen.
  Die vorhandenen Personenkarten bleiben erzählerische Notizen und werden
  nicht ungefragt in ein festes Gegner- oder Kampfsystem umgewandelt.
- Über **Ins Universum aufnehmen** bewusst eine wiederverwendbare Vorlage
  erstellen beziehungsweise zuordnen. Die Universumsleitung entscheidet
  über die Aufnahme, falls der Ersteller diese Berechtigung nicht besitzt.
- Vorlagen von konkreten Figuren und Begegnungen unterscheiden. Lokale
  Verletzungen, Ressourcen oder andere Zustandsänderungen verändern nicht
  alle Exemplare oder die Universumsvorlage.
- Gegnern, NPCs und Begegnungen optionale Beutetabellen zuordnen.
  Keine fest vorgeschriebenen Attribute, Gegnerklassen oder Kampfabläufe.
- Freigegebene Lexikonbeschreibungen über die optionale PS-Wiki-Anbindung
  zugänglich machen; geheime Werte und Beuteinformationen bleiben geschützt.
  Gegnerverwaltung und Spielregeln funktionieren auch ohne PS Wiki.

### Geplant: Würfelbasierte Beutetabellen

- Die bestehende serverseitige Würfelmechanik für frei definierte
  Ergebnisbereiche verwenden, etwa W100 mit unterschiedlichen Beuteausgängen.
- Je Ergebnis keine Beute, bestimmte Gegenstände, Auswahlen aus einer
  Gegenstandsgruppe, Währungsbeträge oder Kombinationen davon vorsehen.
  Mengen und Beträge können feste Werte oder begrenzte Würfelausdrücke wie
  `1W3` beziehungsweise `2W6` sein.
- Verweise auf weitere Beutetabellen ermöglichen, ohne unbegrenzte
  Verschachtelung oder zyklische Aufrufe zuzulassen. Bereichsüberschneidungen,
  Lücken und die Bedeutung von Chancen vor der Ausführung eindeutig prüfen.
- Die Spielleitung wählt zunächst bewusst den Anlass und die Tabelle.
  Den Wurf einschließlich verwendetem Tabellenstand und ermittelter Beute
  speichern; Neuladen zeigt denselben Vorgang und würfelt nicht erneut.
- Beute zunächst als Vorschlag anzeigen. Empfänger und Vergabe bestätigt
  die Spielleitung; konkrete Exemplare übernehmen ihre Gegenstandsbindung.
  Währungsvergaben verwenden dieselben geprüften Guthabenregeln wie manuelle
  Vergaben.
- Eine Belohnung darf pro vorgesehenem Vorgang nur einmal vergeben werden.
  Bestände, Beträge und Vergabezustand gemeinsam und nachvollziehbar sichern;
  Wiederholungsanfragen dürfen keine zweite Auszahlung erzeugen.
- Automatische Vergabe erst im ausdrücklich dafür freigegebenen
  Abenteuerablauf erlauben. Verdeckte Beuteinformationen nicht über Wiki,
  Spielerprotokolle oder Vorschauen verraten.

### Geplante Umsetzungsreihenfolge

1. Geltungsbereiche und Rechte der Universumswirtschaft klären;
   Währung, Guthaben und Gegenstandsbindung ergänzen.
2. Strukturierte Gegner-/NPC-Vorlagen und kontrollierte Beutetabellen
   auf Grundlage der vorhandenen Würfelmechanik aufbauen.
3. Die optionale PS-Wiki-Anbindung mit geschützter Zuordnung und bewusst
   veröffentlichten Lexikoninhalten ergänzen.
4. Darauf aufbauend sicheren Handel mit optionaler Spielleiterfreigabe umsetzen.
5. Erst anschließend vorbereitete Solo-/Gruppenabenteuer automatisieren.

## Phase 7: Geleitete, hybride und automatisierte Abenteuer

**Konzeptionell vorgemerkt, noch nicht implementiert.** Dies ist ein eigener
größerer Ausbau nach zuverlässigen Fortschritts- und Belohnungsgrundlagen,
kein bereits vorhandener Automatikmodus.

Die Spielleitung bleibt Abenteuerautor und bestimmt je Kampagne den Modus:

| Modus | Ablauf |
|---|---|
| Geleitet | Die Spielleitung erzählt, entscheidet und vergibt Belohnungen. |
| Hybrid | Vorbereitete Abschnitte laufen automatisch; festgelegte Entscheidungen benötigen die Spielleitung. |
| Automatisiert | Spieler durchlaufen ausdrücklich vorbereitete Abenteuer solo oder gemeinsam, auch während die Spielleitung abwesend ist. |

- Mit einem kleinen Szenen- und Entscheidungsablauf beginnen: Die
  Spielleitung definiert Texte, Auswahlmöglichkeiten, Voraussetzungen,
  Proben, Erfolgs-/Misserfolgsfolgen und erlaubte Übergänge.
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

## Phase 8: Mögliche spätere Erweiterungen

Diese Ideen sind bewusst noch nicht Teil der ersten spielbaren Version:

- Kampagnen- und Charakterbogen-Vorlagen kopieren und zwischen eigenen
  Kampagnen übernehmen.
- Erweiterte Würfelmechaniken wie Erfolgswürfe, Würfelpools oder explodierende
  Würfel als optionale Regeln.
- Komfortfunktionen für den Vergleich von Regelversionen und die gebündelte
  Übernahme umfangreicher Änderungen. Die grundlegende Absicherung und
  Nachvollziehbarkeit von Regeländerungen gehört bereits in die frühen Phasen.
- Optionales Level- oder Rangsystem, Erfahrungspunkte und weitere
  Freigabeabläufe entsprechend dem gewählten Kampagnenregelwerk.
- Sitzungsnotizen, Terminübersicht und Zusammenfassungen vergangener Sitzungen.
- Gruppeninventar mit eigenen Zugriffs- und Vergaberechten; der geplante
  Handel zwischen Charakteren wird bereits in Phase 6 behandelt.
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

## Offene Entscheidungen

1. Welche Versionen von WordPress, ClassicPress und PHP sollen mindestens
   unterstützt werden?
2. Administratoren und ausdrücklich berechtigte Benutzer dürfen Kampagnen
   erstellen. Wie sollen Einladungen und die Aufnahme neuer Spieler ablaufen?
3. Welche konkreten Änderungen an Charakteren benötigen eine Freigabe, und
   wer darf private Würfe sehen?
4. Wie sollen Änderungen an bereits verwendeten Feldern und Regeln bestehende
   Charaktere und offene Freigaben beeinflussen?
5. Welche Wiki- und Chat-Zugriffsrechte sind bereits vorhanden, und wo werden
   gegebenenfalls Erweiterungen der anderen Plugins benötigt?
6. Welche weiteren Handoutformate und Empfängerkreise werden neben den
   geschützten Text-/Bild-Handouts für die gesamte Runde benötigt?
7. Wie gelten Universums-Handelsregeln in Kampagnen: verbindliche Vererbung,
   ausschließlich strengere lokale Regeln oder frei erlaubte Abweichungen?
8. Bleiben Guthaben kampagnenbezogen oder werden sie je Universumscharakter
   geteilt? Welche Leitungen dürfen dann Beträge vergeben und korrigieren?
9. Welche Übertragungen von Gegenständen und Währung zwischen Kampagnen
   sind zulässig, insbesondere bei automatisierten Abenteuerdurchläufen?
10. Wie werden Entscheidungen, Proben, Wiedereinstieg und Belohnungen in
    automatisch spielenden Gruppen geregelt?
