# PS Pen & Paper – Pluginhandbuch

Stand: Version 0.26.0 · 15. November 2026.

Dieses Handbuch beschreibt den vorhandenen Funktionsumfang, nicht die gesamte
Roadmap. Die Abbildungen zur Demorunde stammen aus der tatsächlich angelegten
[Demokampagne „Die letzte Fracht von Nebelhafen“](demokampagne.md).
Ihre Welt, Namen und Demoregeln sind Beispiele, keine Vorgaben des Plugins.
Die Universums- und Kartenabbildungen zeigen getrennte, anschließend entfernte Prüfwelten.
Die Wiki-Abbildungen zeigen ein separat erhaltenes Lexikon-Demouniversum.
Eine fremde Partnerprogramm-Debugleiste wurde nur für diese Aufnahmen ausgeblendet.

## Inhalt

1. [Ist das schon spielbar?](#1-ist-das-schon-spielbar)
2. [Installation und Spielbereich](#2-installation-und-spielbereich)
3. [Kampagnen und Mitglieder](#3-kampagnen-und-mitglieder)
4. [Regeln und Charakterfelder](#4-regeln-und-charakterfelder)
5. [Charaktere und Freigaben](#5-charaktere-und-freigaben)
6. [Würfeln](#6-würfeln)
7. [Ressourcen verwalten](#7-ressourcen-verwalten)
8. [Spielprotokoll lesen](#8-spielprotokoll-lesen)
9. [Farben anpassen](#9-farben-anpassen)
10. [Handy und Tablet](#10-handy-und-tablet)
11. [Probleme und Grenzen](#11-probleme-und-grenzen)
12. [Szenen und Kartentisch](#12-szenen-und-kartentisch)
13. [Spielleitermappe, Kapitel und Handouts](#13-spielleitermappe-kapitel-und-handouts)
14. [Porträts und Bogenhintergründe](#14-porträts-und-bogenhintergründe)
15. [Community-Profil, Gegenstände und Ausrüstung](#15-community-profil-gegenstände-und-ausrüstung)
16. [Währung, Guthaben und Gegenstandsbindung](#16-währung-guthaben-und-gegenstandsbindung)
17. [Gegner, NPCs und Universumsvorlagen](#17-gegner-npcs-und-universumsvorlagen)
18. [Beutetabellen und bestätigte Vergabe](#18-beutetabellen-und-bestätigte-vergabe)
19. [Öffentliches Weltlexikon mit PS Wiki](#19-öffentliches-weltlexikon-mit-ps-wiki)
20. [Geschenke, Verkauf und Tausch](#20-geschenke-verkauf-und-tausch)
21. [Soloabenteuer](#21-soloabenteuer)
22. [Kampagne sichern](#22-kampagne-sichern)

## 1. Ist das schon spielbar?

**Ja: Der erste vollständige Ablauf von Szenenvorbereitung und Freigabe bis zum
gemeinsamen Kartentisch ist vorhanden.** Ein umfangreicher Abenteuereditor ist
das noch nicht; Kapitel, eine private Spielleitermappe und Text-/Bild-Handouts
sind seit 0.13.0 enthalten.

| Bereits nutzbar | Noch nicht vorhanden |
|---|---|
| Getrennte Kampagnen, Mitgliedschaften und Kapitel mit Szenenfolge | Sitzungsplanung und öffentliche Zusammenfassungen |
| Szenenentwürfe, getrennte Geheimnisse, NPCs und geordnete Begegnungsphasen | Automatische Kampf-Engine |
| Private Inhaltsvorschau und geschützter Kartentisch | Nebel des Krieges und Zeichenwerkzeuge |
| Frei benennbare Charakterfelder, Basisbögen und Freigaben | Grafischer, frei positionierbarer Slotdesigner |
| Würfelsummen, optionale Erfolgspools, höchster/niedrigster Einzelwürfel und geschützte Ergebnisse | Explodierende Würfel und Regelskripte |
| Änderungs- und Wurfprotokoll, Auswahlfelder, Attributpunktepool und feldweise Bearbeitungsrechte | Ressourcenauflagen je Kampagne |
| Spielleitermappe, geschützte Text-/Bild-/PDF-Handouts mit ausgewählten Empfängern | Weitere Dateiformate |
| Private Porträts sowie Community-gebundene Inventare, Ausrüstung und kontrollierter Handel | Stapelmengen und automatische Gegenstandssynchronisierung |
| Solo-, hybride und gemeinsame Gruppendurchläufe, Zugang, Wiederholungsregeln und optionale Erfahrungs-/Stufenschwellen | Automatische Klassenentwicklung |
| Privater, versionierter Kampagnenexport und bestätigte Wiederherstellung neuer/bestehender Kampagnen | Erweiterte Plattform- und Wiederherstellungstestmatrix |

Die Spielleitung kann Szenentexte und Karten vorbereiten und gezielt anzeigen.
Die Kampagnenbeschreibung eignet sich weiter für eine gemeinsame Einleitung,
**nicht für Geheimnisse**. Dafür gibt es das getrennte Geheimnisfeld der Szene.
PS Chat lässt sich mit den ergänzten Spieltisch-Schnittstellen geschützt
einbetten. Seit 0.19.0 ergänzt PS Wiki ein ausdrücklich freigegebenes
öffentliches Weltlexikon; private Kampagnen-Wikis sind nicht enthalten.

## 2. Installation und Spielbereich

1. Plugin auf der gewünschten Website aktivieren. In einem Netzwerk auf die
   richtige Unterwebsite achten.
2. Eine normale Seite anlegen und genau einmal `[ps_pen_paper]` einfügen.
3. Optional die Seitenvorlage **„PS Pen & Paper – Spielbereich“** auswählen,
   wenn kein Theme-Layout verwendet werden soll.
4. Die Seite veröffentlichen und angemeldet öffnen.
5. Bei Seiten-Caches oder CDNs die Seite **einschließlich ihrer
   Abfrageparameter vom Caching ausschließen**.

Auf unserer Entwicklungswebsite besteht der
[Spielbereich](http://ps-dev.local/pen-paper/spielbereich/) bereits.
Das Plugin legt eine solche Seite nicht automatisch an.

Standardmäßig erhalten Gäste eine Anmeldeaufforderung. Die Spielleitung kann
einen separaten Teaser ausdrücklich freigeben; der Spieltisch bleibt trotzdem
geschützt. Eine öffentliche Spielseite macht interne Kampagnendaten nicht öffentlich.

![Spielertisch mit Gruppe, Kampagnenbeschreibung und eigenem Charakterbogen](images/09-spieltisch.png)

*Ausschnitt aus Maras echter Spieleransicht. Zusätzliche Einstellungen für
Spielleiter sind dort nicht eingeblendet.*

### Der Spieltisch

Der Einstieg ist die **Game-Lounge**. Bei mehreren Universen wählst du eine Welt
und öffnest deren Kampagnen. **Meine Kampagnen und laufenden Spiele · alle
Universen** bleibt unabhängig von dieser Auswahl sichtbar.

- **Meine Charaktere** öffnet dein Charakterprofil.
- **Weltwiki öffnen** erscheint, wenn ein Weltlexikon verknüpft ist.
- An den Kampagnen findest du **Story / Einleitung**, **Regelwerk**, **Handouts**
  und **Spielverlauf**.
- **Spielmodus wählen** zeigt nur die von der Leitung angebotenen Spielarten:
  **Mit Spielleiter**, **Solo-Abenteuer** und **Automatisches Gruppenabenteuer**.
  Automatische Abenteuer müssen für den jeweiligen Modus veröffentlicht sein.
- Öffne eine bestehende Runde oder lege einen **Wartebereich** an, wenn du dazu
  berechtigt bist. Wähle dort deinen eigenen freigegebenen Universumscharakter.
  **Spiel starten** beginnt anschließend das Abenteuer; ein leerer Wartebereich
  ist noch kein laufendes Spiel.
- **Einladungen** kannst du annehmen oder ablehnen. Vor der Annahme erhältst du
  keine internen Kampagnendaten.
- **Kampagne verlassen** beendet nach Bestätigung deine Mitgliedschaft und
  aktiven Rundenteilnahmen. Charaktere und bisheriger Verlauf bleiben erhalten.
  Eine einzelne neue Runde verlässt du dort über **Runde verlassen**.

Die Universumsleitung kann unter **Welt vorbereiten → Universum bearbeiten**
eine optionale **Welteinleitung für die Game-Lounge** eintragen. Diese ist für
alle angemeldeten Personen sichtbar, denen die Welt angeboten wird. Geheimnisse
gehören ausschließlich in die getrennte **Interne Weltbeschreibung**.
Die Lounge übernimmt die optionalen Farben des ausgewählten Universums.

Unter **Spielarten anbieten** stellt die Leitung die angebotenen Modi ein und
erlaubt oder verbietet neue automatische Runden durch Spieler. Geleitete Runden
startet die Spielleitung; Solo spielt der Besitzer des gewählten Charakters.
Eine automatische Gruppe organisiert ihr Gastgeber, ohne dadurch Charaktere,
Regeln oder Geheimnisse verwalten zu dürfen. Jeder Teilnehmer kann eine
angebotene Abenteuerentscheidung direkt ausführen.

Die vorbereitete Kampagne bleibt eine Vorlage. Jede Runde erhält ihren eigenen
Spielstand, ihre Szenen, Karten, Spielzüge und Protokolle. Werte, Ressourcen und
Inventar deiner Figur bleiben mit ihrem Universumsbogen verbunden; beim Beitritt
entstehen keine Gegenstandskopien. Ein Charakter kann nur an einer laufenden
Runde gleichzeitig teilnehmen. Während einer Pause können Spieler beitreten,
austreten oder einen anderen passenden Charakter auswählen. Das Abschließen
einer Runde ersetzt nicht den tatsächlichen Erfolg eines unvollendeten Abenteuers.

Bereits vorhandene Spiele werden nicht automatisch umgebaut. Bei der
Wiederherstellung einer Rundensicherung werden frühere Rundenverknüpfungen
archiviert: Die importierte Kampagne wird nicht automatisch als laufende Runde
einer möglicherweise fremden Vorlage aktiviert.

Die kompakte Gruppenleiste steht oberhalb der großen Spielfläche.
„Mein Bogen“ öffnet den gegliederten Charakterbogen mit Profil, Attributen
und Ressourcen. Spielleiter sehen unter „Bögen“ auch die Bögen ihrer Spieler
und offene Anträge. Würfel, Regelwerk und Spielprotokoll öffnen eigene
Bereiche; „Bogen öffnen / bearbeiten“ führt zum privaten Detailbogen.
Das eigentliche Eingabeformular wird dort erst über „Bogen bearbeiten“ geöffnet.

Die Spielleitung bestimmt unter „Wer ist am Zug?“ einen Teilnehmer oder
„Freies Spiel“. Der Zugmarker wird für die Gruppe aktualisiert und im
Protokoll festgehalten. Er ist keine automatische Initiativeberechnung.

### Anwesenheit und kurze Pausen

- „Anwesenheit teilen“ freiwillig aktivieren. Sonst steht dort „Nicht gemeldet“.
- Für eine Pause eine optionale kurze Notiz wie „Küche“ eingeben und
  „Kurze Pause / AFK“ drücken.
- Mit „Wieder da“ zur aktiven Anwesenheit zurückkehren.
- „Status nicht mehr teilen“ beendet die Anzeige.

Die Aktualisierung erfolgt bei sichtbarer Seite alle 30 Sekunden. Mehr als
zwei Minuten ohne Meldung bedeutet „Offline“, nicht zwingend eine Abmeldung.
AFK wird durch Hintergrundmeldungen nicht aufgehoben und überspringt keinen
Zug. Es gibt keine Anwesenheitshistorie; aktuelle Meldungen laufen nach
höchstens einem Tag aus. Verbindungs- und Browserpausen können die Anzeige
verzögern.

### Rundenchat

„Rundenchat“ öffnet die Chatbox in der Bildschirmecke. Nachrichten und
Moderation stammen aus PS Chat; Spielleiter sind Moderatoren ihres Raums.
Zugang haben nur aktuelle Kampagnenmitglieder sowie Website-Administratoren.
Eine getrennte Anmeldung zu einzelnen Sitzungen gibt es noch nicht.

![Echte Spielernachricht im eingebetteten PS Chat](images/11-rundenchat.png)

Erforderlich ist PS Chat mit den im
[technischen Überblick](../README.md#optionaler-rundenchat-mit-ps-chat)
beschriebenen Spieltisch-Schnittstellen. Ohne passende Integration erscheint
ein Hinweis. Chatverlauf und Spielprotokoll sind getrennt; Würfelergebnisse
werden nicht automatisch als Nachrichten übertragen.

## 3. Kampagnen und Mitglieder

Unter **Pen & Paper → Kampagnen** im Backend oder unter **Kampagnen**
im Frontend-Spielbereich:

1. Als Website-Administrator eine Kampagne erstellen oder einem Benutzer die
   Berechtigung **„Kampagnen erstellen“** geben.
2. Name und gemeinsame Beschreibung speichern.
3. Bereits vorhandene Mitglieder dieser Website als **Spieler** oder
   **Spielleiter** aufnehmen.
4. Regelwerk einrichten, bevor die ersten Charaktere entstehen.

Im Frontend führt die Anlage direkt zum **Regelwerk** der neuen Runde.
Unter **Rundeneinstellungen** kann die Spielleitung Name, Beschreibung
und Archivstatus speichern sowie vorhandene Website-Benutzer als Spieler
oder Spielleiter hinzufügen und entfernen. Das **Regelwerk** am Spieltisch
ist für die Spielleitung bearbeitbar, für Spieler weiterhin nur lesbar.
Speichern führt zurück in den jeweiligen Frontend-Bereich; ein Backend-Zugang
ist für diese Vorbereitung nicht erforderlich.

Die Kampagnenrolle ist nicht dieselbe wie die WordPress-Benutzerrolle.
Ein Benutzer kann in einer Kampagne Spielleiter und in einer anderen Spieler
sein. Der Ersteller bleibt Spielleiter und kann nicht entfernt werden.
Administratoren können alle Kampagnen verwalten.
Netzwerk-Superadmins sehen und verwalten auch ohne Mitgliedschaft auf der
jeweiligen Unterwebsite sämtliche dortigen Kampagnen, Bögen, Protokolle und Chats.

Aufnahme und Entfernung erscheinen jetzt mit damaligem Anzeigenamen,
Benutzerkennung und Rolle im Spielprotokoll – ausschließlich für aktuelle
Spielleiter und Administratoren. Veraltete Mitgliederformulare werden mit
einer Konfliktmeldung abgewiesen; dann neu laden und den aktuellen Stand prüfen.
Scheitert die zugehörige Protokollierung, bleibt die Mitgliedschaft unverändert.
Frühere Mitgliedschaftsänderungen werden nicht nachträglich eingetragen.

### Übersichtliche Verwaltungsbereiche

Im Backend unter **Pen & Paper** und auf der Startseite des Frontend-Spielbereichs:

- **Übersicht:** Orientierung und Erklärung des Einstiegs.
- **Universen:** Welten, Regelvorlagen und Basisbögen verwalten; Kampagnen zuordnen.
- **Kampagnen:** Runde wählen oder mit Erstellberechtigung im Backend und Frontend anlegen.
- **Charakterverwaltung:** eigene und als Spielleitung verwaltbare Bögen finden.
- **Einstellungen:** verwaltbare Runde wählen; Zugang, Anfragen, Bilder und Farben
  direkt im Frontend ändern. Globale Erstellberechtigungen bleiben im Backend.

### Universum anlegen und Kampagnen zuordnen

Neue Kampagnen benötigen immer ein vorhandenes Universum: zuerst unter
**Universen** die Welt erstellen, danach im Kampagnenformular diese Welt wählen.
Ohne verwaltbares Universum führt der Einstieg direkt zur Welterstellung.
Ältere, unzugeordnete Kampagnen werden nicht automatisch verändert; ihre
Zuordnung erfolgt bewusst über die folgende Vorschau.

1. Als Administrator oder mit der Erstellberechtigung unter **Universen**
   einen Namen und eine interne Weltbeschreibung speichern.
2. Unter **Kampagne zuordnen oder lösen** die eigene Kampagne und das
   Zieluniversum wählen. **Ohne Universum** löst eine vorhandene Zuordnung.
   Unter **Währung und Handelsregeln** wählen, ob die Zieluniversumseinstellungen
   gelten oder **Lokale Währung und Handelsregeln behalten** werden.
   Die lokale Variante erhält vorhandene Kampagnenkonten und Guthaben unverändert.
3. **Zuordnung prüfen** öffnet die Vorschau. Die bisherige Zuordnung bleibt
   dabei unverändert.
4. Die Vorschau lesen, das Bestätigungsfeld markieren und **Zuordnung bestätigen**
   wählen. Veraltete Zuordnungen oder zwischenzeitlich geänderte Zieluniversen
   erfordern eine neue Vorschau.

Nur Ersteller und Administration verwalten ein Universum. Zum Zuordnen sind
zusätzlich Verwaltungsrechte für die Kampagne und gegebenenfalls das bisherige
Universum erforderlich. Ein Universum zeigt ausschließlich Kampagnen, auf die
die angemeldete Person ohnehin Zugriff hat. Mitgliedschaften und Chat-Rechte
werden nicht vererbt; Spieler sehen auch nicht die interne Weltbeschreibung.

Universumsänderungen stehen im eigenen Änderungsverlauf, Zuordnungen nur für
die Spielleitung im jeweiligen Kampagnenprotokoll. Die Universumsansicht zeigt
die letzten 20 Änderungen; ältere Datensätze bleiben gespeichert.

Die Zuordnung allein verändert weiterhin keine Regeln, Bögen oder Freigaben.
Nutzt eine Kampagne gemeinsame Basisbögen, ist das Lösen oder Wechseln gesperrt,
bis diese ausdrücklich unabhängig weitergeführt werden. Unabhängige Kopien
dürfen ihre Universumszuordnung dagegen verlieren, sofern bestehende Konten
weiter zu ihrer Währung passen.

### Regelvorlage und Basisbögen verwenden

1. Im Universum **Regelwerk & Basischaraktere** öffnen.
2. **Universumsregelwerk bearbeiten** aufklappen. Felder, Wertebereiche, Startwerte,
   erlaubte Würfel und Wurfsichtbarkeiten festlegen und speichern.
   Die Spielerbearbeitung ist eine Vorgabe für spätere Kampagnen; Basisbögen
   bearbeitet unabhängig davon ausschließlich die Universumsleitung.
3. Einen **Basischarakter** für ein leseberechtigtes Mitglied dieser Website
   anlegen. Der Besitz öffnet noch keine Kampagne und keine Universumsverwaltung.
4. Eine neue, noch unkonfigurierte Kampagne zuordnen und den Besitzer dort als
   Teilnehmer aufnehmen. Die persönliche Aufnahme erfolgt weiterhin im Backend,
   alternativ entscheidet die Spielleitung über einen Beitrittsantrag.
5. Unter **In eine Kampagne übernehmen** die **Regelvorlage übernehmen**
   ausdrücklich bestätigen. Das funktioniert nur ohne gespeichertes
   Kampagnenregelwerk und ohne Charaktere. Die Feldkennungen bleiben erhalten;
   spätere Vorlagenänderungen werden nicht automatisch nachgezogen.
6. Einen freigegebenen Basisbogen wählen, **Gemeinsamer Basisstand** oder
   **Unabhängige Kampagnenkopie** auswählen und die Übernahme bestätigen.

![Universumsregelvorlage, Basisbogen und Übernahme in getrennte Kampagnen](images/13-universum-basisboegen.png)

*Die getrennte Prüfwelt besitzt einen Basisbogen und zwei zugeordnete Runden.
Die ausdrücklich gewählte Übernahme ändert keine Mitgliedschaften.*

**Gemeinsamer Basisstand:** Änderungen am freigegebenen Basisbogen erscheinen
auch in seinen gemeinsamen Kampagnenbögen. Grundwerte und Ressourcen werden im
Universum bearbeitet, nicht im Kampagnenbogen. Offene Basisanträge bleiben privat.

**Unabhängige Kampagnenkopie:** Nur der aktuelle freigegebene Stand wird
übernommen. Die Kopie hat danach eigene Bearbeitung und Freigaben entsprechend
dem Kampagnenregelwerk; späterer Basisfortschritt verändert sie nicht.
Sie bleibt auch bei fehlender Basis oder gelöster Universumszuordnung nutzbar.

Zur Übernahme muss dieselbe Person Universum und Kampagne verwalten dürfen;
die Feldkennungen und Wertebereiche müssen zusammenpassen. Bestehende
Kampagnen werden nicht automatisch migriert oder überschrieben.

### Kampagnenbezogene Handicaps und Stufen

Die Kampagnenleitung öffnet den übernommenen Bogen und klappt unter
**Kampagnenkontext** den Bereich **Kampagnenauflagen bearbeiten** auf.
Für Zahlen- und Attributfelder lassen sich absolute ganze Werte innerhalb
der Regelgrenzen eintragen, beispielsweise Mut 2 oder Stufe 1.
**Kampagnenauflagen freigeben** macht diese Werte nur in dieser Kampagne wirksam.
Ein leeres Feld übernimmt wieder den Grundwert. Das gilt für gemeinsame Bögen
und unabhängige Kopien gleichermaßen.

![Effektiver Charakterstand und kampagnenbezogene Auflagen](images/14-kampagnenauflagen.png)

*Elins Basis besitzt Mut 8 und Stufe 3. In der gemeinsamen Proberunde gelten
wegen ihrer lokalen Auflagen Mut 2 und Stufe 1. Andere Runden bleiben unverändert.*

Angezeigt und gewürfelt wird mit dem effektiven Stand. Basis und andere Runden
werden nicht überschrieben; auch bei einer Bearbeitung der Kopie bleiben ihre
gesonderten Auflagen erhalten. Notiz und Änderungshistorie sehen nur Besitzer,
aktuelle Spielleitung und Administration. Nur die Kampagnenleitung erteilt
Auflagen; dafür sind keine Verwaltungsrechte am Universum erforderlich.
Basisänderungen stehen dagegen in der geschützten Universumshistorie.

### Gemeinsamen Bogen unabhängig weiterführen

Die Kampagnenleitung öffnet am Bogen unter **Kampagnenkontext** den Bereich
**Als unabhängigen Kampagnenbogen weiterführen**. Die Vorschau zeigt den
freigegebenen Grundstand einschließlich Kampagnenauflagen, aber ohne
Ausrüstungsboni. Nach Bestätigung **Basisverbindung lösen** wählen.

Die vorhandene Figur bleibt bestehen: Werte, Ressourcen, Notiz, Ausrüstung,
Inventar und persönliche Kampagnenkonten werden erhalten. Die bisherigen
Auflagen werden eigene Grundwerte, nicht erneut angewendet. Offene Basisanträge
werden nicht übernommen. Spätere Basisänderungen gelten hier nicht mehr;
Basisbogen und andere Kampagnen bleiben unverändert. Der Moduswechsel wird
mit Vorher/Nachher nur für Besitzer und Spielleitung protokolliert.
Eine automatische Rückverbindung ist nicht möglich.

Ein gemeinsames Universumskonto sperrt die Umstellung bis zu einer gesonderten
Kontenmigration. Es wird kein Ersatzkonto erstellt und kein Guthaben kopiert.
Nach dem Ablösen aller gemeinsamen Bögen kann die Universumszuordnung geändert
oder gelöst werden; die bestehenden Währungs- und Kontenschutzregeln gelten
weiterhin.

### Eigene Universumsbögen beantragen und freigeben

Ein aufgenommenes Kampagnenmitglied öffnet bei den Charakteren
**Neuen eigenen Universumsbogen beantragen**, füllt die Universumsvorlage aus
und reicht sie ein. Das ist auch ohne bereits vorhandenen Basisbogen möglich.
Der neue Bogen bleibt bis zur Freigabe ohne gültigen Stand.

Die Universumsleitung öffnet den Antrag unter **Universen → Regelwerk &
Basischaraktere** und genehmigt oder verwirft ihn. Kampagnenleitung allein
genügt dafür nicht. Erst ein freigegebener eigener Basisbogen kann anschließend
für eine Kampagne beantragt oder durch deren Leitung übernommen werden.
Die beiden Freigaben gewähren keine Teilnahme an anderen Kampagnen.

### Eine unabhängige Kopie wieder verbinden

Unter **Kampagnenkontext → Mit dem aktuellen Basisbogen wieder verbinden**
zeigt die Vorschau abweichende Werte. Für jeden Konflikt eine Lösung wählen:
den Basiswert übernehmen oder bei Zahlen und Attributen den Kopiewert als
Kampagnenauflage erhalten. Andere abweichende Werte müssen ausdrücklich durch
den Basiswert ersetzt werden. Inventar und Ausrüstung bleiben erhalten.
Die Rückverbindung erfolgt nur nach Bestätigung, nie automatisch.

Bei kostenpflichtigen Abenteuerentscheidungen wird eine gemeinsame
Basisressource einmalig am Universumsbasisbogen verbraucht. Andere damit
verbundene Runden sehen denselben neuen Stand. Offene Basisanträge und veraltete
Verbrauchsvorschauen sperren den Vorgang; Wiederholen zahlt nicht nochmals.

Ressourcenauflagen und universumsweite Mitgliedschaften bleiben offen.
Optionale Erfahrungs-/Stufenschwellen berechnen eine frei benannte numerische
Stufe aus dem Erfahrungsstand. Eine fehlende gemeinsame Basis erzeugt einen
sichtbaren Fehler statt eines veralteten Ersatzbogens.

### Später auf Englisch übersetzen

Die Textdomain heißt **`ps-pen-paper`**. Aus
[`languages/ps-pen-paper.pot`](../languages/ps-pen-paper.pot) lässt sich etwa mit
Poedit ein englischer Katalog erstellen. Diesen als
`languages/ps-pen-paper-en_US.po` und die kompilierte
`languages/ps-pen-paper-en_US.mo` speichern; für britisches Englisch `en_GB`
verwenden. WordPress beziehungsweise ClassicPress wählt die Übersetzung anhand
der Website- oder Benutzersprache.

Noch wird kein fertiger englischer Katalog ausgeliefert. Nicht übersetzte
Plugintexte bleiben deutsch. Selbst verfasste Weltbeschreibungen, Regelbegriffe
und Spielinhalte werden nicht automatisch übersetzt.

### Als Spieler anmelden und einen Bogen erstellen

1. Ein Benutzerkonto beim Website-Betreiber erhalten oder dessen normale
   Registrierung verwenden, falls diese freigegeben ist. Das Plugin selbst
   bietet noch keine Kontoregistrierung an.
2. Im Spielbereich **Anmelden** wählen.
3. Den Kampagnenlink von der Spielleitung öffnen.
4. Falls angeboten, **Teilnahme beantragen** wählen und die Entscheidung abwarten.
   Sonst nimmt die Spielleitung dich persönlich auf.
5. Nach der Aufnahme die Runde unter **Kampagnen** öffnen.
6. **Ersten Charakter erstellen** beziehungsweise **Mein Bogen → Neuen Charakter
   erstellen** öffnen, den Bogen ausfüllen und speichern.
7. Wenn das Regelwerk es verlangt, die Freigabe durch die Spielleitung abwarten.

Eine offene Anfrage gewährt noch keinen Spiel- oder Chat-Zugang.
Die Aufnahme als Spieler gewährt keine Spielleiterrechte.

### Gäste, Vorschau und Beitrittsanfragen

Unter **Rundeneinstellungen → Zugang & Privatsphäre** entscheidet die Spielleitung:

- **Geschlossen:** keine Kampagnendaten für Gäste und Nichtmitglieder.
- **Titel und separaten Teaser freigeben:** nur diese zwei Angaben sind über den
  Kampagnenlink öffentlich sichtbar. Die interne Beschreibung wird nie übernommen.
- **Anfragen erlauben:** angemeldete Mitglieder dieser Website können bei
  freigegebener Vorschau eine Aufnahme beantragen.
- **Persönliche Aufnahme:** die Spielleitung fügt vorhandene Benutzer selbst hinzu.

![Zugangseinstellungen mit getrennter Vorschau und Aufnahmeentscheidung](images/12-zugang.png)

Teilnehmerliste, Anwesenheit, Hintergrundbild, Bögen, Würfe, Protokoll und Chat
bleiben geschützt. Es gibt keine öffentliche Kampagnenübersicht; die Vorschau
wird über den weitergegebenen Link geöffnet. Unter **Beitrittsanfragen** nimmt
die Spielleitung Antragsteller als Spieler auf oder lehnt sie ab. Bereits offene
Anfragen lassen sich auch nach Schließen der Vorschau noch entscheiden.
Nach einer Ablehnung oder beendeten Teilnahme ist eine erneute automatische
Anfrage gesperrt; für eine spätere Aufnahme die Spielleitung kontaktieren.

Auch Anfrage, Annahme und Ablehnung werden nur für die Spielleitung protokolliert.
Antragsstatus, gegebenenfalls erteilte Spielerrolle und Protokolleintrag werden
als Einheit gespeichert. Scheitert die Speicherung einer Entscheidung,
bleibt die Anfrage offen; es wird weder unbemerkt eine Rolle vergeben noch
eine Ablehnung übernommen.
Der Antragsteller sieht weiterhin nur den eigenen Antragsstatus, nicht die
private Mitgliederhistorie.

Einladungen und die automatische Aufnahme in eine Netzwerk-Unterwebsite sind
noch nicht enthalten. Benutzer müssen dort bereits Mitglied sein.
**„Archiviert“ ist derzeit nur eine Kennzeichnung, keine Schreibsperre.**

**Praxishinweis zur Entwicklungswebsite:** Der normale Demo-Spielleiter wurde
beim Backend-Zugriff umgeleitet. Zugang, Beitrittsanfragen, Bilder und Farben
sind inzwischen direkt im Frontend erreichbar; Anträge, Freigaben und Würfe funktionieren
über den Frontend-Spielbereich auch für die normalen Demokonten.
Für selbstständige Spielleiter muss dieser Backend-Zugriff vor einem Einsatz
auf dieser Website geklärt werden. Nicht pauschal Administratorrechte vergeben.

## 4. Regeln und Charakterfelder

Im **Regelwerk** werden Name, Beschreibung, erlaubte Würfel, Wurfsichtbarkeiten
und die Bearbeitungspolitik für Charaktere festgelegt.

![Regelwerks-Editor mit Bearbeitungsmodus, Würfeln und Sichtbarkeiten](images/02-regelwerk.png)

*Ausschnitt aus der Verwaltung: In der Demo sind W6 und W20 sowie alle drei
Sichtbarkeiten erlaubt.*

Bis zu 30 Felder sind möglich:

| Feldtyp | Verwendung |
|---|---|
| Kurzer Text | Herkunft, Beruf oder andere frei benannte Angaben |
| Langer Text | Hintergrundgeschichte und ausführliche Notizen |
| Auswahl | Eine der ausdrücklich konfigurierten Optionen |
| Zahl | Ganzzahliger Wert mit Grenzen und Startwert |
| Attribut | Ganzzahliger Wert, auch zum Addieren bei Würfen |
| Ressource | Aktueller Wert und persönliches Maximum |

Bezeichnungen, Erläuterungen und Reihenfolge sind frei. Bei Zahlenfeldern
liegen Minimum, Maximum und Startwert zwischen −1000000 und 1000000.
Felder werden über Schaltflächen hinzugefügt, verschoben und entfernt.

Auswahlfelder erhalten unter **Auswahlmöglichkeiten (eine pro Zeile)** ihre
zulässigen Werte. Ein anderer Wert wird beim Speichern abgelehnt.
Unter **Optionale Attribut-Punkteverteilung** kann die Spielleitung Attribute
auswählen und eine Gesamtsumme festlegen. Diese Attribute müssen zusammen
genau die konfigurierte Punktzahl ergeben; es sind keine zusätzlichen Punkte
oberhalb ihrer Startwerte. Ohne aktivierte Punkteverteilung gilt kein Punktepool:
bei der eigenen Erstellung bleiben Zahlenwerte auf den Vorgaben der Leitung.
Name, Hintergrund und erlaubte Auswahlen können selbst eingegeben werden;
Lebenspunkte, Ressourcenmaxima, Erfahrung und Level nicht.

Für ein Levelsystem **Startpunkte und Punkte je Level** auswählen:

1. Die Attribute markieren, auf die Spieler Punkte verteilen dürfen.
2. **Startpunkte** und **Zusätzliche Punkte je Levelaufstieg** festlegen.
3. Stufenregeln ab Level 1 und dem Erfahrungsminimum verwenden.

Beispiel: 10 Startpunkte und 2 Punkte je Aufstieg erlauben auf Level 1
insgesamt 10, auf Level 2 insgesamt 12 und auf Level 3 insgesamt 14 Punkte.
Der Bogen zeigt die noch verfügbaren Punkte während der Eingabe an.
Die Leitung vergibt Erfahrung; das erreichte Level bestimmt das Budget.
Ein Aufstieg erzwingt keine sofortige Verteilung: offene Punkte bleiben
verfügbar. Feldgrenzen und gegebenenfalls die Attributfreigabe gelten weiter.
Manipulierte Formulare können weder zusätzliche Punkte noch eigene
Ressourcen-, Erfahrungs- oder Levelwerte erzeugen.

### Bearbeitungsrechte je Feld

Unter **Bearbeitungsrecht** wählt die Spielleitung je Feld **Spieler direkt**,
**Mit Freigabe** oder **Nur Spielleitung**. **Vorgabe des Regelwerks** erhält
die bisherige Kampagnenpolitik; diese gilt auch für den Charakternamen.
So kann der Hintergrund direkt bearbeitbar sein, während Erfahrung eine
Freigabe benötigt und Lebenspunkte ausschließlich die Leitung ändert.
Geschützte Werte werden auch bei manipulierten Formularen serverseitig gesperrt.

Direkte Änderungen werden im gültigen Stand und im bestehenden Antrag
nachgeführt. Andere beantragte Werte bleiben offen; eine Ablehnung nimmt
direkte Änderungen nicht zurück. Ein neuer Bogen mit Freigabefeldern benötigt
eine vollständige Erstfreigabe. Gemeinsame Basisbögen bleiben leitungsverwaltet.
Diese Rechte betreffen Charakterfelder, nicht die getrennt verwalteten Geldkonten.

### Optionale Erfahrung und Stufen

1. Zwei Zahlen-/Attributfelder für Erfahrung und Stufe anlegen und speichern.
2. Unter **Optionale Erfahrung und Stufen** die Berechnung aktivieren und
   diese beiden unterschiedlichen Felder auswählen.
3. Schwellen als **Erfahrung : Stufe** eingeben, eine Zeile je Schwelle,
   beispielsweise `0 : 1`, `100 : 2`, `300 : 3`.
4. Bei vorhandenen Bögen zuerst die Änderungsvorschau prüfen und ausdrücklich
   bestätigen. Gültiger Stand und Antrag werden getrennt angepasst.

Die erste Schwelle beginnt beim Minimum des Erfahrungsfeldes. Erfahrung und
Stufen müssen aufsteigend sein und innerhalb ihrer Feldgrenzen liegen.
Punkteverteilungsfelder sind ausgeschlossen. Die Stufe entspricht der höchsten
erreichten Schwelle des jeweiligen Erfahrungsstandes; sinkende Erfahrung kann
auch die Stufe senken. Beantragte Erfahrung verändert nur die beantragte Stufe.
Dies gilt für Stufenregeln ohne Level-Punktepool. Mit **Startpunkte und Punkte
je Level** vergibt ausschließlich die Leitung Erfahrung; andere Zahlenwerte
außerhalb des Attributpools sind ebenfalls leitungsverwaltet.
Das Stufenfeld ist nicht manuell editierbar. Klassen und Attributboni werden
nicht vergeben; Erfahrung und Stufe erhalten keine Ausrüstungsboni oder
Kampagnenauflagen. Beim Ausschalten bleiben vorhandene Werte erhalten.
Bei einem aktiven Level-Punktepool muss dieser vor dem Ausschalten ausdrücklich
auf eine feste Punkteverteilung oder **Nicht verwenden** umgestellt werden.
Leitungskonten erhalten im Spielerprofil dieselben eingeschränkten
Spieler-Eingaben; administrative Korrekturen erfolgen in der Spielverwaltung.

### Zusätzliche Würfelmechaniken

Unter **Erlaubte Würfelmechaniken** gibt die Leitung einzelne Varianten frei.
Ohne neue Konfiguration bleibt die bisherige Würfelsumme erhalten.

- **Würfelpool:** genau eine Gruppe, etwa `4W6`, und eine Erfolgsschwelle
  zwischen 1 und der Seitenzahl. Jeder Würfel ab dieser Schwelle zählt einmal;
  keine Modifikatoren oder Attributaddition.
- **Bestes/schlechtestes Ergebnis:** höchster/niedrigster Einzelwürfel einer
  Gruppe. Bei `2W20 + 2` wird nur einer der beiden Würfel verwendet, danach
  werden Modifikator und gegebenenfalls der gewählte Attributwert addiert.

Einzelwürfe, Mechanik und Poolschwelle bleiben im geschützten Wurfprotokoll
gespeichert. Spätere Regeländerungen ändern alte Ergebnisse nicht.
Diese Varianten gelten für die Würfelbühne; Abenteuerproben und Beutetabellen
behalten ihre bisherige Würfelsumme.

Die eigenständige Live-Demorunde **Regelprobe – Werkstatt von Nebelhafen**
wurde mit demomann über echte Browserformulare eingerichtet und geprüft:
100 Erfahrung ergaben zunächst Stufe 2; die bestätigte Schwellenmigration
änderte sie auf Stufe 3. Der Reparaturpool `4W6` mit Schwelle 4 ergab aus
3, 6, 4, 6 genau drei Erfolge. Höchster und niedrigster Würfel wurden ebenfalls
mit Modifikator und Handwerkswert geprüft. Bestehende Demorunden blieben unverändert.

### Bestehende Regeln ändern

- **Umbenennen** erhält die interne Feldkennung und vorhandene Werte.
- **Neue Startwerte** überschreiben keine bestehenden Charakterwerte.
- Änderungen an verwendeten Feldlisten, Typen, Pflichtstatus und Wertebereichen
  benötigen eine Vorschau für gültige Charakterstände und offene Anträge.
  Die Vorschau zeigt unverträgliche Werte und die vorgesehene Konfliktlösung.
  Passt diese nicht, zuerst den Entwurf ändern und eine neue Vorschau öffnen.
  Erst die ausdrückliche Bestätigung **Vorschau migrieren und Regelwerk speichern**
  übernimmt den angezeigten Stand.
- Neue Regeländerungen werden mit Vorher-/Nachher-Ständen protokolliert.
- Bei einem Konflikt die Seite neu laden und die Änderung erneut prüfen.

Strukturelle Änderungen werden niemals ohne Bestätigung übernommen.
In der Demo wurde „Dampf“ nach dem Spielen zu „Kesseldruck“ umbenannt:
Die Ressource blieb erhalten.

## 5. Charaktere und Freigaben

Spieler öffnen ihre Kampagne im Spielbereich und erstellen dort ihren eigenen
Charakter. Spielleiter können auch Charaktere für andere Mitglieder erstellen.
Name und Felder werden serverseitig geprüft.

Die Spielleitung wählt die Vorgabe pro Kampagne; eigene Feldrechte können
diese Vorgabe für einzelne Werte ersetzen:

- **Mit Freigabe:** Spieleränderungen bleiben zunächst ein Antrag.
- **Direkt:** Spieleränderungen werden unmittelbar gültig.
- **Nur Spielleiter:** Ohne ausdrücklich freigegebene Einzelfelder können
  Spieler ihre Bögen lesen, aber nicht bearbeiten.

Im Freigabemodus zeigt die Charakterseite **„Gültiger Stand“** und gegebenenfalls
**„Offener Antrag“** getrennt. Spielleiter können **„Freigeben“** oder
**„Antrag verwerfen“** wählen. Eine Ablehnung verändert den gültigen Stand nicht.
Ein noch nicht freigegebener neuer Charakter kann nicht für Attributwürfe
verwendet werden.

![Protokoll der Erstanlage mit noch offenem Charakterantrag](images/03-charakterfreigabe.png)

*Dies ist die historische Vorher-/Nachher-Ansicht von Maras Erstanlage:
Der Antrag war gespeichert, aber noch kein Wert freigegeben. Die spätere
Freigabe ist ein eigener Protokolleintrag.*

Neue Eingaben ersetzen die entsprechenden beantragten Werte. Direkte
Spieleränderungen genehmigen andere offene Werte nicht. Eine direkte Speicherung
durch den Spielleiter übernimmt die Werte sofort und entfernt einen offenen
Antrag. Ein Wechsel der Bearbeitungspolitik allein genehmigt keine Anträge.

Charakterbögen sehen derzeit nur Besitzer und aktuelle Spielleiter
beziehungsweise Administratoren, nicht automatisch die ganze Gruppe.
Gewöhnliche Bögen bleiben kampagnengebunden. Aus einem Universum übernommene
Bögen folgen dem ausdrücklich gewählten gemeinsamen oder unabhängigen Modus
und können zusätzliche lokale Zahlenauflagen besitzen; siehe Abschnitt 3.

## 6. Würfeln

Unter **„Würfel / Protokoll“**:

1. Einen erlaubten Würfel über die Schnellwahl wählen oder den Ausdruck eingeben.
2. Optional einen freigegebenen eigenen Charakter auswählen.
3. Optional ein Zahlen- oder Attributfeld addieren.
4. Sichtbarkeit und gegebenenfalls Anlass wählen.
5. **„Jetzt würfeln“** drücken.

Bei übernommenen Basisbögen speichert der Wurf neben dem tatsächlichen
Attributwert auch Basis- und Kontextrevision. Diese stehen unter
**Einzelwürfe und Modifikatoren**. Private Auflagennotizen werden nicht in
Gruppenwürfe übernommen. Spätere Basis- oder Auflagenänderungen verändern
keinen bereits gespeicherten Wurf.

Beispiele: `3W6 + 2`, `W20 + 2W6 - 1`, `D100`.
Erlaubt sind höchstens zehn addierte Würfelgruppen mit insgesamt 100 Würfeln
und ein abschließender ganzzahliger Modifikator bis ±1000000.
Die Kampagne kann die Würfel und Sichtbarkeiten weiter einschränken.

**Attribut nicht doppelt addieren:** Für `2W6 + Geschick` in der Demo nur
`2W6` eingeben und Geschick im Auswahlfeld wählen.
Verwendet wird der freigegebene Zahlenwert, kein automatisch abgeleiteter Bonus.

![Gespeicherte Gruppenprobe mit zwei Einsen und Geschick drei](images/05-wuerfelergebnis.png)

*Echtes Demowurfergebnis: 1 + 1 + Geschick 3 = 5. Die Würfel wurden nicht
für ein schöneres Ergebnis erneut geworfen.*

| Sichtbarkeit | Wer liest das Ergebnis? |
|---|---|
| Gruppe | Aktuelle Kampagnenmitglieder, keine anonymen Besucher |
| Privat | Würfelnder, ausgewählter Charakterbesitzer und aktuelle Spielleiter |
| Verdeckt | Nur aktuelle Spielleiter und Administratoren |

Bei einer verdeckten Spielerprobe erhält der Spieler nur eine Bestätigung.
Eine Gruppenprobe teilt auch den gewählten Charakternamen und Attributwert.
Spielleiter können für andere Mitglieder würfeln.

Würfe werden auf dem Server gespeichert. Die Animation verändert keine Zahlen.
Neuladen einer Ergebnisseite würfelt nicht erneut.
**Erfolg, Komplikationen und Ressourcenverbrauch bewertet die Spielleitung.**

## 7. Ressourcen verwalten

Ressourcen haben einen aktuellen Wert und ein persönliches Maximum.
Beide müssen im erlaubten Bereich liegen; aktuell darf nicht größer als das
persönliche Maximum sein.

Eine Ressource wird über den Charakterbogen verändert. Im Freigabemodus wird
auch ein Verbrauch zunächst beantragt. Würfeln zieht nicht automatisch etwas ab.
Eine Ausnahme sind seit 0.23.0 ausdrücklich konfigurierte Kosten im
[Soloabenteuer](#ressourcenkosten-pro-entscheidung): Sie werden ohne zusätzlichen
Werteantrag nach der von der Spielleitung freigegebenen Entscheidung abgezogen.

![Freigabe des Ressourcenverbrauchs von vier auf drei](images/08-ressourcenfreigabe.png)

*Vor der Freigabe: gültig 4/6, beantragt 3/6. Nach der Freigabe: gültig 3/6.
Der historische Feldname „Dampf“ bleibt im Protokoll erhalten.*

## 8. Spielprotokoll lesen

**„Spielprotokoll“** zeigt Würfe, Regeländerungen, Charakteränderungen, Anträge,
Freigaben, Ablehnungen und Zugwechsel. Über **„Änderung ansehen: Vorher / Nachher“** werden
die damaligen Stände geöffnet.

![Gemeinsames Spielprotokoll mit Regeländerung und Freigabeereignissen](images/06-spielprotokoll.png)

- 20 berechtigte Ereignisse pro Seite; weitere Seiten über die Navigation.
- Charakterereignisse bleiben privat für Besitzer und aktuelle Spielleiter.
- Die damalige Bearbeitung allein verschafft ehemaligen Spielleitern keinen
  dauerhaften Zugriff auf fremde Charakterdaten.
- Entfernte Mitglieder verlieren auch den Zugriff auf ältere eigene Einträge.
- Neue Korrekturen erzeugen neue Ereignisse; alte Einträge werden nicht bearbeitet.

Charakter- und Regelhistorie beginnen mit Version 0.7.0. Frühere Änderungen
werden nicht rückwirkend ergänzt. Mitglieder- und Style-Änderungen werden
derzeit nicht protokolliert. Seit 0.12.0 stehen Szenenentwürfe, Freigaben,
Rücknahmen und Kartenbewegungen mit privaten Vorher-/Nachher-Ständen im
Spielleiterprotokoll; die Gruppe sieht Szenenwechsel mit freigegebenem Titel.
Änderungen an Zugang und Teaser werden seit Version 0.9.0 mit Vorher/Nachher
ausschließlich für Spielleitung und Administration protokolliert.

## 9. Farben anpassen

Als Universumsleitung unter **„Vorbereiten & verwalten“ → „Universen“ →
„UI-Farben des Universums (optional)“**:

1. Hintergrund, Flächen, Text, Schaltflächen, Links und Rahmen auswählen.
2. Live-Vorschau und Kontrasthinweise prüfen.
3. **„Gestaltung speichern“** drücken.

![Kampagnenfarben mit Live-Vorschau und Kontrastwerten](images/04-kampagnenfarben.png)

Hex-Farben haben sechs Stellen, etwa `#142b36`.
Die Schrift auf Schaltflächen wird automatisch schwarz oder weiß gewählt.
Der Kontrastrichtwert beträgt 4,5:1; Hinweise verhindern das Speichern nicht.
**„Standardfarben laden“** setzt zunächst nur die Eingaben zurück.
Die Farben gelten für die Kampagnen und Charakteransichten dieses Universums,
auch im eigenen PS-Community-Profil. Sie verändern nicht das Theme oder andere
Universen. Ohne eigene Farbauswahl gelten die Standardfarben.
Noch nicht zugeordnete Bestandskampagnen behalten bis zur kontrollierten
Universumszuordnung ihre bisherige Gestaltung.

### Ein eigenes Bild für den Spieltisch

Unter **Rundeneinstellungen → Bild für den Spieltisch** ein PNG-, JPEG- oder
WebP-Bild auswählen und **Hintergrund speichern** drücken. Erlaubt sind
höchstens 2 MiB und 3000 × 3000 Pixel; SVG und externe Bildadressen nicht.
Nur Bilder verwenden, für die die nötigen Rechte vorliegen.

Das Bild wird geschützt in der Datenbank gespeichert und nur an berechtigte
Betrachter ausgeliefert, nicht über eine öffentliche Mediathek-Adresse.
**Hintergrund entfernen** stellt den bildfreien Tisch wieder her und löscht
die gespeicherten Bilddaten. Ein Ersatzbild entfernt das vorherige; auch beim
endgültigen Löschen der Kampagne werden ihre Bilddaten gelöscht.
Für schnelle mobile Darstellung möglichst kleine Dateien verwenden.
Private Charakterporträts und eigene Bogenhintergründe sind seit 0.14.0
vorhanden; siehe Abschnitt 14. Für diese Bilder bleiben historische Stände
erhalten, anders als beim austauschbaren Spieltischhintergrund.

## 10. Handy und Tablet

Die Oberfläche nutzt responsive Anordnung und große Bedienelemente.
Auf schmalen Ansichten wechseln „Spiel“, „Mein Bogen“, „Würfel“ und „Gruppe“
den sichtbaren Bereich. Der Zugstatus bleibt in der Bereichsnavigation sichtbar.
Die Abbildung wurde bei 390 Pixeln Browserbreite aufgenommen.

![Persönlicher Charakterbogen auf dem Handy](images/10-spieltisch-mobil.png)

![Gespeichertes Würfelergebnis bei 390 Pixeln Browserbreite](images/07-mobil.png)

Es gibt keinen erforderlichen Drag-and-drop-Ablauf. Ein Betriebssystemwunsch
nach reduzierter Bewegung wird berücksichtigt. Ohne JavaScript bleiben
Formulare und Ergebnisse nutzbar; Schnellwahl, Live-Farbvorschau und Änderungen
an der Feldliste sowie Anwesenheit, Zugsteuerung und interaktive Karten benötigen JavaScript.

## 11. Probleme und Grenzen

| Problem | Prüfen |
|---|---|
| Keine Kampagne sichtbar | Anmeldung, richtige Unterwebsite und Mitgliedschaft |
| Kampagne kann nicht erstellt werden | Berechtigung durch einen Website-Administrator |
| Spielleiter landet auf einer anderen Backend-Seite | Weiterleitungen anderer Website-Komponenten prüfen lassen; Frontend-Aktionen gesondert testen |
| Charakter fehlt beim Würfeln | Freigegebener Stand, Besitzerrechte und aktuelle Mitgliedschaft |
| Verdecktes Ergebnis fehlt | Für Spieler beabsichtigt, kein Anzeigefehler |
| Werteänderung wirkt noch nicht | Offenen Freigabeantrag prüfen |
| Würfeln deaktiviert | Mindestens einen Würfel und eine Sichtbarkeit erlauben |
| Speicherkonflikt | Neu laden und gültigen Stand mit eigenen Änderungen abgleichen |
| Sichere Datenbanktransaktion nicht verfügbar | Administrator: InnoDB für Beitrags-/Metadatentabelle, Standard-Datenbankverbindung und fremde Transaktionen prüfen |
| Administratorprüfung angefordert | Nicht blind erneut speichern; unvollständigen Vorgang prüfen lassen |

Die zentrale Speicherung übernimmt Regel- und Charakteränderung samt
Protokolleintrag als Einheit: Vor dem Datenbankabschluss werden bei Fehlern
oder Abbruch beide zurückgenommen; nach dem Abschluss sind beide gespeichert.
Ohne geeignete Datenbankunterstützung wird die Änderung abgewiesen. Kann der
Abschluss nicht eindeutig bestätigt werden, erscheint ausdrücklich eine
Aufforderung zur Administratorprüfung. Dann nicht blind erneut speichern.
Das Protokoll ersetzt keine Datensicherung und ist kein manipulationssicheres
Audit-System.

### Was beim Abenteuerablauf noch fehlt

Der erste **Soloabenteuer-Ablauf** ist seit Version 0.21.0 vorhanden. Er
ergänzt den Story-Builder um private Einzeldurchläufe, freigegebene
Szenenpfade, Entscheidungen und optionale Zahlenproben. Seit 0.22.0 ergänzen
Mindestwerte und seit 0.23.0 optionale Ressourcenkosten diesen Ablauf.
Seit 0.24.0 sind feste und tabellenbasierte Belohnungen konfigurierbar.
Seit 0.25.0 lassen sich mehrere Wertebedingungen mit UND oder ODER verknüpfen.
Text- und Fortschrittsbedingungen sowie hybride Spielleiterfreigaben für
Einzeldurchläufe sind ergänzt. Gemeinsame Gruppendurchläufe mit konfigurierbaren
Entscheidungen, Teilnahme und Wiedereinstieg sind ebenfalls vorhanden.
Gegenstandsbedingungen, frei gesetzte Abenteuerzustände und Begegnungsphasen sind ergänzt;
Details stehen in Abschnitt 21.
Der **Story-Builder** mit Szenen, getrennten Geheimnissen, Inhaltsvorschau,
Freigabe, Rücknahme und Protokollierung ist vorhanden.
Kapitel mit geordneter Szenenfolge, private Personen-/Orts-/Notizkarten und
Text-/Bild-Handouts sind seit 0.13.0 vorhanden. Community-gebundene Inventare
und Ausrüstung ergänzen seit 0.14.0 den Charakterbereich, kontrollierter Handel
seit 0.20.0. Redaktioneller Komfort und weitere Handoutdateiformate fehlen
noch. Eine normale Medien-URL ist nicht automatisch privat.
Auch die unterstützten WordPress-, ClassicPress- und PHP-Versionen müssen
noch verbindlich festgelegt und plattformübergreifend geprüft werden.

## 12. Szenen und Kartentisch

### Ein Abenteuer am Tisch leiten

Seit **0.15.0** öffnet **Abenteuer & Szenen** zuerst den privaten Abenteuerplan:

1. Kapitel erscheinen mit der in der Spielleitermappe festgelegten Szenenfolge.
   Nicht zugeordnete Szenen stehen gesondert darunter.
2. **Jetzt am Tisch** markiert die aktive freigegebene Szene. Die folgende Szene
   ist als **Nächste Szene im Kapitel** gekennzeichnet. Die Reihenfolge zwingt
   keinen Lösungsweg auf: Die Spielleitung entscheidet selbst, wohin es geht.
3. **Vorbereiten / Vorschau / Freigabe** öffnet den Szeneneditor. Der Plan ist
   beim Bearbeiten eines gespeicherten Entwurfs zunächst eingeklappt.
4. **Am Tisch anzeigen** wechselt zur bereits freigegebenen Fassung und führt
   zurück zum Plan. Ein privater Entwurf bietet diese Schaltfläche nicht.
   Ein Hinweis kennzeichnet Abweichungen zwischen Entwurf und Spielstand;
   auch bewegte Marker können solche Abweichungen verursachen.
5. Unter **Aktuelle Szene leiten** stehen die gespeicherten Geheimnisse und
   direkt mit dieser Szene verknüpfte Personen, Orte, Notizen und Handouts.
   Alles in diesem Bereich bleibt ausschließlich für die Spielleitung sichtbar.
6. Einen noch privaten Hinweis aufklappen, die Freigabe ausdrücklich bestätigen
   und **Handout auf den Tisch legen** wählen. Nur Titel, Text und optionales
   Bild werden übernommen, niemals die geheimen Ergänzungen. Die Gruppe findet
   den Hinweis danach unter **Handouts**. Änderungen und Rücknahmen bereits
   veröffentlichter Handouts erfolgen weiterhin in der Spielleitermappe.

![Privater Abenteuerplan der Nebelhafen-Demo mit aktiver Szene, privatem Folgeentwurf und verknüpftem Handout](images/23-abenteuerplan.png)

*Die Kapitelreihenfolge ist eine Orientierung für die Spielleitung, kein
automatischer Questablauf. Plan, Entwurfstitel und Geheimnisse erscheinen
nicht in der Spieleransicht.*

Der Plan zeigt den Stand beim Laden. Wenn eine weitere Spielleitung etwas
ändert, **Abenteuerplan und Notizen neu laden** verwenden. Die Spielerszene
aktualisiert sich weiterhin wie im Abschnitt zum Kartentisch beschrieben.

### Probespiel: Von Dock 3 zur Morgenröte

Die bestehende Nebelhafen-Demo steht jetzt in **Die Laterne an Dock 3**.
Die Spielleitung hat die **Bordkarte für Kiste 17** ausdrücklich freigegeben;
Mara und Jorin sehen sie zusammen mit dem bisherigen Brief unter **Handouts**.
**An Bord der Morgenröte** ist die nächste Szene im Kapitel, bleibt aber
ein privater Entwurf ohne Schaltfläche zum Anzeigen am Tisch.

![Spieleransicht mit dem bisherigen Brief und der neu freigegebenen Bordkarte](images/24-bordkarte.png)

*Die geheimen Ergänzungen aus der Spielleitungsansicht fehlen hier vollständig.
Der Hinweis legt keine automatische Verladung, Abreise oder Probe fest.*

Für diese Fortsetzung wurde kein neuer Wurf benötigt. Maras bereits
gespeicherte Probe 13 bleibt der Ausgangspunkt; Charaktere, Ausrüstung
und Regeln wurden nicht verändert.

### Als Spielleitung vorbereiten

1. Im Spielbereich **Abenteuer & Szenen** öffnen. Im Backend befindet sich derselbe
   Editor in der Kampagnenverwaltung.
2. Unter **Neue Szene** Titel und freizugebenden Text eintragen.
3. **Geheimnisse – nur Spielleitung** getrennt ausfüllen. Dieses Feld erscheint
   weder in der Spielervorschau noch in den Kartenantworten für Spieler.
   Für eine reine Erzählszene direkt zu Schritt 7 gehen: Neue Szenen beginnen
   ohne Raster; ohne Bild und Marker wird keine leere Kartenfläche angezeigt.
   Die folgenden Kartenfelder liegen unter **Optionale Karte und Figurenmarker**.
4. Optional ein PNG-, JPEG- oder WebP-Kartenbild hochladen: höchstens 2 MiB und
   3000 × 3000 Pixel. Ohne Bild ist eine neutrale Kartenfläche möglich.
5. **Quadratraster** auf 10–500 Bildpixel setzen; **0** bedeutet ohne Raster
   und ohne Einrasten.
6. Über **Marker hinzufügen** bis zu 30 Figuren oder Gegenstände anlegen:
   sichtbarer Name, Typ, Farbe, Bewegungsberechtigter und Startposition.
   X und Y sind Prozentwerte von 0 bis 100; 50 bedeutet die Kartenmitte.
7. **Entwurf speichern** und **Gespeicherten Entwurf als Spieler ansehen** öffnen.
   Diese private Inhaltsvorschau ist ohne Figurensteuerung und zeigt nur den
   gespeicherten Stand, nicht ungespeicherte Eingaben.

![Szenenvorbereitung mit privater Inhaltsvorschau und getrennten Geheimnissen](images/15-szenenvorbereitung.png)

*Die schematische Prüfkarte ist ein Beispiel, kein mitgeliefertes Spielsystem.*

### Bewusst freigeben und anzeigen

1. Das Bestätigungsfeld lesen und **Gespeicherten Entwurf freigeben** wählen.
   Das veröffentlicht noch keine andere Szene am Tisch.
2. **Freigegebene Szene am Tisch anzeigen** wechselt die gemeinsame Ansicht.
3. Neue Änderungen zunächst wieder als Entwurf speichern. Der bisherige
   veröffentlichte Stand bleibt sichtbar, bis du erneut freigibst.
4. **Freigabe zurücknehmen** sperrt weitere Spielerabrufe dieser Szene und ihres
   Bildes. Die Spielansicht kehrt nach der nächsten Aktualisierung zur Einleitung
   zurück. **Zur Rundeneinleitung wechseln** lässt sich auch unabhängig davon nutzen.

**Wichtig:** Eine erneute Freigabe übernimmt die vorbereiteten Startpositionen.
Zwischenzeitlich am Tisch bewegte Positionen werden dabei ersetzt.
Jede Bewegung erhöht die Szenenrevision. War ein Bearbeitungsformular bereits
offen, kann sein Speichern deshalb eine Aktualisierung verlangen; fremde Änderungen
werden nicht still überschrieben.

### Gemeinsam auf der Karte spielen

![Kartentisch mit Figuren, Raster, Zoom und geteiltem Ping](images/16-kartentisch.png)

- **+**, **−** und **Einpassen** steuern den Ausschnitt. Die Karte lässt sich
  ziehen; Mausrad oder zwei Finger verändern den Zoom.
- Eine zugewiesene Figur wählen und ihr Ziel antippen oder die Figur ziehen.
  Alternativ **Figur ohne Ziehen bewegen** öffnen und X/Y eingeben.
  Per Tastatur lässt sich der Figurenmarker mit Eingabe oder Leertaste auswählen;
  danach sind die Positionsfelder fokussiert.
- Die Spielleitung bewegt alle Marker. Spieler bewegen ausschließlich ihre
  ausdrücklich zugewiesenen Marker, nicht automatisch jede Spielerfigur.
- **Ping setzen** wählen und auf die Karte tippen. Der Hinweis trägt den
  tatsächlichen Absender und läuft nach spätestens 15 Sekunden aus.

Die aktuelle Karte wird auf sichtbaren Browserseiten alle fünf Sekunden
aktualisiert. Bewegungen und Szenenwechsel erscheinen deshalb gegebenenfalls
leicht verzögert. Positionen bleiben über Neuladen und Abmelden hinaus gespeichert.
Eine Mitgliedschaftsänderung wird bei neuen Abrufen und Bewegungen geprüft.

Alle freigegebenen Marker sind für die Runde sichtbar. Namen bewusst wählen;
private Charakterwerte werden nicht automatisch an einen Marker angehängt.
Raster und Bewegung prüfen weder Reichweite noch Kollisionen oder Spielregeln.
Nebel des Krieges, versteckte Marker, Zeichenwerkzeuge und Entfernungsmessung
sind noch nicht vorhanden.

### Bildschutz und Aufbewahrung

Kartenbilder liegen in privaten Datensätzen ohne öffentliche Mediathek-Adresse.
Spieler erhalten nur das Bild der aktuellen freigegebenen Szene. Alte Bildstände
bleiben geschützt für die Historie gespeichert und werden beim endgültigen
Löschen der Kampagne entfernt. Kleine Dateien verwenden und die Datenbank sichern.
Bereits betrachtete oder heruntergeladene Inhalte können durch eine Rücknahme
nicht nachträglich aus fremden Browsern oder Kopien entfernt werden.

### Zu den Abbildungen

Die Bilder zeigen echte angemeldete Rollenansichten und gespeicherte Demodaten.
Browsernavigation und fremde Debug-Einblendungen sind für die Abbildungen
ausgespart beziehungsweise nur während der Aufnahme ausgeblendet.

## 13. Spielleitermappe, Kapitel und Handouts

### Abenteuer in der Spielleitermappe vorbereiten

1. Als Spielleitung im Spielbereich **Spielleitermappe** öffnen. Im Backend
   steht derselbe Bereich in der Kampagnenverwaltung zur Verfügung.
2. **Neuer Mappeneintrag** wählen und die Art festlegen: Kapitel, Handout,
   Person, Ort oder Notiz. Die Art lässt sich nach dem Anlegen nicht umstellen.
3. Titel, Inhalt und gegebenenfalls geheime Ergänzungen eintragen.
   Personen, Orte, Kapitel und Notizen bleiben vollständig privat.
4. Über die Sortiernummer die Reihenfolge innerhalb der jeweiligen Rubrik
   festlegen. Kleinere Zahlen erscheinen zuerst.
5. Optional ein Kapitel und eine Szene verknüpfen. Die Verknüpfung dient der
   Vorbereitung und verändert weder Mitgliedschaften noch Freigaben.
6. **Mappeneintrag speichern**. Bei einem Revisionskonflikt den aktuellen
   Stand neu laden; Änderungen werden nicht still überschrieben.

Eine Personenkarte ist eine erzählerische Gedächtnisstütze, kein verpflichtender
Gegnerbogen. Ortskarten sammeln Atmosphäre und Hinweise; Notizen eignen sich
für die eigene Vorbereitung oder eine private Zusammenfassung.

### Kapitel und Szenenfolge

Ein Kapitel zunächst speichern und dann erneut bearbeiten. Unter **Szenen im
Kapitel** neben jeder gewünschten Szene eine Reihenfolgenummer eintragen.
Ein leeres Feld nimmt die Szene nicht auf. Bis zu 100 Szenen sind pro Kapitel
möglich; eine Szene kann in mehreren Kapiteln vermerkt sein.

Die Kapitelübersicht zeigt die geordnete Folge mit Links und Freigabestatus.
Ein Link öffnet die Szenenvorbereitung. **Eine Kapitelzuordnung veröffentlicht
keine Szene und wechselt nicht die aktive Szene.** Dafür weiterhin die
ausdrücklichen Szenenaktionen aus Abschnitt 12 verwenden.

![Spielleitermappe der fortgesetzten Nebelhafen-Demo mit Kapitel und Szenenfolge](images/17-spielleitermappe.png)

### Ein Handout auf den Tisch legen

1. Einen Eintrag der Art **Handout** mit Titel und Text speichern.
2. Optional ein eigenes PNG-, JPEG- oder WebP-Bild hochladen, höchstens 2 MiB
   und 3000 × 3000 Pixel. PDFs und andere Dateien sind nicht unterstützt.
3. **Private Vorschau des gespeicherten Handoutentwurfs** öffnen.
4. Die Bestätigung markieren und **Handout für die Runde freigeben** wählen.
5. Spieler öffnen **Handouts** beziehungsweise **Handouts neu laden**.

Die Gruppe erhält ausschließlich den freigegebenen Titel, Text und das Bild.
Geheime Ergänzungen, Kapitel- und Szenenverknüpfungen bleiben in der
Spielleitermappe. Handouts bleiben auch nach einem Szenenwechsel zugänglich.
Die Freigabe gilt für die gesamte aktuelle Kampagnengruppe, nicht für einzelne
Empfänger. Auch die Spielleitung sieht unter **Handouts** nur freigegebene Stände.

![Freigegebener Brief mit geschütztem Frachtzeichen aus Maras Spieleransicht](images/18-handout.png)

### Überarbeiten, zurücknehmen und schützen

Entwurfsänderungen verändern ein bereits freigegebenes Handout nicht. Erst eine
erneute bestätigte Freigabe ersetzt den Gruppenstand. Ein Bild kann aus dem
Entwurf entfernt oder ersetzt werden; Entfernung und Ersatz sind getrennte
Aktionen. Historische Bilder bleiben für die Historie bis zur endgültigen
Kampagnenlöschung in der Datenbank.

**Handout zurücknehmen** verhindert die Ausgabe bei neuen Seitenabrufen.
Es gibt keine automatische Handoutaktualisierung und keine Möglichkeit,
bereits gelesene oder gespeicherte Inhalte aus fremden Browsern zurückzuholen.
Nach Entzug der Kampagnenmitgliedschaft sind auch die Handouts nicht mehr
abrufbar. Die Spielbereich-Seite einschließlich ihrer Abfrageparameter darf
nicht öffentlich zwischengespeichert werden.

Mappenänderungen, Handoutfreigaben und Rücknahmen besitzen private
Vorher-/Nachher-Protokolle für die Spielleitung. Die neuen Abbildungen zeigen
die tatsächlich fortgesetzte Nebelhafen-Demo; ihre Charaktere, Regeln und
ursprünglichen Würfe wurden dafür nicht zurückgesetzt.
Für diese Mappenabbildungen wurden keine Spielwerte oder Berechtigungen geändert.

## 14. Porträts und Bogenhintergründe

Diese Gestaltung funktioniert **auch ohne PS Community**.

1. Den eigenen Charakter über **Mein Bogen → Bogen bearbeiten** beziehungsweise
   die Charakterverwaltung öffnen.
2. Unter **Charakterporträt** ein PNG-, JPEG- oder WebP-Bild auswählen.
3. **Porträt einreichen / speichern** drücken. Im Freigabemodus sieht die
   Spielleitung den Antrag getrennt und wählt **Porträt freigeben** oder
   **Porträtantrag verwerfen**. Bis dahin bleibt das bisherige Bild gültig.
4. **Porträt entfernen / beantragen** entfernt das Bild je nach
   Bearbeitungspolitik sofort oder erst nach Freigabe.

Bei **Nur Spielleiter** können Besitzer keine Bilder ändern. Porträtanträge
ändern keine Charakterwerte und benötigen keine erneute Wertefreigabe.
Gemeinsame Basisbögen können trotzdem ein lokales Kampagnenporträt erhalten.
Das Bild erscheint nur auf berechtigten Charakterbögen für Besitzer und
Spielleitung, nicht als öffentlicher Community-Avatar oder Teilnehmeravatar.

Den Hintergrund bestimmt die Spielleitung unter **Rundeneinstellungen →
Bogenhintergrund der Kampagne**. Er erscheint dezent hinter dem gültigen
Charakterbogen. **Bogenhintergrund entfernen** schaltet die Dekoration aus.
Ein Ausschnitt-/Positionseditor ist noch nicht vorhanden.

![Maras freigegebenes privates Porträt und dezenter Nebelhafen-Bogenhintergrund](images/20-charakterportraet.png)

Alle drei Bildarten – Porträt, Bogenhintergrund und Gegenstandsgrafik –
verwenden den privaten Bildspeicher: höchstens 2 MiB und 3000 × 3000 Pixel,
keine SVGs oder externen Bildadressen. Historische Bilder bleiben für
Protokolle und ausgegebene Gegenstände bis zur Löschung ihres Ursprungs
gespeichert. Nur Bilder mit passenden Nutzungsrechten verwenden und die
Datenbank regelmäßig sichern.

## 15. Community-Profil, Gegenstände und Ausrüstung

### Voraussetzungen und privater Profil-Tab

PS Community auf derselben Website aktivieren. In der lokal geprüften
Community-Version müssen **Profil** und **Aktivität** eingeschaltet sein,
damit die Profil-Tab-Schnittstellen geladen werden. Eine veröffentlichte
Profilseite mit `[cpc-activity-page]` anlegen und in Community zuordnen,
falls noch keine vorhanden ist. Bei aktiviertem Permalinkmodus muss die
zugehörige Community-Profiladresse erreichbar sein.

Im **eigenen** Profil **Pen & Paper** öffnen. **Meine Charaktere** bietet die
Auswahl eigener Bögen, einschließlich noch nicht freigegebener Figuren.
Unter **Charakter erstellen** ein verfügbares Universum wählen und **Weiter**
anklicken. Die dort geltenden Vorgaben und das Charakterlimit bestimmen, ob
ein neuer Bogen erstellt werden kann. Neue Figuren benötigen gegebenenfalls
die Freigabe der Spielleitung. Erhaltene Einladungen erscheinen ebenfalls hier;
Leitungsfreigaben werden dagegen in der betreffenden Spielrunde verwaltet.

Im Profil wird ein vorhandener eigener Charakter direkt geöffnet. Die Tabs
**Werte** und **Inventar** zeigen seine Stats, Ressourcen und Gegenstände.
Ein eingerichtetes Universum erlaubt standardmäßig die eigene Erstellung für
Website-Mitglieder; eine weitere Mitgliedschaft ist nicht vorgeschaltet.
Nur eine ausdrücklich gespeicherte Zugangsbeschränkung der Leitung begrenzt dies.

**Deine Einladungen** nennt jeweils die Spielrunde beziehungsweise das Universum
und den Absender. Hier werden erhaltene Einladungen **angenommen** oder
**abgelehnt**. Das Spielerprofil enthält keine Mitgliederverwaltung,
Einladungsfreigaben oder Spieleauswahl.

Im Spielbereich unter **Mitspieler zu dieser Runde einladen** lädt die Leitung
oder ein berechtigtes Mitglied einen Spieler zur ausdrücklich benannten Runde
ein. Vorschläge von Mitgliedern bestätigt standardmäßig zuerst die Leitung.
Diese Freigaben und gesendete Einladungen werden dort verwaltet, nicht im Profil.
Nach Annahme ist die Runde im **Spielbereich** zugänglich.
Unter **Bögen** den freigegebenen Universumscharakter auswählen und
**Mit diesem Charakter mitspielen** einreichen; die Leitung bestätigt die
Aufnahme unter **Werte → Offener Antrag → Freigeben**.
Eine leere neue Kampagne bietet **Regeln dieses Universums übernehmen**.
Bestehende Regeln werden nicht automatisch überschrieben.

Alternativ führt **Mein Charakterprofil**
am Spieltisch dorthin. Im Tab lassen sich Bogen, Porträt und Ausrüstung
bearbeiten. **Zur Bogenübersicht** führt zur eigenen Profilauswahl zurück.
Profilformulare werden auf der Frontend-Profilseite verarbeitet und bleiben
nach dem Speichern dort.

Profil und Spiel verwenden dieselbe Bogenansicht: **Charakter**, **Werte**,
**Inventar**, **Abenteuer** und die erlaubten Bearbeitungsfunktionen. Das
Charakterbild steht zwischen den Ausrüstungsspalten. Level wird nur bei
eingestellter Stufenentwicklung angezeigt; Universumsfarben gelten auch hier.

Andere Profilbesucher und Freunde erhalten weder fremde Bögen noch Inventare.
Auch ein direkter Community-AJAX-Aufruf vergibt keine zusätzlichen Rechte.
Mitgliedschaft und Charakterbesitz bleiben erforderlich.
**Die Profilseite einschließlich ihrer Parameter vom Seiten-Cache ausschließen.**

### Als Spielleitung Slots und Seltenheiten festlegen

Im Spielbereich **Gegenstände → Slots und Seltenheitsstufen konfigurieren**
öffnen. Derselbe Katalog ist in der Backend-Kampagnenverwaltung und für
Universumsleitungen beim jeweiligen Universum erreichbar.

Die Vorgabe zeigt **Kopf, Beine und Füße** links sowie **Brust, Arme und Hände**
rechts vom Charakterbild. Namen lassen sich direkt ändern; Slots können
ausgeblendet, einer Seite zugewiesen oder mit einem privaten Bild versehen werden.
Ausblenden verändert weder die bestehende Belegung noch ihre Boni.

Zusätzliche Slots können über die erweiterte Eingabe angelegt werden.
Beispiel für deren Zeilenformat:

```text
kopf | Kopf | kopfbedeckung
arme | Arme | armschutz
brust | Brust | oberbekleidung
```

Die drei Teile sind **stabile Kennung | sichtbarer Name | passender
Gegenstandstyp**. Eine Kopfbedeckung passt nur in einen entsprechend
konfigurierten Slot. Eine konkrete Instanz darf nicht gleichzeitig mehrere
Slots belegen. Bestehende Slottypen werden nicht still umgedeutet.
**Entfernen** benötigt eine ausdrückliche Bestätigung: Belegung und betroffene
offene Anträge werden kontrolliert angepasst; Gegenstände bleiben im Inventar,
alte Nachweise bleiben erhalten. Die bisherige Kennung bleibt als entfernt
registriert und darf nicht für einen anderen Gegenstandstyp wiederverwendet werden.

Beispiel für unabhängige Seltenheitsstufen:

```text
normal | Normal | #52646d
episch | Episch | #8054a3
```

Bezeichnungen und Farben bestimmt die Spielleitung. Eine Farbe ist nie die
einzige Kennzeichnung. **Episch** erzeugt keinen automatischen Multiplikator.
Bis zu 30 Slots und 30 Seltenheiten sind je Vorlage möglich. Kampagnen legen
ihre Slots selbst fest, auch wenn sie Universumsgegenstände verwenden.

### Gegenstandsvorlagen erstellen und Exemplare ausgeben

1. **Neue Gegenstandsvorlage** öffnen.
2. Namen, Beschreibung, Typ und Seltenheit eintragen.
3. Freie Eigenschaften als `Bezeichnung | Wert` ergänzen.
4. Optional ausdrücklich ganzzahlige Boni auf Zahlen- oder Attributfelder
   setzen. Ressourcen und Textfelder sind keine Bonusziele.
5. Optional eine geschützte Grafik hochladen und die Vorlage speichern.
6. Den gewünschten Charakter öffnen und **Ein Exemplar ausgeben: …** wählen.
   Erst diese Aktion erzeugt Besitz; eine Katalogvorlage allein liegt
   noch in keinem Inventar.

![Tatsächlich angelegte Nebelhafen-Vorlagen mit freien Seltenheiten und geschützter Grafik](images/19-gegenstandskatalog.png)

Zugeordnete Kampagnen können Universumsvorlagen und eigene Ergänzungen
verwenden. Standalone-Kampagnen verwenden ihren eigenen Katalog.
Vorlagenänderungen gelten nur für spätere Ausgaben, nicht für bereits
ausgegebene Exemplare. Ein Charakter besitzt zunächst höchstens 100
einzelne Exemplare; Stapelmengen und eine Entzugs-/Löschoberfläche fehlen.

### Verbrauchsgüter und Gegenstandswirkungen

In der Vorlage unter **Ausrüstung oder konfigurierbarer Verbrauch** die
**Voraussetzung im Spiel** wählen: sofort, SL-Freigabe, Probe oder eine echte
Spielrunde aussetzen. Slotgebundene Ausrüstung wird dagegen angelegt, nicht
verbraucht. Für Verbrauchsgüter die gewünschten ganzzahligen Änderungen an
Attributen, Zahlen oder dem aktuellen Ressourcenwert und die Wirkungsdauer
eintragen. Klassen, Heiltränke und Mana sind mögliche eigene Konfigurationen,
keine eingebauten Spielregeln. Geld bleibt im konfigurierten Guthaben.

Im Charaktertab **Inventar** zeigt jedes Exemplar seine Voraussetzung und
Wirkung. **Gezeigte Folgen bestätigen / Gegenstand verwenden** verarbeitet
genau dieses Exemplar. Unzulässige Werte,
fehlende Rechte oder veraltete Stände verhindern den gesamten Vorgang.
Eine Probe wird pro Exemplar einmal aufgelöst; auch ein Fehlschlag oder eine
Ablehnung wird gespeichert und darf nicht durch Wiederholen ersetzt werden.
Bei erfolgreichem Verbrauch verschwindet das Exemplar aus dem Inventar,
während der Nachweis erhalten bleibt.

Temporäre Zahlenänderungen sind additive Effekte. Ressourcenänderungen werden
am tatsächlichen Charakterstand gebucht, damit sie anschließend ausgegeben
werden können. Beim Beenden wird nur die aufgezeichnete Änderung abgezogen,
nicht ein alter Gesamtstand zurückgeschrieben. Ein unzulässiges Effektende
bleibt bis zur Korrektur durch die Leitung gesperrt.

Unter **Spieltisch → Spielsitzung und Runden steuern** startet und beendet die
Leitung die Sitzung und zählt über **Nächste Spielrunde / fällige Effekte
beenden** weiter. Aussetzen sperrt charaktergebundene Würfe, Abenteueraktionen
und die Kartenbewegung des Besitzers bis zur nächsten ausdrücklich gezählten
Runde. AFK, Uhrzeit und Seitenaufrufe zählen nicht. Temporäre Kampagneneffekte
können eine zusätzliche Rundendauer haben; temporäre Universums-Basiseffekte
beendet ausschließlich die Universumsleitung ausdrücklich.

### Aktionen im Profil außerhalb des Spiels

Beim Universum beziehungsweise der Kampagne **Ausrüstung und Verbrauch
außerhalb des Spiels** öffnen. Profilaktionen sind zunächst deaktiviert.
Die Leitung erlaubt sie ausdrücklich und entscheidet getrennt über
**Ausrüstung wechseln** und **Gegenstände verbrauchen**: sofort, verboten oder
mit SL-Freigabe. Optional können Aktionen während einer gestarteten Sitzung
erlaubt werden. Ohne diese zusätzliche Erlaubnis bleiben sie dann gesperrt;
bei einem gemeinsamen Basisbogen zählen auch dessen aktive Kampagnensitzungen.

Slots und einzelne Exemplare können weitere Einschränkungen erhalten.
Ein Verbot hat Vorrang; Freigabeauflagen bleiben wirksam. Erlaubter
Profilverbrauch benötigt keine Probe und kein Rundenaussetzen.
Die erforderliche Zustimmung folgt den Profilregeln, nicht der ausschließlich
für das Spiel eingestellten Voraussetzung. Bereits offene Spielanträge lassen
sich durch einen Profilaufruf nicht in sofortigen Verbrauch umwandeln.
Besitz, Verbrauch, Feldgrenzen und Protokoll gelten in beiden Ansichten.

Ein Universumsbasisbogen besitzt ein eigenes Inventar. Ressourcenänderungen
werden einmal am gemeinsamen Basisstand gebucht und in verbundenen
Kampagnen angezeigt. Kampagnenausrüstung und deren Zahlenmodifier bleiben
unabhängig. Profilaktionen werden auf der signierten Profilseite verarbeitet;
ein frei übermitteltes Kontextfeld vergibt keine Rechte.

### Ausrüstung auswählen und freigeben

Im Charakter unter **Inventar & Ausrüstung** bei jedem Slot ein passendes
eigenes Exemplar oder **Leer** wählen. Über **Ausrüstung speichern /
einreichen** speichern. Im Freigabemodus bleibt die bisherige Belegung
wirksam, bis die Spielleitung **Ausrüstung freigeben** wählt. Die
Auswahlfelder zeigen bei offenem Antrag den beantragten Stand; die Übersicht
darunter zeigt die gültige Belegung. Ein neuer Antrag ersetzt den bisherigen.

![Maras eigenes Inventar im privaten Community-Profil mit Kopf-, Arm- und Brustslot](images/21-inventar-profiltab.png)

Angelegte, freigegebene Boni werden addiert; die Grundwerte bleiben erhalten.
In der Demo gibt die Lotsenkappe **Mut +1**: Grundwert **2**, effektiver
Bogenwert **3**. Im Bearbeitungsformular steht weiter der Grundwert **2**.
Den Bonus beim Würfeln **nicht noch einmal** zum Ausdruck addieren.

![Gespeicherte Mutprobe mit Ausrüstungsherkunft: fünf plus fünf plus drei ergibt dreizehn](images/22-ausruestungswurf.png)

Der tatsächliche Wurf verwendet Mut 3 und speichert Ausrüstungsrevision,
Slotrevision und enthaltene Boni. Alte Würfe ändern sich nicht beim Ablegen
oder bei späteren Vorlagenänderungen.

### Grenzen, Fehlerbehebung und Deaktivierung

Feldgrenzen gelten einschließlich Boni. Unzulässiges Anlegen oder eine
unzulässige Grundwert-/Auflagenänderung wird ausdrücklich abgelehnt.
Bei gemeinsamem Basisfortschritt kann eine andere Kampagnenausrüstung
nachträglich ungültig werden. Dann werden keine still gekürzten Werte oder
Würfe erzeugt. Über **Ausrüstung prüfen** in der eigenen Profilübersicht
bleibt die Slotverwaltung zugänglich. Besitzer können das Ablegen beantragen;
die Spielleitung kann es freigeben oder unmittelbar korrigieren.

Ohne aktives, verwendbares PS Community sind Katalog, Inventaroperationen
und Ausrüstung inaktiv; **auch Boni werden dann nicht angewandt**.
Gespeicherte Bestände werden nicht gelöscht. Charakterbögen, Porträts,
Geschichten und Würfel bleiben nutzbar. Bereits gespeicherte Würfe behalten
ihre damaligen Werte. Bei erneuter Aktivierung gelten die gespeicherten
Belegungen wieder, soweit die aktuellen Wertebereiche sie zulassen.

Schema- und Vorlagenänderungen stehen privat im Spielleiterprotokoll;
Inventar- und Porträtänderungen sind nur für Besitzer und aktuelle
Spielleitung sichtbar. Das ist keine Datenbanksicherung.

### Gegenstände entziehen / entfernen

Die Spielleitung öffnet den Charakterbogen und wählt im Bereich
**Gegenstände entziehen / entfernen** die konkreten Exemplare aus.
Die Entfernung ausdrücklich bestätigen und absenden.
Auch gebundene und ausgerüstete Exemplare dürfen entfernt werden.
Aktives Inventar, Ausrüstungsantrag und zugehörige Slotbelegungen werden
gemeinsam bereinigt; eine spätere Freigabe holt die Exemplare nicht zurück.
Die bisherigen Vergaben und der Vorher-/Nachher-Nachweis bleiben gespeichert.
Handelsreservierte Exemplare zuerst durch Zurückziehen oder Ablehnen
des Angebots freigeben. Spieler können Gegenstände nicht selbst entziehen.

**Noch offen:** Stapelmengen, Mehrfachslot-Gegenstände und ein frei
positionierbarer grafischer Slotdesigner. Mengenhandel erfolgt bereits
durch Mehrfachauswahl konkreter Exemplare.
Die aktuelle Oberfläche verwendet zugängliche Auswahlfelder, kein Drag-and-drop.

## 16. Währung, Guthaben und Gegenstandsbindung

Dieser Bereich benötigt aktives PS Community. Die Währung schreibt kein
Spielsystem vor: Name und Kürzel sind frei, Beträge bleiben **ganze Einheiten**.

### Währung und Kontomodell einstellen

- **Mit Universum:** Unter **Universen → Regelwerk & Basischaraktere →
  Währung & Guthaben** wählt die Universumsleitung beziehungsweise Administration
  Name, Kürzel und Kontomodell.
- **Ohne Universum:** Unter **Rundeneinstellungen → Währung & Guthaben**
  richtet die Kampagnenleitung eine eigene Währung ein.
- Zugeordnete Kampagnen übernehmen standardmäßig die Währung und das gewählte
  Modell aus dem Universum. Bei bewusst lokaler Zuordnung bleiben stattdessen
  die Kampagnenwährung, bestehenden Konten und Handelsregeln erhalten.
  Es gibt keine automatische Umrechnung oder Verdoppelung von Guthaben.

| Kontomodell im Universum | Wirkung und Buchungsrechte |
|---|---|
| Getrennt je Kampagnencharakter | Jede Runde hat ein eigenes Guthaben, auch bei gemeinsamen Charaktergrundwerten. Es bucht die jeweilige Kampagnenleitung. |
| Gemeinsam am Universumscharakter | Gemeinsam aufgenommene Basisbögen verwenden dasselbe Konto. Nur Universumsleitung beziehungsweise Administration buchen. Lokale Bögen und unabhängige Kopien erhalten kein Ersatzkonto. |

Vor der ersten Buchung kann die Universumsleitung das Modell wechseln.
Danach bleibt es fest, damit Guthaben weder verschwinden noch doppelt
verwendet werden. Name und Kürzel bleiben änderbar. Ein Wechsel der
Universumszuordnung ist mit bewusst beibehaltenen lokalen Konten möglich.
Bestehende Universumskonten lassen sich damit nicht lokal übernehmen; für
solche Übertragungen und Währungsumrechnungen gemeinsamer Universumskonten fehlt
weiterhin die gesonderte Migration.

![Lokale Währungseinstellungen der Nebelhafen-Demo mit Dockmark und ganzen Einheiten](images/25-waehrungseinstellungen.png)

### Beträge vergeben oder abziehen

1. Als zuständige Spielleitung den Charakterbogen öffnen.
2. Unter **Guthaben → Guthaben vergeben oder abziehen** die Buchungsart wählen.
3. Einen **ganzen Betrag ab 1** und eine Begründung eintragen.
4. Betrag und Buchungsart ausdrücklich bestätigen und
   **Guthabenbuchung speichern** wählen.

Ein Kontostand darf zwischen **0 und 999999999** liegen. Nachkommastellen,
Überziehung und Überschreitung der Grenze werden abgewiesen. Ein veraltetes
Formular fordert zum Neuladen auf; es führt keine zweite Buchung aus.
Ohne erforderlichen Protokolleintrag wird auch das Guthaben nicht geändert.
Guthabenbuchungen verändern keine Charakterattribute oder Ressourcen.

Spieler sehen ausschließlich Guthaben ihrer eigenen zugänglichen Charaktere,
auch im privaten Community-Profil, aber keine Buchungsschaltflächen.
Kampagnenbuchungen stehen privat im Protokoll für Besitzer und aktuelle
Spielleitung. Den Verlauf gemeinsamer Konten verwaltet die Universumsleitung
im Universum. Alte Buchungen behalten damalige Bezeichnungen und Begründungen.

![Maras privates Guthaben von zehn Dockmark ohne Buchungsformular](images/26-charakterguthaben.png)

### Gegenstände binden

Beim Erstellen oder Bearbeiten einer Gegenstandsvorlage unter
**Gegenstandsbindung** wählen:

- **Charaktergebunden:** Das konkrete Exemplar gehört zu diesem Charakter.
- **Handelbar:** Das Exemplar kann nach den eingestellten Handelsregeln
  angeboten werden.

Beim Ausgeben wird die Auswahl im Exemplar festgehalten. Ein späterer
Vorlagenwechsel verändert vorhandene Exemplare nicht. Die Handelsfunktion
seit 0.20.0 berücksichtigt diese Bindung; siehe Abschnitt 20. Eine manuelle
Geldbuchung ist kein automatischer Kauf.

Der Ausgangsstand der Währungsdemo enthält **Dockmark (DM)**: Mara erhält
12 DM, davon werden 2 DM mit Begründung für Vorräte abgezogen; ihr damaliger Stand beträgt **10 DM**.
Jorin erhält getrennt **8 DM**. Mara besitzt zusätzlich einen gebundenen
**Bordausweis der Morgenröte** und einen handelbar gekennzeichneten
**Messingkompass vom Dock**, beide ohne neue Werteboni.
Spätere Beute und Handel verändern diesen Stand; der aktuelle Abschluss
steht in Abschnitt 20 und im Demobericht.

Ohne verwendbares PS Community sind wirtschaftliche Änderungen inaktiv;
gespeicherte Guthaben und Gegenstände werden nicht gelöscht.

## 17. Gegner, NPCs und Universumsvorlagen

Figuren sind private Werkzeuge der Spielleitung, unabhängig von PS Community
und PS Wiki. Eine Personenkarte bleibt eine Erzählnotiz; sie wird nicht
automatisch in einen Gegner oder NPC umgewandelt.

### Eine Kampagnenfigur vorbereiten

1. **Spielleitermappe → Gegner und NPCs verwalten** öffnen.
2. **Neue Kampagnenfigur** wählen, die Art **NPC** oder **Gegner** einstellen.
3. Name, Beschreibung und getrennte Geheimnisse eintragen.
4. Eigenschaften als `Bezeichnung | Text`, Werte als `Bezeichnung | ganze Zahl`
   eintragen, jeweils eine Zeile pro Eintrag.
5. Optional eine Szene und ein geschütztes Bild auswählen.
6. **Figur speichern** wählen. Die Vorschau zeigt den gespeicherten Stand.

Beispiel für Eigenschaften:

```text
Aufgabe | Hafenlotsin
Haltung | Hilfsbereit
```

Beispiel für Werte:

```text
Ortskenntnis | 4
Überzeugen | 3
```

Es sind höchstens **30 Eigenschaften und 30 Werte** möglich. Zahlen liegen
zwischen **-1000000 und 1000000**; Nachkommastellen und doppelte Bezeichnungen
innerhalb einer Liste werden abgewiesen. Es gibt keine vorgeschriebene
Attributliste. Werte verändern keine Spielerbögen und lösen weder Kämpfe
noch Proben automatisch aus.

Bilder werden wie andere private Spielbilder gespeichert: PNG, JPEG oder
WebP bis 2 MiB und 3000 × 3000 Pixel, ohne öffentliche Mediathek-Adresse.
Eine Szenenzuordnung macht die Figur in der **privaten Regie des
Abenteuerplans** erreichbar. Sie veröffentlicht weder Beschreibung noch
Bild, Werte oder Geheimnisse für Spieler.

![Tessa Wolkensteg als private strukturierte NPC-Figur der Nebelhafen-Demo](images/27-gegner-npc.png)

### Ins Universum aufnehmen

Bei einer zugeordneten Kampagne besitzt die gespeicherte Figur eine
ausdrücklich zu bestätigende Aufnahmeaktion. **Dabei werden auch Werte,
Bild und Geheimnisse mit der Universumsleitung geteilt.**

- Leitet dieselbe Person auch das Universum, erstellt **Ins Universum
  aufnehmen** direkt eine Vorlage.
- Andernfalls erzeugt **Aufnahme ins Universum vorschlagen** zunächst einen
  privaten Vorschlag. Andere Kampagnen sehen ihn noch nicht.
- Die Universumsleitung öffnet **Universen → Regelwerk & Basischaraktere →
  Gegner & NPCs**, prüft den Vorschlag und bestätigt **Aufnahme ins Universum
  bestätigen**. Sie kann dort auch eigene Vorlagen erstellen.

Wiederholtes Anbieten derselben Figur erzeugt keine weitere Vorlage, auch
nicht nach einer Umbenennung. Spätere Änderungen werden nicht ungefragt
übertragen. Eine Kampagne ohne Universum behält ihre Figuren ausschließlich lokal.

![Bestätigte Universumsvorlage aus einer separaten temporären Prüfwelt](images/28-universumsvorlage.png)

### Vorlagen in einer anderen Kampagne verwenden

1. Als Spielleitung einer zugeordneten Kampagne die Figurenverwaltung öffnen.
2. Unter **Vorlagen aus dem Universum** die Vorlage prüfen.
3. **Als Kampagnenfigur übernehmen** ausdrücklich bestätigen.
4. Name, aktuelle Werte und Szenenverknüpfung der lokalen Figur anpassen.

Jede Übernahme friert den Vorlagenstand ein und kopiert das geschützte Bild.
Die Szenenzuordnung ist zunächst leer. Eine verletzte lokale Wache verändert
keine anderen Wachen; spätere Vorlagenänderungen verändern keine bestehenden
Kopien. Nach einer gelösten Universumszuordnung verschwindet der Katalogzugriff,
die eigenen Kampagnenfiguren bleiben erhalten.

Nach Änderungen oder Freigaben durch eine andere Spielleitung
**Figuren und Vorlagen neu laden** verwenden. Veraltete Speicherformulare
überschreiben keine neueren Stände. Ohne erforderlichen Protokolleintrag
wird die Figurenänderung nicht übernommen.

Die Demo enthält **Tessa Wolkensteg – Hafenlotsin** und die optionale
**Zollwache an Dock 3**. Beide sind Dock 3 zugeordnet und bleiben privat.
Es wird kein Kampf gestartet. Seit 0.18.0 ist eine lokale Beutetabelle
optional verknüpfbar. Die Verknüpfung wird nicht in Universumsvorlagen
übernommen. Für bestätigte Universumsvorlagen kann seit 0.19.0 ein separater
öffentlicher Wiki-Artikel vorbereitet werden.

## 18. Beutetabellen und bestätigte Vergabe

Seit Version 0.18.0 kann die Spielleitung Beute vorbereiten, auswürfeln und
anschließend bewusst vergeben. Das ist keine automatische Kampfbelohnung.
Die Verwaltung bleibt privat und benötigt für Änderungen aktives PS Community.

### Eine Tabelle vorbereiten

1. Als Kampagnenleitung **Spielleitermappe → Beutetabellen** öffnen und
   **Neue Beutetabelle** wählen.
2. Name und einen im Kampagnenregelwerk erlaubten Standardwürfel einstellen.
3. Für jeden Ausgang einen Bereich von/bis einschließlich und einen Namen
   eintragen. **Ausgang hinzufügen** ergänzt weitere Bereiche.
4. Optional eine Gegenstandsvorlage und Menge auswählen sowie einen
   Währungsbetrag eintragen. Ohne Gegenstand muss die Menge **0** sein.
5. **Beutetabelle speichern**.

Alle Würfelwerte müssen genau einmal vorkommen. Eine W6-Tabelle könnte
die Bereiche **1–2**, **3–4** und **5–6** enthalten: Jeder hat die Chance
**2/6**. Lücken, Überschneidungen und mehr als 30 Ausgänge werden abgelehnt.
Ein Ausgang kann keine Beute, einen Gegenstandstyp, Geld oder beides enthalten.

Mengen sind feste ganze Zahlen von **1 bis 100** oder ein erlaubter
Würfelausdruck wie **2W6**. Geld darf **0 bis 999999999** ergeben und benötigt
eine eingerichtete Kampagnen-/Universumswährung. Auch alle theoretisch
möglichen Würfelergebnisse müssen innerhalb der Grenzen liegen.
Nachkommastellen und negative Ergebnisse sind nicht erlaubt. **W3 ist derzeit
nicht unterstützt**; es gelten die vorhandenen Standardwürfel.
Weitere Ausgänge hinzufügen oder entfernen benötigt JavaScript.

Speichern friert die aktuellen Gegenstandsvorlagen samt Bindung ein.
Erneutes Speichern übernimmt den dann aktuellen Vorlagenstand für neue
Vorgänge, verändert aber keine bereits gespeicherten Beutevorschläge.

![Beutetabelle mit drei möglichen Ergebnissen in der Nebelhafen-Demo](images/29-beutetabelle.png)

### Auswürfeln ist noch keine Vergabe

1. Die gespeicherte Tabelle öffnen.
2. Einen privaten Anlass eintragen.
3. **Beutevorschlag auswürfeln** wählen.
4. Ergebnis, Menge, Geldbetrag und gegebenenfalls Gegenstandsbindung prüfen.
5. Einen **Empfänger der gesamten Beute** auswählen.
6. Die Bestätigung setzen und **Beute verbindlich vergeben** wählen.

Der Vorschlag enthält den damaligen Tabellenstand sowie sämtliche Einzelwürfe.
Neuladen, späteres Öffnen und eine Wiederholung derselben Anfrage würfeln
nicht neu. **Ein bewusst neu gestarteter Wurf ist dagegen ein neuer Vorgang.**
Bis zur bestätigten Vergabe verändern sich weder Inventar noch Guthaben.
Ein Ergebnis ohne Belohnung wird mit **Ohne Beute abschließen** abgeschlossen.

Die gesamte Vergabe wird gemeinsam gespeichert: konkrete Gegenstandsexemplare,
Guthaben, Abschluss und erforderliche Protokolle. Scheitert ein Teil,
beispielsweise wegen eines vollen Inventars, bleibt auch das Geld unvergeben.
Ein abgeschlossener Vorgang kann nicht erneut auszahlen.
Ausgegebene Gegenstände sind nicht automatisch ausgerüstet.

Die bisherigen Buchungsrechte gelten unverändert: Bei gemeinsamem
Universumsguthaben benötigt die vergebende Kampagnenleitung zusätzlich das
zuständige Universumsrecht. Veraltete Empfänger-/Guthabenstände werden nicht
überschrieben; bei einem Konflikt den Vorgang neu laden und erneut prüfen.

![Einmalig abgeschlossener Beutevorgang für Jorin](images/30-beutevergabe.png)

### Verknüpfung, Sichtbarkeit und Grenzen

Im Gegner-/NPC-Editor kann eine Kampagnen- oder Universumstabelle ausgewählt werden.
Der Link öffnet die Tabelle für die Spielleitung; er startet keinen Wurf und
keine automatische Auszahlung. Universumsvorlagen und daraus erstellte
Kampagnenkopien übernehmen diese lokale Tabellenreferenz nicht.

Tabellen, Chancen, private Anlässe und Vorschläge sehen nur Spielleitungen.
Charakterbesitzer sehen ihre erhaltenen Gegenstände und Guthabenbuchungen,
nicht die geheime Tabelle. Ohne aktives PS Community bleiben gespeicherte
Tabellen und Vorgänge erhalten; Änderungen sind gesperrt.

Die Universumsleitung pflegt gemeinsame **Beutetabellen** unter
**Universen → Regelwerk & Basischaraktere**.
Angeschlossene Kampagnen finden sie in ihrer Beuteverwaltung mit dem Hinweis
**Universumstabelle**. Die Kampagnenleitung kann diese Tabellen auswürfeln
und als Untertabellen oder Abenteuerbelohnungen verwenden, aber nicht
überschreiben. Eigene Kampagnentabellen bleiben separat bearbeitbar.
Im Universum selbst werden keine Vergaben an Kampagnencharaktere ausgeführt.
Bestehende Vorschläge behalten ihren eingefrorenen Stand, wenn die
Universumsleitung später Chancen oder Belohnungen ändert.
Es gibt keine automatische Währungsumrechnung; die bisherigen Kontorechte
gelten weiterhin. Vollständige Kampagnensicherungen enthalten auch den
Universumskatalog mit seinen verschachtelten Tabellen und gespeicherten Würfen.

Automatische Vergaben können
für freigegebene Abenteuerentscheidungen ausdrücklich eingerichtet werden.
In der Demo ist **Dock 3 – Fundkiste** mit der
Zollwache verknüpft. Der gespeicherte W6-Wurf **2** ergab **1 Dockmark**
für Jorin: Sein Guthaben stieg von **8 auf 9 DM**.

### Gegenstandsgruppen, Untertabellen und mehrere Empfänger

Ein Ausgang kann unter **Weitere Gegenstände** zusätzliche Vorlagen und Mengen
enthalten. **Untertabelle (ein zusätzlicher Wurf)** ergänzt einen weiteren
gespeicherten Tabellenwurf. Zyklen und überlange Verschachtelungen werden
abgelehnt; alle beteiligten Tabellenstände und Würfe bleiben eingefroren.

Bei der bestätigten Vergabe lassen sich die Mengen je Gegenstandskomponente
und der Geldbetrag auf mehrere Kampagnencharaktere verschiedener
Teilnehmer aufteilen. Alle ermittelten Mengen und Beträge müssen vollständig
zugeordnet werden. Die gesamte Aufteilung wird gemeinsam gespeichert oder
bei einem Fehler vollständig zurückgenommen.

Kampagnensicherungen erhalten auch diese Tabellen, Vorschläge und Aufteilungen.
Die Wiederherstellung ordnet Tabellen, Gegenstandsspeicher und Empfänger neu
zu, ohne Würfe zu wiederholen oder bereits vergebene Beute nochmals auszuzahlen.
Ein noch offener Vorschlag kann nach der Wiederherstellung ausdrücklich
vergeben werden. Die ursprüngliche Kampagne bleibt dabei unverändert.

## 19. Öffentliches Weltlexikon mit PS Wiki

Seit Version 0.19.0 kann jedes Universum ein optionales öffentliches
Regel-/Weltlexikon erhalten. PS Wiki muss auf derselben Website aktiv sein
und seine Kategorien bereitstellen. PS Community ist dafür nicht erforderlich.
**Das ist kein privates Kampagnen-Wiki.**

### Einmalig aktivieren und Kategorien festlegen

1. **Universen → Regelwerk & Basischaraktere → Öffentliches Weltlexikon**
   öffnen.
2. Unter **Öffentliche Kategorien und Anbindung** einen öffentlichen
   Lexikonnamen wählen. Der private Universumsname wird nicht vorausgefüllt.
3. Die sechs Unterkategorien **Personen, Gegner, Orte, Fraktionen, Gegenstände
   und Regeln** nach Wunsch benennen.
4. **Aktivieren** auswählen, die ausdrückliche Freigabe bestätigen und
   **Lexikonanbindung speichern** wählen.

Einrichten, Kategorien umbenennen und Pausieren benötigen neben den
Universumsrechten zusätzlich die nativen PS-Wiki-Rechte zum Veröffentlichen
und Verwalten von Kategorien. Fehlen diese, kann beispielsweise die
Administration die Einrichtung übernehmen. **Sie erlaubt damit ausdrücklich
der Universumsleitung, die zugeordneten Artikel dieses Lexikons über den
Pen-&-Paper-Adapter vorzubereiten und zu veröffentlichen.** Allgemeine
Wiki-Rechte oder Administratorrollen werden dadurch nicht vergeben.

Die Hauptkategorie und ihre sechs Unterkategorien entstehen automatisch
mit stabiler Zuordnung. **Ihre Namen sind sofort öffentlich sichtbar,
auch wenn noch kein Artikel veröffentlicht ist.** Kategorien schützen keine
Geheimnisse. Zusätzliche freie Kategorieebenen sind noch nicht vorgesehen.

![Ausdrückliche Einrichtung des öffentlichen Lexikons durch die Administration](images/31-wiki-einrichtung.png)

### Einen Artikel bewusst vorbereiten

1. Als Universumsleitung **Neuen Artikel vorbereiten** wählen.
2. Optional eine **bestätigte Universumsvorlage** auswählen. Vorgeschlagene
   und noch nicht aufgenommene Kampagnenfiguren können nicht verknüpft werden.
   Für Orte, Fraktionen oder Hausregeln **Freier Welt-/Regelartikel** verwenden.
3. Kategorie sowie einen separaten **öffentlichen Artikeltitel** und
   **öffentlichen Artikeltext** eintragen.
4. Die Bestätigung setzen und **Artikelentwurf vorbereiten** wählen.

Es werden keine internen Texte vorausgefüllt. Geheimnisse, private Identitäten,
Bilder, Eigenschaften, Zahlenwerte und Beuteinformationen werden nicht
automatisch übertragen. **Nur die separat eingegebenen Titel und Texte**
werden für den Artikel verwendet. Der Text ist reiner Text; Shortcodes werden
nicht ausgeführt.

Beim ersten Vorbereiten entsteht ein nativer PS-Wiki-Entwurf, noch kein über
diesen Ablauf veröffentlichter Artikel. Trotzdem niemals Geheimnisse eintragen:
**Wiki-Redakteure können Entwürfe mit den eigenen Wiki-Rechten lesen, ändern
oder außerhalb dieses Ablaufs veröffentlichen.**

Jede Figur kann genau einen solchen Artikeleintrag erhalten. Umbenennen der
Figur oder erneutes Anbieten erzeugt keine zweite Zuordnung. Zum Weiterarbeiten
den vorhandenen Eintrag unter **Zugeordnete Artikel** öffnen.
Eine Regelbeschreibung im Wiki verändert keine überprüfbaren Spielregeln.

### Veröffentlichen und später ändern

1. Den gespeicherten öffentlichen Titel und Text prüfen.
2. Die eigene Veröffentlichungsbestätigung setzen.
3. **Artikel öffentlich veröffentlichen** wählen.
4. Über **Öffentlichen Wiki-Artikel öffnen** die tatsächliche Gastansicht prüfen.

Veröffentlicht wird genau der gespeicherte Stand. Ein veraltetes oder
wiederholtes Freigabeformular wird abgelehnt. Neue vorbereitete Änderungen
lassen die bisherige öffentliche Fassung unverändert; erst die nächste
bewusste Freigabe ersetzt sie.

Kategorien, Artikel, Zuordnungen und erforderliche Protokolle werden gemeinsam
gespeichert oder zurückgenommen. Dazu müssen auch die beteiligten
WordPress-Taxonomietabellen InnoDB unterstützen. Native Wiki-Benachrichtigungen
werden erst nach erfolgreichem Abschluss ausgelöst.

![Freigegebener öffentlicher Artikel für Tessa, verwaltet durch die Universumsleitung](images/32-wiki-freigabe.png)

### Redaktionelle Konflikte, Pausieren und Grenzen

Ändert jemand den Artikel direkt in PS Wiki, überschreibt der Adapter diese
Ergänzungen nicht. Stattdessen erscheint ein Konflikthinweis; den Artikel
anschließend direkt im Wiki weiterpflegen. Ein automatischer Wiederabgleich
oder eine Zusammenführung ist noch nicht vorhanden. Dasselbe Schutzprinzip
gilt für außerhalb der Integration veränderte Kategorien.

**Pausieren nimmt keine Veröffentlichung zurück.** Kategorien, veröffentlichte
Artikel und deren frühere Wiki-Revisionen bleiben bestehen. Auch Deaktivieren
von PS Wiki oder Lösen einer Kampagnenzuordnung entfernt keine bereits
veröffentlichten Inhalte. Inhalte im Wiki nach dessen eigenen Möglichkeiten
verwalten; Veröffentlichung ist kein Weg, Geheimnisse zwischenzuspeichern.

Ohne aktives PS Wiki bleiben gespeicherte Zuordnungen und die gesamte
Pen-&-Paper-Runde erhalten; Wiki-Änderungen sind gesperrt. Private
Kampagnenleserechte, automatische Übernahme aller Figuren, zusätzliche
Kategorieebenen und Verknüpfungen zu einzelnen Szenen/Feldern folgen später.

Die öffentliche Demo **Tessa Wolkensteg – Hafenlotsin** gehört zum getrennten
**Nebelhafen – Wiki-Demouniversum**. Die bestehende Nebelhafen-Kampagne mit
ihren Charakteren und Guthaben wurde dafür nicht umgehängt.

## 20. Geschenke, Verkauf und Tausch

Seit Version 0.20.0 gibt es kontrollierten Handel mit aktivem **PS Community**.
Die Regeln legt die Spielleitung fest, nicht das Plugin für jede Runde gleich.

### Handelsregeln einstellen

- **Ohne Universum:** **Rundeneinstellungen → Handelsregeln** öffnen.
- **Mit Universum:** Die Universumsleitung öffnet **Universen → Regelwerk &
  Basischaraktere → Handelsregeln**. Kampagnen übernehmen diese Regeln
  unverändert und können sie nicht lokal überschreiben.
- **Geschenk**, **Verkauf**, **Tausch** und **Geldgeschenk** einzeln erlauben
  oder ausschalten. Geldgeschenke werden nicht automatisch mit aktiviert.
- Unter **Nach Zustimmung beider Besitzer** zusätzliche Spielleiterfreigabe
  verlangen oder den direkten Abschluss wählen.
- **Handelsregeln speichern**.

Standardmäßig sind **alle Arten ausgeschaltet** und die zusätzliche Freigabe
ist voreingestellt. Jede gespeicherte Regeländerung macht bisher offene
Angebote nicht mehr ausführbar, auch wenn dieselben Arten gewählt bleiben.
Diese Angebote zuerst zurückziehen oder ablehnen und bei Bedarf neu erstellen.

![Unabhängig aktivierte Handelsarten und zusätzliche Spielleiterfreigabe in der Demo](images/33-handelsregeln.png)

### Exemplare oder Geld anbieten

1. Im Spielbereich den **eigenen Charakterbogen** öffnen, alternativ den
   eigenen privaten **Pen-&-Paper-Profilbereich**.
2. Unter **Handel → Neues Angebot** die erlaubte Handelsart wählen.
3. Eigene **handelbare, abgelegte Exemplare** auswählen, höchstens 100.
   Beim **Geldgeschenk** keine Exemplare auswählen. Gebundene,
   bereits reservierte oder aktiv/in einem Ausrüstungsantrag angelegte
   Exemplare können nicht angeboten werden.
4. Einen freigegebenen Charakter eines **anderen Besitzers derselben
   Kampagne** wählen. Nur dessen freigegebener Name wird zur Auswahl gezeigt,
   nicht Bogenwerte, Inventar oder Guthaben.
5. Beim **Verkauf** einen ganzen **Gesamtpreis für alle ausgewählten
   Exemplare** ab 1 eintragen. Beim **Geldgeschenk** einen ganzen Betrag ab 1
   eintragen; der Anbieter zahlt an den Empfänger. Gegenstandsgeschenk und
   Tausch verwenden **0**. Es gilt die konfigurierte Währung.
6. Angebot und Bedingungen bestätigen und **Handelsangebot erstellen** wählen.

Angebotene Exemplare sind bis zum Abschluss oder zur Ablehnung reserviert
und können nicht angelegt oder nochmals angeboten werden. **Geld wird nicht
reserviert:** Das tatsächliche Guthaben wird erst beim Abschluss geprüft.

### Zustimmen und abschließen

- **Geschenk/Verkauf/Geldgeschenk:** Der Empfänger öffnet den eigenen Bogen,
  prüft die gespeicherten Exemplare beziehungsweise den Betrag und nimmt
  verbindlich an.
- **Tausch:** Der Empfänger wählt zunächst seine eigenen zulässigen
  Gegenexemplare und bestätigt dieses Gegenangebot. Anschließend muss der
  ursprüngliche Anbieter den jetzt vollständig bestimmten Tausch bestätigen.
- Ist zusätzliche Spielleiterfreigabe eingestellt, bleibt der Vorgang
  **Wartet auf Spielleiterfreigabe**. Erst die Spielleitung bestätigt am
  betreffenden Bogen **Handel freigeben und abschließen**.
- Ohne zusätzliche Freigabe erfolgt der Abschluss nach der letzten nötigen
  Besitzerzustimmung. Die Spielleitung kann diese Zustimmung nicht ersetzen.

**Abgeschlossen** bedeutet: Gegenstände, gegebenenfalls Geld und alle nötigen
Protokolle sind gemeinsam gespeichert. Schlägt etwas fehl, bleibt nichts
teilweise übertragen. Volle Inventare, fehlendes Guthaben, entzogene
Mitgliedschaft oder geänderte Bedingungen führen zu einem ausdrücklichen Fehler.
Die Gegenstands-UUID und der eingefrorene Stand bleiben erhalten; auch offene
Ausrüstungsanträge werden angepasst, damit eine spätere Freigabe nichts
verdoppelt oder zurückholt. Empfangene Exemplare bleiben abgelegt.
Verkäufe und Geldgeschenke verwenden je nach Kontomodell getrennte Kampagnenkonten oder die
ausdrücklich eingebundenen gemeinsamen Basisguthaben, keine Ersatzkonten.

Besitzer sehen ihre betreffenden Angebote und Abschlüsse; die Spielleitung
sieht die Kampagnenvorgänge und deren zentrale Historie. Weitere Inventarteile
und fremde Kontostände bleiben privat. Veraltete Abschlussformulare und
Neuladen übertragen nichts ein zweites Mal.

### Abbrechen, Grenzen und Demo

Der Anbieter kann ein offenes Angebot **zurückziehen**, der Empfänger
**ablehnen**, die Spielleitung **als Spielleitung ablehnen**. Das löst die
Reservierungen, auch wenn inzwischen die Handelsregeln geändert wurden.
Ohne Community bleiben Angebote und Nachweise gespeichert und im Spielbereich
lesbar; alle wirtschaftlichen Änderungen einschließlich Handelsentscheidungen
sind inaktiv.

Es gilt **höchstens 100 konkrete Exemplare je Seite, dieselbe Kampagne,
unterschiedliche Besitzer** für den Spielerhandel.

In der Nebelhafen-Demo verkauft Mara den **Messingkompass vom Dock** für
**3 DM** an Jorin. Beide Besitzer stimmen zu; die Demo-Spielleitung gibt frei.
Mara besitzt anschließend **13 DM**, Jorin **6 DM** und genau dasselbe
Kompass-Exemplar. Der gebundene Bordausweis bleibt bei Mara. Der alte
Beschreibungstext des ausgegebenen Kompasses bleibt bewusst unverändert.

![Abgeschlossener Verkauf mit konkretem Exemplar, drei Dockmark und bestätigter Historie](images/34-handel-abschluss.png)

Weiterlesen: [Demorunde mit Ergebnissen und Prüfbericht](demokampagne.md) ·
[Technischer Überblick](../README.md) · [Roadmap](../ROADMAP.md).

### Kontrollierte Übertragungen innerhalb eines Universums

1. Die Universumsleitung öffnet **Universen → Regelwerk & Basischaraktere →
   Handelsregeln** und aktiviert **Kontrollierte Übertragungen zwischen
   Kampagnen dieses Universums erlauben**. Einstellungen speichern.
   Standardmäßig ist diese Freigabe ausgeschaltet.
2. Als für **beide Kampagnen** berechtigte Spielleitung oder Administration
   den Quellcharakter öffnen.
3. Unter **In eine andere Kampagne dieses Universums übertragen** konkrete
   handelbare, abgelegte Exemplare auswählen. Gebundene, reservierte oder
   wirksam/beantragt angelegte Exemplare sind gesperrt.
4. **Zielkampagne und Empfänger** wählen. Auch eigene Figuren in einer
   anderen Kampagne desselben Universums sind zulässig.
5. Den **Geldbetrag** eintragen: **0** für reine Gegenstandsübertragung.
   Für reine Geldübertragung keine Exemplare auswählen.
6. Auswahl, Betrag und Empfänger ausdrücklich bestätigen und
   **Übertragung bestätigen** wählen.

Die Formulare zeigen nur Ziele, die ebenfalls von dir verwaltet werden und
zum selben Universum gehören. Eine allein für die Quellkampagne berechtigte
Spielleitung kann nicht einseitig in fremde Kampagnen übertragen.
Spieler können diesen Verwaltungsweg nicht selbst auslösen.

Gegenstandsidentitäten und eingefrorene Werte bleiben erhalten. Lokale
geschützte Bilder werden in die Zielkampagne kopiert. Auch offene
Ausrüstungsanträge werden angepasst, damit Freigaben nichts zurückholen oder
verdoppeln. Enthaltene Bonusfelder müssen zu den Zielregeln passen.

Geldübertragungen verwenden ausschließlich dieselbe Universumswährung und
die bisherigen Kontorechte, auch bei tatsächlichen Basisguthaben.
Lokale Währungen werden nicht umgerechnet. Zwei Bögen desselben gemeinsamen
Basiskontos brauchen keine Übertragung: Ein solcher Geldvorgang wird abgewiesen,
nicht als zweites Guthaben ausgezahlt.

Inventare, Geld und Protokolle werden vollständig zusammen gespeichert oder
zurückgerollt. Reine Geldübertragungen ändern keine Inventare. Veraltete
Formulare und geänderte Freigaben werden abgewiesen; erneutes Absenden zahlt
nicht doppelt. Der Wiederholungsschutz bleibt in nativen Sicherungen erhalten.
**Übertragungen zwischen getrennten Universen sind ausgeschlossen.**

## 21. Soloabenteuer

Seit Version **0.21.0** kann die Spielleitung aus freigegebenen Szenen ein
kleines, individuell gespieltes Abenteuer vorbereiten. Jeder Charakter erhält
einen eigenen privaten Durchlauf. Dieser Ablauf ist eine Ergänzung zum
gemeinsam geleiteten Spieltisch, kein automatisches Regelwerk und keine
Änderung an der aktiven Gruppenszene.

### Abenteuer vorbereiten und freigeben

1. Öffne als Spielleitung **Abenteuer & Szenen** und bearbeite die gewünschten
   Szenen wie gewohnt. Soloabenteuer dürfen ausschließlich auf Szenen
   verweisen, die regulär freigegeben sind.
2. Unter **Soloabenteuer** einen Titel und eine freigegebene Startszene
   auswählen und den privaten Entwurf speichern.
3. Für jede Entscheidung einen sichtbaren Entscheidungstext und eine
   freigegebene Folgeszene festlegen. Alternativ kann die Entscheidung den
   Durchlauf beenden.
4. Optional eine Probe mit einem im Kampagnenregelwerk erlaubten
   Würfelausdruck, einem Zahlenattribut oder einem festen Zahlenwert und
   Zielwert konfigurieren. Erfolgs- und Misserfolgsfolgen müssen jeweils auf
   eine freigegebene Szene oder das Ende verweisen.
5. Den Entwurf prüfen und **Soloabenteuer freigeben**. Das Freigeben speichert
   einen eigenen Szenen- und Entscheidungsstand samt Regelrevision. Neue oder
   geänderte Entwürfe werden erst nach ausdrücklicher erneuter Freigabe für
   neu begonnene Durchläufe verwendet. Bereits freigegebene Würfelproben
   bleiben in laufenden Durchläufen zulässig, auch wenn die aktuellen
   erlaubten Würfel später geändert werden.

Die aktuelle Umsetzung ist absichtlich begrenzt: höchstens 30 Szenen und
100 Entscheidungen pro Abenteuer, ohne Zyklen. Proben werten nur die gespeicherten erlaubten
Würfelgruppen und Zahlenattribute aus. Es gibt keine freie Skriptsprache,
keine frei programmierten Effekte. Ressourcen werden nur durch ausdrücklich
konfigurierte Kosten, Bestände nur durch konfigurierte Belohnungen verändert.

### Mindestwerte für Entscheidungen

Seit **0.22.0** kann eine Entscheidung zusätzlich eine Voraussetzung besitzen:

1. Unter **Entscheidung oder Probe ergänzen → Optionale Wertebedingungen**
   ein Zahlen- oder Attributfeld wählen. Seit 0.25.0 ersetzt dieser Bereich
   die einfache Mindestwerteingabe für neue Entscheidungen.
2. Den Vergleich **mindestens (≥)** und einen ganzzahligen **Vergleichswert**
   zwischen −1000000 und 1000000 wählen. **Keine Bedingung** mit Vergleichswert
   0 lässt eine Zeile ungenutzt; ohne ausgewählte Felder gibt es keine Bedingung.
   Für eine Entscheidung ohne Würfelprobe den Würfelausdruck und das
   Probe-Attribut leer lassen, den Erfolgszielwert auf 0 lassen und bei
   Misserfolg **Abenteuer beenden** wählen.
3. Entscheidung speichern und den Abenteuerentwurf ausdrücklich freigeben.
   Begonnene Durchläufe behalten ihre bisherigen Entscheidungen.

Die Voraussetzung ist unabhängig von der optionalen Würfelprobe. Beispiel:
**Tür aufbrechen** erfordert Stärke mindestens 5; erst dann darf die
Entscheidung gewählt und gegebenenfalls gewürfelt werden.
Der aktuelle freigegebene Charakterwert einschließlich wirksamer Ausrüstung
zählt, nicht ein offener Änderungsantrag. Die ältere einfache Mindestwertstruktur
gilt nur für Zahlen-/Attributfelder; die neuen Wertebedingungen unterstützen
zusätzlich Ressourcen. Die Prüfung selbst verbraucht nichts.

Bei einem zu niedrigen Wert bleiben Entscheidung, Voraussetzung und aktueller
Wert sichtbar, aber die Schaltfläche ist gesperrt. Nach einer Wertefreigabe
den Bogen neu laden. Auch ein zuvor geöffnetes, noch auswählbares Formular
wird serverseitig abgewiesen, wenn der Wert inzwischen zu niedrig ist.
Sind alle Entscheidungen gesperrt, bleibt der Durchlauf stehen; das Plugin
erfindet keine Ausweichentscheidung und senkt keine Mindestwerte.

Eine erfolgreiche Entscheidung speichert Feld, damaligen Wert und Mindestwert
gemeinsam mit dem Fortschritt und Pflichtereignis. Spätere Werteänderungen
schreiben diesen privaten Nachweis nicht um. Ältere Abenteuer, Durchläufe
und Ereignisse ohne Voraussetzungen bleiben lesbar und unverändert.

### Hybride Entscheidungen mit Spielleiterfreigabe

Unter **Entscheidung oder Probe ergänzen** kann die Spielleitung
**Diese Entscheidung benötigt ausdrücklich eine Spielleiterfreigabe**
aktivieren. Nach dem Speichern den Abenteuerentwurf wie gewohnt freigeben.

Der Besitzer wählt die Entscheidung zunächst als Anfrage. Sie erscheint unter
**Abenteuer & Szenen → Ausstehende Abenteuerentscheidungen** bei der Spielleitung,
die ausdrücklich freigibt oder ablehnt. Bis dahin bleiben Fortschritt,
Ressourcen und Belohnungen unverändert; auch die Probe wird noch nicht gewürfelt.
Nach Freigabe lädt der Besitzer seinen Bogen neu und führt die Entscheidung aus.
Voraussetzungen und aktuelle Berechtigungen werden dabei erneut geprüft.

Der Besitzer kann eine Anfrage oder ungenutzte Freigabe zurückziehen und erneut
anfragen. Das ermöglicht auch eine neue Prüfung nach entzogenen
Spielleiterrechten. Erfolgreiche Ausführung verbraucht die Freigabe genau einmal
und dokumentiert sie privat im Verlauf. Schlägt die Ausführung fehl, bleiben
Freigabe und Ressourcen erhalten. Import und Wiederherstellung übernehmen diese
Zustände samt ausdrücklicher Benutzerzuordnung; sie führen keine Entscheidung aus.
Gruppendurchläufe beachten dieselbe ausdrücklich konfigurierte Freigabepflicht.

### Gemeinsame und hybride Gruppenabenteuer

1. Bereite das Abenteuer wie oben unter **Abenteuer & Szenen → Soloabenteuer**
   vor und gib es frei. Diese Szenen- und Entscheidungsdefinition wird auch
   für Gruppen verwendet; ein bestehender Solodurchlauf wird nicht umgewandelt.
2. Öffne **Abenteuer → Gruppendurchlauf anlegen**, vergib einen Namen
   und wähle die Entscheidungsregel: ein Teilnehmer, Mehrheit der aktiven
   Teilnehmer, alle aktiven Teilnehmer oder ausdrückliche Spielleiterfreigabe.
3. Jeder Besitzer wählt eine eigene freigegebene Figur und betätigt
   **Teilnehmen / wieder einsteigen**. Pro Benutzer kann eine Figur zugleich
   aktiv teilnehmen; bis zu 100 Figuren einschließlich früherer Teilnehmer
   bleiben zugeordnet. Vor eigener Teilnahme sieht ein normales Kampagnenmitglied
   keine Gruppenszenen oder Historie.
4. Ein Teilnehmer schlägt eine Entscheidung mit seiner Figur vor. Der Vorschlag
   zählt als eigene Zustimmung; andere stimmen zu oder ziehen ihre Zustimmung
   zurück. Bei Mehrheit müssen mehr als die Hälfte der aktiven Teilnehmer
   zustimmen, bei Einstimmigkeit alle. Eine für die Entscheidung zusätzlich
   konfigurierte Spielleiterfreigabe bleibt in jedem Modus verpflichtend.
5. Sobald Zustimmung und gegebenenfalls Freigabe vorliegen, führt ausschließlich
   der vorgeschlagene Besitzer **Vereinbarte Gruppenentscheidung ausführen** aus.
   Erst jetzt prüft der Server alle Bedingungen und Berechtigungen erneut,
   würfelt, verbraucht persönliche Ressourcen und vergibt persönliche Belohnungen.
   Fehler lassen weder Teilfortschritt noch Teilverbrauch zurück.
6. Alle Teilnehmer sehen dieselbe Folgeszene und den gemeinsamen Verlauf. Die
   nächste Entscheidung darf eine andere Figur ausführen und voraussetzen,
   dass eine frühere Gruppenentscheidung tatsächlich ausgeführt wurde.

Der gemeinsame Fortschritt gehört diesem Durchlauf, nicht der allgemeinen
Kampagnenszene oder anderen Gruppen. Fremde Zahlen-, Text- und Ressourcenwerte
werden nicht an andere Teilnehmer ausgeliefert; die Spielleitung behält ihre
bestehenden Einsichtsrechte. Gruppenereignisse mit persönlichen Nachweisen
bleiben spielleiterprivat.

**Teilnahme beenden**, **Gruppendurchlauf pausieren**, Wiedereinstieg und Änderungen
der Entscheidungsregel erhalten Szene und Historie, verwerfen aber offene
Vorschläge, Stimmen und Freigaben. Die Spielleitung kann auch ungültig gewordene
Teilnahmen beenden. Pausierte Durchläufe erlauben Wiedereinstieg, aber keine
Entscheidungen. Nach **Gruppendurchlauf fortsetzen** kann die Gruppe weitergehen.
Auch das Verlassen aller Teilnehmer löscht den bisherigen Fortschritt nicht.

Import und Wiederherstellung übernehmen laufende Gruppen, Teilnehmer,
historische Wählerschaft, Stimmen und Freigaben mit ausdrücklichen Benutzer-
und Objektzuordnungen. Sie würfeln nicht und zahlen keine Belohnung erneut aus.
Benutzerzusammenlegungen mit Doppelstimmen oder kollidierenden aktiven
Teilnahmen werden abgewiesen. Nach der Übernahme zählen die aktuellen Rechte
der örtlichen Spielleitung und tatsächlichen Besitzer.

Kosten und Belohnungen betreffen ausschließlich die jeweils ausführende Figur.
Gegenstandsbedingungen, frei gesetzte Abenteuerzustände und Begegnungsphasen
ergänzen die vorbereiteten Entscheidungen.

### Abenteuer ohne Dateiübernahme spielen

Spielleitung/Admin stellt Universen, Kampagnen und Abenteuer auf der Website
bereit und legt Regeln, Spielmodus, Teilnahme und Freigaben fest.
Spieler öffnen ihre Kampagne und nutzen die dort freigegebenen Abenteuer mit
ihrem Charakter. Eine separate Paketbibliothek und Abenteuerdatei-Importe oder
-Downloads gehören nicht zum Spielablauf.

### Kapitel, Zugang und Wiederholungsbelohnungen

Unter **Abenteuer & Szenen → Abenteuer und Szenarien vorbereiten** legt die
Spielleitung die Szenarioeinstellungen fest und gibt den gespeicherten Entwurf
anschließend ausdrücklich frei.

- **Lose Szenarien:** Kapitel und Vorgänger leer lassen. Die Anzeigeordnung
  sortiert nur die Liste und erzwingt keinen Spielablauf.
- **Kapitelgeschichte:** Optional ein Kapitel der Spielleitermappe zuordnen.
  Für Kapitel 2 beispielsweise Kapitel 1 als Voraussetzung mit **gewonnen**
  auswählen. **Abgeschlossen** akzeptiert auch ein verlorenes oder neutrales
  Ende. Nur der ausgewählte Kapiteltitel wird für Spieler sichtbar, nicht
  der private Kapiteltext.
- **Mindestwert:** Ein eigenes Zahlen- oder Attributfeld, etwa Stufe oder Rang,
  und dessen Mindestwert wählen. Es gibt kein fest eingebautes Levelsystem.
- **Spielweise:** Einzelspiel, Gruppenspiel oder beides zulassen. Die vorhandene
  Spielleiterfreigabe bleibt für hybride Entscheidungen verfügbar.
- **Wiederholung:** Erlauben oder sperren. Belohnungen nur im ersten Durchlauf,
  in jedem Durchlauf gleich oder ab Wiederholung anders vergeben. Für den
  letzten Fall pro Entscheidung eine alternative Belohnung einrichten;
  ohne Alternative gibt es dort ab dem zweiten Durchlauf keine Belohnung.

Bei einer Entscheidung bestimmt die Spielleitung das Ergebnis für Erfolg
und Misserfolg getrennt: gewonnen, verloren oder neutral. Es zählt erst, wenn
der Durchlauf endet; ein bestandener Würfelwurf allein ist kein Szenariosieg.
Spieler sehen Voraussetzungen und die tatsächlich vorgesehene Belohnung vor
dem Versuch. Am Spieltisch führt **Abenteuer** zum eigenen Charakterdurchlauf
oder zu den gemeinsamen Durchläufen.

**Wichtig:** Der erste gestartete beziehungsweise erstmals beigetretene
Durchlauf zählt je Spielerkonto und Szenario, nicht je Figur. Auch ein verlorener
erster Durchlauf verbraucht die Erstbelohnungsberechtigung. Ein anderer Charakter,
ein Wechsel zwischen Einzel- und Gruppenspiel oder erneutes Veröffentlichen
setzt den Zähler nicht zurück. Ein Wiedereinstieg in dieselbe Gruppe zählt nicht
nochmals. Die Belohnungsrichtlinie gilt für alle Entscheidungen des jeweiligen
Durchlaufs; sie ersetzt nicht die bestehende Vergaberegel pro Entscheidung.
Gemeinsame Belohnungen erhält weiterhin nur die ausführende Figur.

Neue über den Editor angelegte Szenarien beginnen ohne Wiederholung und mit
Erstbelohnung. Bestehende Abenteuer behalten ihr bisheriges Verhalten, bis die
Spielleitung Szenarioeinstellungen ausdrücklich speichert und veröffentlicht.
Neue Fassungen verändern weder laufende Durchläufe noch frühere Vergaben.

### Mehrere Wertebedingungen mit UND oder ODER

Seit **0.25.0** kann jede Entscheidung bis zu 20 Wertebedingungen erhalten.
Die Spielleitung entscheidet unter **Bedingungen verknüpfen**, ob **alle**
erfüllt sein müssen (UND) oder **mindestens eine** genügt (ODER).
Mit **Weitere Bedingung** lassen sich zusätzliche Zeilen ergänzen und wieder
entfernen. Ohne JavaScript stehen zwei Eingabezeilen zur Verfügung.

Jede Zeile wählt ein Feld, den Wertbestandteil, einen Vergleich und einen
Vergleichswert. Zahlenvergleiche verwenden ganze Zahlen von −1000000 bis 1000000.
Textfelder unterstützen **gleich**, **ungleich**, **enthält Text** und
**enthält Text nicht** mit bis zu 2000 Zeichen; Groß-/Kleinschreibung wird
beachtet. **Vorherige Entscheidung** prüft ausschließlich den eigenen Durchlauf:
1 bedeutet ausgeführt, 0 bedeutet nicht ausgeführt. Die Entscheidung muss bereits
im Entwurf existieren; der gespeicherte Nachweis wird später nicht umgeschrieben.
Für Zahlen und Ressourcen stehen die bisherigen Vergleiche zur Verfügung:

- Zahlen und Attribute: den aktuellen freigegebenen wirksamen Wert verwenden.
- Ressourcen: **aktuellen Wert** oder persönliches **Maximum** wählen.
- Vergleiche: **mindestens (≥)**, **größer als (>)**, **höchstens (≤)**,
  **kleiner als (<)**, **gleich (=)** oder **ungleich (≠)**.

Beispiele: Stärke mindestens 6 **UND** Ausdauer aktuell mindestens 3;
oder Verstand mindestens 8 **ODER** Geschicklichkeit mindestens 7.
Offene Werteanträge zählen nicht. Aktive Ausrüstungsboni wirken auf Zahlen-
und Attributbedingungen genauso wie auf Proben. Ressourcenprüfungen ziehen
keine Punkte ab; etwaige Kosten werden separat und erst danach abgerechnet.
Eine zusätzlich vorhandene ältere Mindestwert-Voraussetzung muss unabhängig
von der neuen UND-/ODER-Gruppe ebenfalls erfüllt sein.

Alle Bedingungen bleiben mit freigegebenem Wert und Vergleichsergebnis
sichtbar. Bei ODER dürfen einzelne Bedingungen unerfüllt sein, solange eine
andere genügt. Ist die gesamte Gruppe nicht erfüllt, bleibt die Entscheidung
gesperrt; ein veraltetes Formular kann die erneute Serverprüfung nicht umgehen.
Es entstehen dabei weder Kosten, Belohnungen noch Fortschritt.

Der private Verlauf speichert die Gruppe mit allen damaligen Werten und
Einzelergebnissen, auch die unerfüllten Bedingungen einer erfolgreichen
ODER-Gruppe. Neufreigaben ändern laufende Durchläufe nicht; ältere Durchläufe
werden nicht migriert. Text-, Gegenstands- und verschachtelte Bedingungsgruppen
sind nicht Teil dieses Ausbaus.

### Ressourcenkosten pro Entscheidung

Seit **0.23.0** können Entscheidungen unabhängig von Mindestwerten und Proben
optionale Kosten erhalten. Die Spielleitung legt die Spielregel je Entscheidung
fest; keine Ressource oder feste Verbrauchsregel wird vorgegeben.

1. Unter **Entscheidung oder Probe ergänzen** bei **Optionale Ressourcenkosten**
   ein Ressourcenfeld wählen, beispielsweise Mana, Ausdauer oder Vorräte.
2. Positive ganzzahlige Kosten bis 1000000 angeben. Ohne Ressource den Betrag
   auf **0** lassen; dann verändert die Entscheidung keine Ressourcen.
3. Unter **Wann werden Kosten abgezogen?** wählen:
   - **Bei jedem ausgeführten Versuch, auch bei Misserfolg**: Eine misslungene
     Probe kostet genauso viel wie eine erfolgreiche.
   - **Nur bei Erfolg; ohne Würfelprobe immer**: Eine misslungene Probe
     verbraucht nichts. Eine Entscheidung ohne Probe bezahlt die Kosten immer.
4. Entscheidung speichern und den Abenteuerentwurf ausdrücklich freigeben.
   Bereits begonnene Durchläufe behalten ihren gespeicherten Kostenstand.

Die Ressource muss **vor jedem Versuch** reichen, auch bei erfolgsabhängigen
Kosten. Es gibt keine Überziehung: Der Rest darf weder unter 0 noch unter
die konfigurierte positive Ressourcen-Untergrenze fallen. Zu teure
Entscheidungen bleiben mit Betrag, Bestand und Kostenzeitpunkt sichtbar,
aber gesperrt; auch veraltete Formulare werden serverseitig erneut geprüft.

Der erste Ausbau unterstützt **eigenständige lokale Bögen und unabhängige
Kampagnenkopien**. Eine Kopie verbraucht nur ihren eigenen Bestand, nicht den
Universums-Basisbogen. Kostenpflichtige Versuche mit gemeinsamen Basisbögen
sind vorläufig gesperrt; kostenfreie Alternativen bleiben spielbar.
Ein offener Werteantrag muss vor einem kostenpflichtigen Versuch freigegeben
oder abgelehnt werden, damit er bereits abgezogene Kosten nicht später ersetzt.

Verbrauch verändert nur den aktuellen Wert der gewählten Ressource und die
Charakterrevision. Maximum, übrige Werte, Inventar und Basisverbindung bleiben
unverändert. Verbrauch, Durchlauf und private Pflichtereignisse werden gemeinsam
gespeichert: Scheitert eine Pflichtänderung, werden weder Kosten noch Fortschritt
übernommen. Wiederholtes Absenden zieht nichts ein zweites Mal ab.
Der Verlauf hält die damaligen Kosten und Vorher-/Nachherwerte fest; bei einer
misslungenen erfolgsabhängigen Probe zeigt er ausdrücklich den Nichtverbrauch.
Ältere kostenfreie Abenteuer und Durchläufe bleiben unverändert nutzbar.

### Belohnungen pro Entscheidung

Seit **0.24.0** kann die Spielleitung jede Entscheidung unabhängig von
Voraussetzung, Probe und Kosten mit einer optionalen Belohnung versehen.
Gegenstände und Währung benötigen **PS Community**; der erzählerische Ablauf
ohne Belohnungen bleibt davon unabhängig.

1. Unter **Optionale Abenteuerbelohnung → Belohnungsart** wählen:
   - **Keine Belohnung**: Alle zusätzlichen Auswahlen/Beträge auf 0 lassen.
   - **Feste Gegenstände und/oder Währung**: Einen Kataloggegenstand mit
     positiver Anzahl bis 100 und/oder einen ganzzahligen Währungsbetrag bis
     999999999 wählen. Ohne Gegenstand die Anzahl auf 0 lassen; keine Tabelle wählen.
   - **Vorhandene Beutetabelle**: Eine Tabelle der Kampagne wählen. Feste
     Gegenstandsauswahl, Anzahl und Währungsbetrag auf 0 lassen. Die Tabelle
     liefert den Ausgang und ihre bereits konfigurierten Mengen-/Geldwürfe.
2. Den Auslöser je Entscheidung festlegen: **Nach jeder ausgeführten
   Entscheidung, auch bei Misserfolg** oder **Nur bei Erfolg; ohne
   Würfelprobe immer**. Eine misslungene erfolgsabhängige Probe vergibt nichts.
3. Entscheidung speichern und das Abenteuer ausdrücklich freigeben.
   Eine Belohnung an der letzten Entscheidung kann den Abenteuerabschluss belohnen.

Beim Speichern der Entscheidung wird der gewählte Gegenstands- oder
Tabellenstand eingefroren, einschließlich Gegenstandsbindung und Währungsbezug.
Spätere Vorlagen-/Tabellenänderungen schreiben weder laufende Durchläufe noch
deren Vergaben um. **Mit der Abenteuerfreigabe wird der gewählte Stand Teil des
für berechtigte Spieler lesbaren Soloabenteuers.** Keine vertraulichen
Tabellendaten dafür auswählen; andere Tabellen und manuelle Beutevorgänge
bleiben privat.

Vor jedem Versuch müssen das aktive und gegebenenfalls beantragte Inventar
sowie das Guthaben **jedes mögliche Ergebnis** aufnehmen können. So wird eine
Tabelle bei vollem Inventar nicht so lange neu gewürfelt, bis ein kleineres
Ergebnis passt. Eine gesperrte Entscheidung bleibt mit Belohnungsregel und
Fehlermeldung sichtbar. Belohnungen werden ins Inventar gelegt, nicht ausgerüstet;
ein offener Ausrüstungsantrag erhält dieselben neuen Exemplare.

Gemeinsame Universumsguthaben benötigen bei der Abenteuerfreigabe die
Autorisierung der Universumsleitung oder Administration. Diese Berechtigung
wird vor der Auszahlung erneut geprüft. Lokale Bögen und unabhängige Kopien
erhalten bei einem gemeinsamen Kontomodell kein Ersatzkonto. Währungswechsel,
fehlende Community oder entzogene Vergaberechte werden ausdrücklich abgewiesen.

Kosten, tatsächliche Belohnung, Bestandsereignisse und Fortschritt werden
gemeinsam gespeichert oder vollständig zurückgenommen. Dieselbe Entscheidung
kann im Charakterdurchlauf nicht erneut auszahlen; es gibt keine Wiederholung
oder Rücksetzung des Durchlaufs. Der private Verlauf zeigt erhaltene Gegenstände,
Betrag, gegebenenfalls Tabellenwurf oder die ausgebliebene Belohnung bei Misserfolg.
Ältere Abenteuer ohne Belohnungen bleiben unverändert nutzbar.

### Solo spielen

1. Öffne den eigenen Charakterbogen oder den eigenen privaten
   Pen-&-Paper-Profilbereich.
2. Ein freigegebenes Abenteuer mit **Solo starten** beginnen. Ein Durchlauf
   gehört genau diesem Charakter und kann nicht zurückgesetzt werden.
3. Die angezeigte Szene lesen und eine der verfügbaren Entscheidungen wählen.
   Proben werden serverseitig durchgeführt; der gespeicherte Verlauf enthält
   Entscheidung, Würfelergebnis, verwendeten Zahlenwert und Szenenübergang.
4. Bei einer abgeschlossenen Szene ohne weitere Entscheidungen ist der
   Durchlauf beendet. Ein Neuladen oder wiederholtes Absenden erzeugt keinen
   zweiten Fortschritt.

Durchläufe und ihre Würfe sind nur für den Charakterbesitzer und die aktuelle
Spielleitung sichtbar. Das Entfernen aus der Kampagne entzieht den Zugriff;
ein erneuter Beitritt gewährt dem Besitzer wieder Zugriff auf den eigenen
Durchlauf. Die Spielleitung kann neue Starts pausieren oder wieder zulassen,
ohne laufende Durchläufe zu löschen. Ein pausiertes, bereits begonnenes
Abenteuer kann weitergespielt werden. Der Spielstand speichert keinen
separaten Charakterbogen: Zahlenproben verwenden den bei der Entscheidung
aktuellen, freigegebenen Zahlenwert des Charakters.

**Keine ungefragten Änderungen:** Ein Soloabenteuer verändert die aktive
Kampagnenszene nicht. Ressourcenverbrauch und Gegenstands-/Geldvergaben erfolgen
ausschließlich nach ausdrücklich konfigurierten und freigegebenen Regeln.
Es benötigt keine Gruppenentscheidung und
funktioniert unabhängig von PS Community. Teamdurchläufe, Rücksetzen,
Voraussetzungen jenseits der beschriebenen Wertebedingungen, automatische
Ressourcenregeneration bleiben spätere, getrennt zu planende
Erweiterungen.

In der Nebelhafen-Demo ist **Die Wetterkapsel vor dem Ablegen** als
Soloabenteuer freigegeben. Jorins Durchlauf endete nach einer erfolgreichen
Verstandprobe mit 9 gegen Zielwert 7 an der Szene **Auf dem Servicesteg**.
Die aktive Gruppenszene und Jorins Charakterbogen blieben dabei unverändert.

## 22. Kampagne sichern

Seit **0.26.0** können Spielleitungen eine versionierte JSON-Sicherung
herunterladen:

1. Die Kampagne öffnen und **Rundeneinstellungen → Kampagne sichern** wählen.
   Dieselbe Funktion steht bei der Kampagne im Backend bereit.
2. Die **Exportvariante** auswählen:
   - **Lokale Kampagnendaten mit ausgewiesenen Abhängigkeiten:** Sichert
     ausschließlich die kampagneneigenen Plugin-Daten und Bilder. Gemeinsame
     Universumsdaten und zugeordnete Basisbögen werden nicht mitkopiert,
     sondern als benötigte, nicht enthaltene Abhängigkeiten ausgewiesen.
   - **Vollständig einschließlich benötigter Universumsdaten:** Sichert
     zusätzlich die Universumsdefinitionen und gemeinsamen Gegenstands-,
     NPC- und plugininternen Wiki-Kataloge, tatsächlich zugeordnete gemeinsame
     Basisbögen sowie benötigte Bilder und zugehörige Ereignisse. Dafür muss
     die exportierende Person auch das zugeordnete Universum verwalten dürfen.
     Ohne Universumszuordnung werden nur die Kampagnendaten benötigt.
3. Bestätigen, dass die Datei private Inhalte enthält und geschützt
   aufbewahrt wird.
4. **JSON-Sicherung herunterladen** wählen und die Datei an einem geschützten
   Ort speichern. Nicht in der öffentlichen Mediathek oder auf einer
   öffentlich erreichbaren Website ablegen.

Die Anfrage öffnet sich separat in einer neuen Registerkarte. Ungespeicherte
Eingaben und Änderungswarnungen auf der bisherigen Seite bleiben erhalten,
auch wenn die Exportanfrage einen Fehler meldet.

Gesichert werden unter anderem Regeln, Mitgliedschaftszuordnungen, Charaktere
mit freigegebenen und beantragten Ständen, Inventare, Guthaben, Kapitel,
Handouts, Szenen, NPCs, Beutetabellen, Handelsstände, Würfe, Ereignisse,
Soloabenteuer und Durchläufe sowie gespeicherte private Bilder. Geheimnisse,
Entwürfe und private Protokolle sind ausdrücklich enthalten. Der Export
verändert keine Spielstände, zahlt nichts aus und vergibt keine Gegenstände.
Die Datei wird direkt heruntergeladen, nicht öffentlich auf dem Server abgelegt.

Andere Kampagnen, unbeteiligte Universums-Basisbögen und deren private
Protokolle bleiben ausgeschlossen. Eine unabhängige Kampagnenkopie nimmt
ihren ursprünglichen Basisbogen nicht mit. Fehlende benötigte Bilder oder
Basisbögen verhindern den vollständigen Export; die lokale Variante hält die
entsprechenden Abhängigkeiten fest. Bereits gelöschte Bilder aus alten
Ereignisprotokollen werden als historische Fehlbestände ausgewiesen und
blockieren die Sicherung des aktuellen Stands nicht.

**Nicht enthalten:** Benutzerkonten und Zugangsdaten, Community-Profile,
Chatnachrichten sowie externe Wiki-Artikel und Kategorien. Ihre Verweise
beziehungsweise die vorhandenen plugininternen Wiki-Stände bleiben erhalten.
Sie müssen bei einer späteren Wiederherstellung kontrolliert zugeordnet
werden; fremde Konten oder Integrationsdaten werden nicht automatisch kopiert.

Die Datei enthält eine Formatversion und SHA-256-Prüfsumme. Diese erkennt
Beschädigungen, ersetzt aber keine Zugriffsprüfung und ist keine Signatur.
Die ursprünglichen Objekt- und Benutzer-IDs sind keine Erlaubnis zum
Überschreiben vorhandener Zieldaten.

Seit **0.29.0** können die Dateien kontrolliert wiederhergestellt werden.
Die JSON-Datei ersetzt weiterhin kein vollständiges Website-Backup.

## 23. Private Importvorschau und Wiederherstellung

Seit **0.27.0** kann berechtigte Administration oder Spielleitung eine
Sicherung prüfen und Zuordnungen vorbereiten:

1. **Einstellungen → Sicherung prüfen und Wiederherstellung vorbereiten**
   im Spielbereich oder Backend öffnen.
2. Eine private JSON-Kampagnensicherung auswählen und **Datei prüfen und
   Vorschau öffnen** wählen. Es gelten die angezeigten Hostgrenzen.
3. **Pro Vorgang selbst auswählen:** neue Kampagne oder Ersatz einer bestehenden
   verwaltbaren Kampagne. Neue Kampagnen und Universen benötigen die
   ausdrücklich vergebene Erstellungsberechtigung.
4. Enthaltene Universumsdaten getrennt wiederherstellen oder ein verwaltbares
   vorhandenes Universum zuordnen. Eine Quelluniversumsverbindung wird nicht
   still entfernt; eine eigenständige Kampagne ist ohne diese Abhängigkeit möglich.
5. Quellbenutzer ausdrücklich bestehenden Websitekonten zuordnen. Rein
   historische Konten dürfen ohne aktive Mitgliedschaft bleiben;
   Charakterbesitzer und benötigte Autorisierungen brauchen eine Zuordnung.
   Es werden keine Konten erstellt oder globale Rollen vergeben.
6. Bei einem bestehenden Universum **Zuordnungen und Konflikte prüfen** wählen,
   um dessen Basisbögen auszuwählen, danach erneut prüfen. Basisbogen,
   Universum und zugeordneter Besitzer müssen zusammenpassen.
7. Konflikte und Hinweise lesen. Bei Ersatz kann die aktuelle Zielkampagne
   über **Aktuelle Zielkampagne vor dem Ersatz sichern** heruntergeladen werden.
   **Private Vorschau verwerfen** entfernt die eigene Vorschau.
8. Zugang auswählen: standardmäßig **privat und geschlossen**, oder die
   ursprünglichen Zugangseinstellungen ausdrücklich übernehmen. Unbekannte
   Plugin-Metadaten dürfen nur mit Zustimmung privat archiviert werden,
   niemals als aktive Einstellungen. Bekannte ungültige Daten sperren weiterhin
   den Vorgang.
9. Ziel, Konten und Übernahme privater Spielstände bestätigen, anschließend
   **Als neue Kampagne wiederherstellen** oder **Zielkampagne gesichert ersetzen**
   wählen. Neue Inhalte bekommen neue IDs; beim Ersatz bleiben Kampagnen-ID
   und bisheriger Ersteller erhalten. Das ausführende Konto bleibt Spielleitung.
10. Nach Erfolg die Kampagne öffnen. Bei Ersatz kann die automatisch gesicherte
    ursprüngliche Kampagne privat heruntergeladen und mit demselben Ablauf
    erneut als neue Kampagne oder bestätigter Ersatz wiederhergestellt werden.

Die Vorschau zeigt Quelle, Exportversion, Prüfsumme und enthaltene Datentypen.
Seit **0.28.0** zeigt sie zusätzlich die **Fachdokumentprüfung**: Unterstützte
Regel-, Charakter-, Story- und Wirtschaftsdokumente werden mit denselben
Fachvalidatoren wie im Plugin geprüft. Ungültige Werte erscheinen als Konflikte;
die Originalwerte werden nicht korrigiert oder überschrieben.
Seit **0.29.0** prüft die vollständige Aufbereitung zusätzlich Protokolle,
Metadaten und typisierte ID-Zuordnungen, bevor die Speicherbestätigung erscheint.
Dateiformat, Struktur, Bilddaten und Kampagnenabgrenzung werden geprüft;
fehlende Bilder, nicht passende Feldstrukturen oder lokale Guthabenwährungen
werden nicht still ersetzt beziehungsweise umgerechnet. Zugeordnete bestehende
Universumsdaten, Basiswerte und gemeinsame Guthaben bleiben unverändert.

Die Originaldatei und Optionen bleiben privat an Konto und Website gebunden.
Die Vorschau ist höchstens eine Stunde verfügbar; erneutes Prüfen verlängert
die Frist nicht. Es wird keine öffentliche Datei oder Mediathekanlage erstellt.

**Eine Vorschau allein ändert noch keine Spielstände.** Erst die ausdrückliche
Bestätigung speichert die aufbereiteten Inhalte gemeinsam in einer sicheren
Datenbanktransaktion. Geänderte Ziel-/Universumsstände verlangen eine erneute
Prüfung; ältere Formulare müssen nach einem Ersatz neu geladen werden.
Wiederholte identische Bestätigungen erzeugen keine zweite Kampagne und vergeben
keine Belohnungen erneut. Auch ein späterer Ersatz macht alte Bestätigungen
nicht wieder verwendbar. Bei einem nicht eindeutig bestätigten Abschluss
informiere die Administration, statt blind einen zweiten Import zu starten.

Die Zielsicherung und alten lokalen Datensätze liegen privat, nicht als spielbare
Kampagne oder öffentliche Mediathekdatei. Herunterladen dürfen nur der bisherige
Ersteller, die ausführende Person und die Administration; neu importierte
Spielleitungen bekommen nicht automatisch Zugriff auf alte Zielgeheimnisse.
Inventare, Guthaben und laufende Abenteuer werden übernommen, wirtschaftliche
Aktionen und historische Würfe nicht erneut ausgeführt. Externe Wiki-Verknüpfungen
bleiben deaktiviert; Konten, globale Rollen, Chat- und Community-Daten benötigen
eigene Verwaltung beziehungsweise Backups.

Nach einem Ersatz beginnt die Runde eine getrennte Chatsitzung und Anwesenheit.
Alte Formulare und offene Spieltische müssen neu geladen werden; sie dürfen den
neuen Stand nicht mit alten Eingaben ändern. Bereits gespeicherte externe
Chatgespräche werden nicht gelöscht oder neuen Mitgliedern zugeordnet.
