<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_loot_url( $campaign, $page_id, $table_id = 0, $run_id = 0 ) {
	$url = $page_id ? ps_pen_paper_game_url( $page_id, $campaign->ID ) : ps_pen_paper_admin_url( $campaign->ID );
	$args = array( 'pspp_loot' => 1 );
	if ( $table_id ) { $args['pspp_loot_table'] = $table_id; }
	if ( $run_id ) { $args['pspp_loot_run'] = $run_id; }
	return add_query_arg( $args, $url ) . '#pspp-folio';
}

function ps_pen_paper_handle_loot_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) { ps_pen_paper_fail( __( 'Diese Beuteverwaltung ist nicht verfügbar.', 'ps-pen-paper' ) ); }
	$table_id = ps_pen_paper_posted_id( 'loot_table_id' ); $run_id = ps_pen_paper_posted_id( 'loot_run_id' );
	if ( 'save_loot_table' === $operation ) {
		if ( 'complete' !== ps_pen_paper_posted_text( 'loot_complete' ) ) { ps_pen_paper_fail( __( 'Die Beutetabelle wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
		$result = ps_pen_paper_save_loot_table( $campaign, $table_id, isset( $_POST['loot'] ) ? wp_unslash( $_POST['loot'] ) : null, ps_pen_paper_posted_text( 'loot_revision' ) );
		if ( ! is_wp_error( $result ) ) { $table_id = $result; }
	} elseif ( 'roll_loot' === $operation ) {
		$result = ps_pen_paper_roll_loot( $campaign, $table_id, ps_pen_paper_posted_text( 'loot_revision' ), ps_pen_paper_posted_text( 'loot_rules_revision' ), ps_pen_paper_posted_text( 'loot_reason' ), ps_pen_paper_posted_text( 'loot_token' ) );
		if ( ! is_wp_error( $result ) ) { $run_id = $result; }
	} else {
		if ( '1' === ps_pen_paper_posted_text( 'loot_split' ) ) {
			if ( 'complete' !== ps_pen_paper_posted_text( 'loot_split_complete' ) ) { ps_pen_paper_fail( __( 'Die Verteilung wurde nicht vollständig übertragen.', 'ps-pen-paper' ) ); }
			$allocations = isset( $_POST['loot_allocations'] ) ? wp_unslash( $_POST['loot_allocations'] ) : array();
			$result = ps_pen_paper_award_loot_split( $campaign, $run_id, $allocations, ps_pen_paper_posted_text( 'loot_confirmation' ) );
		} else {
			$recipient = ps_pen_paper_posted_text( 'loot_recipient' );
			if ( ! preg_match( '/^(0|[1-9]\d{0,10}):(0|[1-9]\d{0,10}):(0|[1-9]\d{0,10}):(0|[1-9]\d{0,10})$/D', $recipient, $parts ) ) { ps_pen_paper_fail( __( 'Bitte wähle einen aktuellen Empfänger und lade bei Konflikten neu.', 'ps-pen-paper' ) ); }
			$result = ps_pen_paper_award_loot( $campaign, $run_id, (int) $parts[1], $parts[2], $parts[3], $parts[4], ps_pen_paper_posted_text( 'loot_confirmation' ) );
		}
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( ps_pen_paper_loot_url( $campaign, $page_id, $table_id, $run_id ) );
	exit;
}

function ps_pen_paper_render_loot_roll( $roll ) {
	echo '<p>' . esc_html( $roll['expression'] . ' = ' . $roll['total'] ) . '</p>';
	foreach ( $roll['groups'] as $group ) { echo '<p>' . esc_html( 'W' . $group['sides'] . ': ' . implode( ', ', $group['rolls'] ) ) . '</p>'; }
}

function ps_pen_paper_render_loot_node( $node ) {
	$row = $node['table']['table']['rows'][ $node['row'] ];
	echo '<h4>' . esc_html( $node['table']['table']['name'] . ' · ' . $row['label'] ) . '</h4>';
	ps_pen_paper_render_loot_roll( $node['roll'] );
	if ( $row['item'] ) { echo '<p>' . esc_html( $node['quantity_roll']['total'] . ' × ' . $row['item']['item']['name'] ) . '</p>'; ps_pen_paper_render_loot_roll( $node['quantity_roll'] ); }
	foreach ( isset( $row['items'] ) ? $row['items'] : array() as $index => $item ) {
		echo '<p>' . esc_html( $node['extra_rolls'][ $index ]['total'] . ' × ' . $item['item']['item']['name'] ) . '</p>';
		ps_pen_paper_render_loot_roll( $node['extra_rolls'][ $index ] );
	}
	if ( $node['money_roll']['total'] ) { echo '<p>' . esc_html( $node['money_roll']['total'] . ' ' . $node['table']['table']['currency']['document']['symbol'] ) . '</p>'; ps_pen_paper_render_loot_roll( $node['money_roll'] ); }
	if ( ! empty( $node['nested'] ) ) { ps_pen_paper_render_loot_node( $node['nested'] ); }
}

function ps_pen_paper_render_loot_snapshot( $state, $table = false ) {
	if ( $table ) {
		if ( ! $state['table'] ) { echo '<p>' . esc_html__( 'Noch keine Tabelle vorhanden.', 'ps-pen-paper' ) . '</p>'; return; }
		$t = $state['table'];
		echo '<h4>' . esc_html( $t['name'] . ' · W' . $t['die'] ) . '</h4><ul>';
		foreach ( $t['rows'] as $row ) {
			$reward = $row['item'] ? $row['quantity'] . ' × ' . $row['item']['item']['name'] . ' · ' . ps_pen_paper_item_bindings()[ ps_pen_paper_item_binding( $row['item']['item'] ) ] : __( 'Keine Gegenstände', 'ps-pen-paper' );
			if ( '0' !== $row['money'] ) { $reward .= ' · ' . $row['money'] . ' ' . $t['currency']['document']['symbol']; }
			foreach ( isset( $row['items'] ) ? $row['items'] : array() as $item ) { $reward .= ' · ' . $item['quantity'] . ' × ' . $item['item']['item']['name']; }
			if ( ! empty( $row['nested'] ) ) { $reward .= ' · ' . __( 'Untertabelle:', 'ps-pen-paper' ) . ' ' . $row['nested']['table']['table']['name']; }
			echo '<li>' . esc_html( $row['from'] . '–' . $row['to'] . ': ' . $row['label'] . ' · ' . ( $row['to'] - $row['from'] + 1 ) . '/' . $t['die'] . ' · ' . $reward ) . '</li>';
		}
		echo '</ul>'; return;
	}
	if ( ! $state['proposal'] ) { echo '<p>' . esc_html__( 'Noch kein Beutevorgang vorhanden.', 'ps-pen-paper' ) . '</p>'; return; }
	$p = $state['proposal']; $row = $p['table']['table']['rows'][ $p['row'] ];
	if ( array_key_exists( 'extra_rolls', $p ) ) {
		echo '<p>' . esc_html( $p['reason'] ) . '</p>'; ps_pen_paper_render_loot_node( $p );
		if ( $state['award'] && isset( $state['award']['allocations'] ) ) {
			echo '<p>' . esc_html__( 'Abgeschlossen für:', 'ps-pen-paper' ) . ' ' . esc_html( implode( ', ', array_column( $state['award']['allocations'], 'character_name' ) ) ) . '</p>';
		} elseif ( ! $state['award'] ) { echo '<p>' . esc_html__( 'Nur Vorschlag – noch nichts vergeben.', 'ps-pen-paper' ) . '</p>'; }
		return;
	}
	echo '<h4>' . esc_html( $p['table']['table']['name'] . ' · ' . $row['label'] ) . '</h4><p>' . esc_html( $p['reason'] ) . '</p>';
	echo '<p>' . esc_html( __( 'Tabellenrevision:', 'ps-pen-paper' ) . ' ' . $p['table']['revision'] ) . '</p>';
	ps_pen_paper_render_loot_roll( $p['roll'] );
	echo '<p>' . esc_html( $p['quantity_roll']['total'] . ' × ' . ( $row['item'] ? $row['item']['item']['name'] : __( 'Keine Gegenstände', 'ps-pen-paper' ) ) ) . '</p>';
	if ( $row['item'] ) {
		echo '<p>' . esc_html( ps_pen_paper_item_bindings()[ ps_pen_paper_item_binding( $row['item']['item'] ) ] ) . '</p>';
		ps_pen_paper_render_loot_roll( $p['quantity_roll'] );
	}
	if ( $p['table']['table']['currency'] ) {
		echo '<p><strong>' . esc_html( $p['money_roll']['total'] . ' ' . $p['table']['table']['currency']['document']['symbol'] ) . '</strong></p>';
		ps_pen_paper_render_loot_roll( $p['money_roll'] );
	}
	$names = $state['award'] ? ( isset( $state['award']['allocations'] ) ? implode( ', ', array_column( $state['award']['allocations'], 'character_name' ) ) : $state['award']['character_name'] ) : '';
	echo '<p><strong>' . esc_html( $state['award'] ? __( 'Abgeschlossen für:', 'ps-pen-paper' ) . ' ' . $names : __( 'Nur Vorschlag – noch nichts vergeben.', 'ps-pen-paper' ) ) . '</strong></p>';
}

function ps_pen_paper_render_loot_row( $index, $row, $catalogue, $tables = array(), $current_id = 0 ) {
	?>
	<fieldset class="pspp-field" data-loot-row><legend><?php esc_html_e( 'Beuteausgang', 'ps-pen-paper' ); ?></legend>
	<?php foreach ( array( 'from' => __( 'Von (einschließlich)', 'ps-pen-paper' ), 'to' => __( 'Bis (einschließlich)', 'ps-pen-paper' ), 'label' => __( 'Bezeichnung des Ausgangs', 'ps-pen-paper' ) ) as $key => $label ) : ?>
		<label><?php echo esc_html( $label ); ?><input name="loot[rows][<?php echo esc_attr( $index ); ?>][<?php echo esc_attr( $key ); ?>]" type="<?php echo 'label' === $key ? 'text' : 'number'; ?>" value="<?php echo esc_attr( $row[ $key ] ); ?>" <?php echo 'label' === $key ? 'maxlength="200"' : 'min="1" max="100"'; ?> required></label>
	<?php endforeach; ?>
	<label><?php esc_html_e( 'Gegenstandsvorlage (optional)', 'ps-pen-paper' ); ?><select name="loot[rows][<?php echo esc_attr( $index ); ?>][item_id]"><option value="0"><?php esc_html_e( 'Keine Gegenstände', 'ps-pen-paper' ); ?></option>
	<?php $selected_id = $row['item'] ? $row['item']['source_id'] : 0;
	if ( $selected_id && ! isset( $catalogue[ $selected_id ] ) ) : ?><option selected value="<?php echo esc_attr( $selected_id ); ?>"><?php esc_html_e( 'Vorlage fehlt – bitte neu wählen', 'ps-pen-paper' ); ?></option><?php endif;
	foreach ( $catalogue as $item_id => $item ) : ?><option value="<?php echo esc_attr( $item_id ); ?>" <?php selected( $selected_id, $item_id ); ?>><?php echo esc_html( $item['item']['name'] ); ?></option><?php endforeach; ?></select></label>
	<label><?php esc_html_e( 'Menge: 0 ohne Gegenstand, sonst 1–100 oder Würfelausdruck', 'ps-pen-paper' ); ?><input type="text" name="loot[rows][<?php echo esc_attr( $index ); ?>][quantity]" value="<?php echo esc_attr( $row['quantity'] ); ?>" maxlength="200" required></label>
	<label><?php esc_html_e( 'Währungsbetrag: 0, ganze Zahl oder Würfelausdruck', 'ps-pen-paper' ); ?><input type="text" name="loot[rows][<?php echo esc_attr( $index ); ?>][money]" value="<?php echo esc_attr( $row['money'] ); ?>" maxlength="200" required></label>
	<?php $items = array(); foreach ( isset( $row['items'] ) ? $row['items'] : array() as $item ) { $items[] = $item['item']['source_id'] . ':' . $item['quantity']; } ?>
	<label><?php esc_html_e( 'Weitere Gegenstände: je Zeile Vorlagen-ID:Menge oder Würfelausdruck', 'ps-pen-paper' ); ?><textarea name="loot[rows][<?php echo esc_attr( $index ); ?>][items]" rows="3"><?php echo esc_textarea( implode( "\n", $items ) ); ?></textarea></label>
	<p><?php foreach ( $catalogue as $item_id => $item ) { echo esc_html( '#' . $item_id . ' ' . $item['item']['name'] ) . ' · '; } ?></p>
	<?php $nested_id = ! empty( $row['nested'] ) ? $row['nested']['table_id'] : 0; ?>
	<label><?php esc_html_e( 'Untertabelle (ein zusätzlicher Wurf)', 'ps-pen-paper' ); ?><select name="loot[rows][<?php echo esc_attr( $index ); ?>][nested_id]">
		<option value="0"><?php esc_html_e( 'Keine', 'ps-pen-paper' ); ?></option>
		<?php if ( $nested_id && ! isset( $tables[ $nested_id ] ) ) : ?><option selected value="<?php echo esc_attr( $nested_id ); ?>"><?php esc_html_e( 'Tabelle fehlt – bitte neu wählen', 'ps-pen-paper' ); ?></option><?php endif; ?>
		<?php foreach ( $tables as $nested_key => $nested ) : if ( $nested_key === $current_id ) { continue; } ?><option value="<?php echo esc_attr( $nested_key ); ?>" <?php selected( $nested_id, $nested_key ); ?>><?php echo esc_html( $nested['table']['name'] ); ?></option><?php endforeach; ?>
	</select></label>
	<button type="button" data-remove-loot><?php esc_html_e( 'Ausgang entfernen', 'ps-pen-paper' ); ?></button>
	</fieldset>
	<?php
}

function ps_pen_paper_render_loot( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_manage_loot( $campaign ) ) { return; }
	$tables = ps_pen_paper_loot_tables( $campaign ); $table_id = ps_pen_paper_frontend_id( 'pspp_loot_table' ); $run_id = ps_pen_paper_frontend_id( 'pspp_loot_run' );
	$open = ps_pen_paper_frontend_id( 'pspp_loot' );
	if ( is_wp_error( $tables ) || is_wp_error( $table_id ) || is_wp_error( $run_id ) || is_wp_error( $open ) ) { echo '<p role="alert">' . esc_html__( 'Die Beuteverwaltung konnte nicht geladen werden.', 'ps-pen-paper' ) . '</p>'; return; }
	?>
	<section id="pspp-loot" class="pspp-npcs"><h2><?php esc_html_e( 'Beutetabellen', 'ps-pen-paper' ); ?></h2>
	<details class="pspp-fold"<?php if ( $open || $table_id || $run_id ) { echo ' open'; } ?>><summary><?php esc_html_e( 'Beute vorbereiten, auswürfeln und bestätigen', 'ps-pen-paper' ); ?></summary>
	<p><?php esc_html_e( 'Nur Spielleitung. Ein Wurf speichert zunächst einen unveränderten Vorschlag; erst die bestätigte Vergabe verändert Inventar oder Guthaben. Ein Vorgang wird höchstens einmal vergeben.', 'ps-pen-paper' ); ?></p>
	<p><a href="<?php echo esc_url( ps_pen_paper_loot_url( $campaign, $page_id ) ); ?>"><?php esc_html_e( 'Neue Beutetabelle', 'ps-pen-paper' ); ?></a></p>
	<ul><?php foreach ( $tables as $record_id => $record ) : ?><li><a href="<?php echo esc_url( ps_pen_paper_loot_url( $campaign, $page_id, $record_id ) ); ?>"><?php echo esc_html( $record['table']['name'] ); ?></a></li><?php endforeach; ?></ul>
	<h3><?php esc_html_e( 'Letzte Beutevorgänge', 'ps-pen-paper' ); ?></h3><ul>
	<?php foreach ( get_posts( array( 'post_type' => 'pspp_loot_run', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => 20 ) ) as $post ) :
		$run = ps_pen_paper_get_loot_run( $campaign, $post->ID );
		if ( is_wp_error( $run ) ) { echo '<li role="alert">' . esc_html( $run->get_error_message() ) . '</li>'; continue; } ?>
		<li><a href="<?php echo esc_url( ps_pen_paper_loot_url( $campaign, $page_id, 0, $post->ID ) ); ?>"><?php echo esc_html( '#' . $post->ID . ' · ' . $run['proposal']['reason'] ); ?></a> · <?php echo esc_html( $run['award'] ? __( 'abgeschlossen', 'ps-pen-paper' ) : __( 'offen', 'ps-pen-paper' ) ); ?></li>
	<?php endforeach; ?></ul>
	<?php if ( ! ps_pen_paper_community_available() ) : ?><p role="alert"><?php esc_html_e( 'Beuteänderungen benötigen aktives PS Community. Gespeicherte Tabellen und Vorgänge bleiben erhalten.', 'ps-pen-paper' ); ?></p></details></section><?php return; endif;
	$rules = ps_pen_paper_get_rules( $campaign->ID );
	if ( $run_id ) :
		$run = ps_pen_paper_get_loot_run( $campaign, $run_id );
		if ( is_wp_error( $run ) ) { echo '<p role="alert">' . esc_html( $run->get_error_message() ) . '</p></details></section>'; return; } ?>
		<div data-loot-proposal><?php ps_pen_paper_render_loot_snapshot( $run ); ?></div>
		<?php if ( ! $run['award'] ) :
			$components = ps_pen_paper_loot_components( $run['proposal'] ); $money = $components['money'];
			$characters = ps_pen_paper_get_characters( $campaign );
			if ( count( $characters ) > 30 ) { echo '<p role="alert">' . esc_html__( 'Die Verteilung unterstützt höchstens 30 Charaktere pro Runde.', 'ps-pen-paper' ) . '</p></details></section>'; return; }
			ps_pen_paper_form_start( 'award_loot', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="loot_run_id" value="<?php echo esc_attr( $run_id ); ?>">
			<input type="hidden" name="loot_split" value="1">
			<p><?php esc_html_e( 'Verteile jeden Gegenstand und den gesamten Geldbetrag vollständig. Null bedeutet keine Vergabe an diesen Charakter.', 'ps-pen-paper' ); ?></p>
			<?php foreach ( $characters as $character ) :
				$state = ps_pen_paper_character_state( $campaign, $character, false );
				$gear = $components['items'] ? ps_pen_paper_gear_state( $character->ID ) : array( 'revision' => 0 );
				$wallet = $money ? ps_pen_paper_wallet_context( $campaign, $character->ID ) : null;
				if ( is_wp_error( $state ) || ! $state['active'] ) { echo '<p role="alert">' . esc_html__( 'Ein Charakter ist nicht verfügbar oder nicht freigegeben.', 'ps-pen-paper' ) . '</p>'; continue; }
				$gear_ok = ! is_wp_error( $gear );
				$wallet_ok = $wallet && ! is_wp_error( $wallet ) && ps_pen_paper_can_manage_character_scope( $wallet['scope'] ); ?>
				<fieldset class="pspp-field"><legend><?php echo esc_html( $state['active']['name'] ); ?></legend>
				<input type="hidden" name="loot_allocations[<?php echo esc_attr( $character->ID ); ?>][gear_revision]" value="<?php echo esc_attr( $gear_ok ? $gear['revision'] : 0 ); ?>">
				<input type="hidden" name="loot_allocations[<?php echo esc_attr( $character->ID ); ?>][wallet_revision]" value="<?php echo esc_attr( $wallet_ok ? $wallet['wallet']['revision'] : 0 ); ?>">
				<input type="hidden" name="loot_allocations[<?php echo esc_attr( $character->ID ); ?>][currency_revision]" value="<?php echo esc_attr( $wallet_ok ? $wallet['currency']['revision'] : 0 ); ?>">
				<?php if ( ! $gear_ok ) : ?><p role="alert"><?php esc_html_e( 'Inventar nicht verfügbar.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				<?php if ( $money && ! $wallet_ok ) : ?><p role="alert"><?php esc_html_e( 'Guthaben oder Buchungsrecht nicht verfügbar.', 'ps-pen-paper' ); ?></p><?php endif; ?>
				<?php foreach ( $components['items'] as $index => $item ) : ?>
					<label><?php echo esc_html( $item['item']['item']['name'] . ' · ' . $item['quantity'] ); ?><input type="number" min="0" max="<?php echo esc_attr( $item['quantity'] ); ?>" name="loot_allocations[<?php echo esc_attr( $character->ID ); ?>][items][]" value="0" <?php if ( ! $gear_ok ) { echo 'readonly'; } ?>></label>
				<?php endforeach; ?>
				<label><?php esc_html_e( 'Geldbetrag', 'ps-pen-paper' ); ?><input type="number" min="0" max="<?php echo esc_attr( min( 999999999, $money ) ); ?>" name="loot_allocations[<?php echo esc_attr( $character->ID ); ?>][money]" value="0" <?php if ( ! $wallet_ok ) { echo 'readonly'; } ?>></label>
				</fieldset>
			<?php endforeach; ?>
			<label class="pspp-check"><input type="checkbox" name="loot_confirmation" value="confirmed" required><?php esc_html_e( 'Gespeicherte Beute und Empfänger geprüft. Diesen Vorgang genau einmal abschließen.', 'ps-pen-paper' ); ?></label>
			<input type="hidden" name="loot_split_complete" value="complete">
			<button type="submit"><?php echo esc_html( $money || $components['items'] ? __( 'Beute verbindlich vergeben', 'ps-pen-paper' ) : __( 'Ohne Beute abschließen', 'ps-pen-paper' ) ); ?></button>
			</form>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( ps_pen_paper_loot_url( $campaign, $page_id, 0, $run_id ) ); ?>"><?php esc_html_e( 'Gespeicherten Vorgang öffnen (kein neuer Wurf)', 'ps-pen-paper' ); ?></a></p>
	<?php else :
		$state = $table_id ? ps_pen_paper_get_loot_table( $campaign, $table_id ) : array( 'revision' => 0, 'table' => null );
		$catalogue = ps_pen_paper_item_catalogue( $campaign );
		if ( is_wp_error( $state ) || is_wp_error( $catalogue ) || ! $rules['revision'] || ! $rules['dice'] ) { echo '<p role="alert">' . esc_html__( 'Bitte prüfe Tabellenwahl, Gegenstandskatalog und das eingerichtete Regelwerk mit erlaubten Würfeln.', 'ps-pen-paper' ) . '</p></details></section>'; return; }
		$blank = array( 'from' => '', 'to' => '', 'label' => '', 'item' => null, 'quantity' => '0', 'money' => '0' );
		$t = $state['table'] ?: array( 'name' => '', 'die' => $rules['dice'][0], 'rows' => array( $blank ) );
		if ( $table_id ) :
			ps_pen_paper_render_loot_snapshot( $state, true );
			ps_pen_paper_form_start( 'roll_loot', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="loot_table_id" value="<?php echo esc_attr( $table_id ); ?>"><input type="hidden" name="loot_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
				<input type="hidden" name="loot_rules_revision" value="<?php echo esc_attr( $rules['revision'] ); ?>"><input type="hidden" name="loot_token" value="<?php echo esc_attr( wp_generate_uuid4() ); ?>">
				<label><?php esc_html_e( 'Anlass des Beutewurfs – nur Spielleitung', 'ps-pen-paper' ); ?><input type="text" name="loot_reason" maxlength="1000" required></label>
				<button type="submit"><?php esc_html_e( 'Beutevorschlag auswürfeln', 'ps-pen-paper' ); ?></button>
			</form>
		<?php endif; ?>
		<h3><?php esc_html_e( 'Beutetabelle bearbeiten', 'ps-pen-paper' ); ?></h3>
		<p><?php esc_html_e( 'Alle Ergebnisse von 1 bis zum höchsten Würfelwert genau einmal abdecken. Ein Ausgang kann mehrere Gegenstände, Geld und eine Untertabelle enthalten. Mengen und Geld dürfen erlaubte Würfelausdrücke sein. Speichern friert Vorlagen und Untertabellen ein; bestehende Vorgänge bleiben unverändert.', 'ps-pen-paper' ); ?></p>
		<?php ps_pen_paper_form_start( 'save_loot_table', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="loot_table_id" value="<?php echo esc_attr( $table_id ); ?>"><input type="hidden" name="loot_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<label><?php esc_html_e( 'Name der Beutetabelle', 'ps-pen-paper' ); ?><input type="text" name="loot[name]" value="<?php echo esc_attr( $t['name'] ); ?>" maxlength="200" required></label>
			<label><?php esc_html_e( 'Tabellenwürfel', 'ps-pen-paper' ); ?><select name="loot[die]"><?php foreach ( $rules['dice'] as $die ) : ?><option value="<?php echo esc_attr( $die ); ?>" <?php selected( $t['die'], $die ); ?>><?php echo esc_html( 'W' . $die ); ?></option><?php endforeach; ?></select></label>
			<div data-loot-editor data-next-index="<?php echo esc_attr( count( $t['rows'] ) ); ?>"><div data-loot-rows>
				<?php foreach ( $t['rows'] as $index => $row ) { ps_pen_paper_render_loot_row( $index, $row, $catalogue, $tables, $table_id ); } ?>
			</div><template data-loot-template><?php ps_pen_paper_render_loot_row( '__INDEX__', $blank, $catalogue, $tables, $table_id ); ?></template>
				<button type="button" data-add-loot><?php esc_html_e( 'Ausgang hinzufügen', 'ps-pen-paper' ); ?></button>
			</div>
			<noscript><?php esc_html_e( 'Weitere Ausgänge hinzufügen oder entfernen benötigt JavaScript.', 'ps-pen-paper' ); ?></noscript>
			<input type="hidden" name="loot_complete" value="complete"><button type="submit"><?php esc_html_e( 'Beutetabelle speichern', 'ps-pen-paper' ); ?></button>
		</form>
	<?php endif; ?></details></section>
	<?php
}
