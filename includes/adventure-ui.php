<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function ps_pen_paper_handle_adventure_action( $operation ) {
	$campaign_id = ps_pen_paper_posted_id( 'campaign_id' ); $page_id = ps_pen_paper_posted_id( 'pspp_frontend_page' );
	check_admin_referer( 'pspp_campaign_' . $campaign_id );
	$campaign = ps_pen_paper_get_campaign( $campaign_id );
	if ( ! $campaign || ! ps_pen_paper_can_access_site() || ( isset( $_POST['pspp_frontend_page'] ) && ! ps_pen_paper_frontend_page( $page_id ) ) ) {
		ps_pen_paper_fail( ps_pen_paper_adventure_error()->get_error_message() );
	}
	$id = ps_pen_paper_posted_id( 'adventure_id' ); $revision = ps_pen_paper_posted_text( 'adventure_revision' );
	if ( 'start_adventure' === $operation ) {
		ps_pen_paper_fail( __( 'Beginne das Abenteuer über die Game-Lounge und wähle dort deinen Charakter.', 'ps-pen-paper' ) );
		$result = ps_pen_paper_start_adventure_run( $campaign, $id, ps_pen_paper_posted_id( 'character_id' ), $revision, ps_pen_paper_posted_text( 'character_revision' ) );
		$character_id = ps_pen_paper_posted_id( 'character_id' );
		$url = ps_pen_paper_profile_return( $campaign_id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $campaign_id, $character_id, $page_id ); }
		$url .= '#pspp-adventures';
	} elseif ( in_array( $operation, array( 'advance_adventure', 'request_adventure_review', 'review_adventure_run', 'cancel_adventure_review' ), true ) ) {
		$run_id = ps_pen_paper_posted_id( 'run_id' ); $character_id = ps_pen_paper_posted_id( 'character_id' );
		$result = 'advance_adventure' === $operation
			? ps_pen_paper_advance_adventure_run( $campaign, $run_id, ps_pen_paper_posted_text( 'adventure_edge' ), ps_pen_paper_posted_text( 'run_revision' ) )
			: ps_pen_paper_review_adventure_run( $campaign, $run_id, ps_pen_paper_posted_text( 'adventure_edge' ), ps_pen_paper_posted_text( 'run_revision' ),
				'request_adventure_review' === $operation ? 'request' : ( 'cancel_adventure_review' === $operation ? 'cancel' : ps_pen_paper_posted_text( 'decision' ) ) );
		$url = ps_pen_paper_profile_return( $campaign_id, $character_id );
		if ( ! $url ) { $url = ps_pen_paper_character_url( $campaign_id, $character_id, $page_id ); }
		$url .= '#pspp-adventure-run-' . $run_id;
	} else {
		if ( ! ps_pen_paper_can_manage_campaign( $campaign ) || 'complete' !== ps_pen_paper_posted_text( 'adventure_complete' ) ) { ps_pen_paper_fail( ps_pen_paper_adventure_error()->get_error_message() ); }
		if ( 'save_adventure' === $operation ) {
			$result = ps_pen_paper_save_adventure( $campaign, $id, ps_pen_paper_posted_text( 'adventure_title' ), ps_pen_paper_posted_text( 'adventure_start_scene' ), $revision, $id ? null : ps_pen_paper_scenario_default() );
		} elseif ( 'save_adventure_scenario' === $operation ) {
			$input = isset( $_POST['adventure_scenario'] ) ? wp_unslash( $_POST['adventure_scenario'] ) : null;
			$result = ps_pen_paper_save_adventure_scenario( $campaign, $id, $input, $revision );
		} elseif ( 'save_adventure_world' === $operation ) {
			$input = isset( $_POST['adventure_world'] ) ? wp_unslash( $_POST['adventure_world'] ) : null;
			if ( is_array( $input ) && isset( $input['encounter'] ) && is_array( $input['encounter'] ) && ! isset( $input['encounter']['npcs'] ) ) { $input['encounter']['npcs'] = array(); }
			$result = ps_pen_paper_save_adventure_world( $campaign, $id, $input, $revision );
		} elseif ( 'save_adventure_edge' === $operation ) {
			$input = isset( $_POST['adventure_edge'] ) ? wp_unslash( $_POST['adventure_edge'] ) : null;
			$result = ps_pen_paper_save_adventure_edge( $campaign, $id, $input, $revision );
		} elseif ( 'remove_adventure_edge' === $operation ) {
			$result = ps_pen_paper_save_adventure_edge( $campaign, $id, array(), $revision, ps_pen_paper_posted_text( 'edge_id' ) );
		} else {
			$result = ps_pen_paper_publish_adventure( $campaign, $id, $revision, ps_pen_paper_posted_text( 'adventure_confirmation' ), in_array( $operation, array( 'pause_adventure', 'resume_adventure' ), true ) && 'pause_adventure' === $operation );
		}
		$url = ps_pen_paper_game_url( $page_id, $campaign_id ) . '#pspp-story';
		if ( ! is_wp_error( $result ) ) { $url = add_query_arg( 'pspp_adventure', 'save_adventure' === $operation ? $result : $id, $url ); }
	}
	if ( is_wp_error( $result ) ) { ps_pen_paper_fail( $result->get_error_message() ); }
	wp_safe_redirect( $url ); exit;
}

function ps_pen_paper_adventure_scene_options( $campaign, $selected = 0, $allow_end = false ) {
	if ( $allow_end ) { echo '<option value="0"' . selected( $selected, 0, false ) . '>' . esc_html__( 'Abenteuer beenden', 'ps-pen-paper' ) . '</option>'; }
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID,
		'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { echo '<option disabled>' . esc_html( $scene->get_error_message() ) . '</option>'; return; }
		if ( ! $scene['published'] ) { continue; }
		echo '<option value="' . esc_attr( $post->ID ) . '"' . selected( $selected, $post->ID, false ) . '>' . esc_html( $scene['published']['title'] ) . '</option>';
	}
}

function ps_pen_paper_adventure_requirement_label( $requirement ) {
	/* translators: 1: field name, 2: approved value, 3: required minimum. */
	return sprintf( __( 'Voraussetzung: %1$s – freigegeben %2$d, mindestens %3$d.', 'ps-pen-paper' ),
		$requirement['label'], $requirement['value'], $requirement['minimum'] );
}

function ps_pen_paper_adventure_condition_operators() {
	return array( 'gte' => __( 'mindestens (≥)', 'ps-pen-paper' ), 'gt' => __( 'größer als (>)', 'ps-pen-paper' ),
		'lte' => __( 'höchstens (≤)', 'ps-pen-paper' ), 'lt' => __( 'kleiner als (<)', 'ps-pen-paper' ),
		'eq' => __( 'gleich (=)', 'ps-pen-paper' ), 'ne' => __( 'ungleich (≠)', 'ps-pen-paper' ),
		'contains' => __( 'enthält Text', 'ps-pen-paper' ), 'not_contains' => __( 'enthält Text nicht', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_condition_parts() {
	return array( 'value' => __( 'Wert', 'ps-pen-paper' ), 'current' => __( 'aktueller Wert', 'ps-pen-paper' ), 'maximum' => __( 'Maximum', 'ps-pen-paper' ),
		'text' => __( 'Text', 'ps-pen-paper' ), 'history' => __( 'vorher ausgeführte Entscheidung (0/1)', 'ps-pen-paper' ), 'state' => __( 'Abenteuerzustand', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_conditions_label( $conditions ) {
	$parts = array();
	foreach ( $conditions['rows'] as $row ) {
		/* translators: 1: field, 2: value component, 3: approved value, 4: comparison, 5: required value, 6: result. */
		$parts[] = sprintf( __( '%1$s (%2$s): freigegeben %3$s, %4$s %5$s — %6$s', 'ps-pen-paper' ),
			$row['label'], ps_pen_paper_adventure_condition_parts()[ $row['part'] ], $row['value'],
			ps_pen_paper_adventure_condition_operators()[ $row['operator'] ], $row['expected'],
			$row['met'] ? __( 'erfüllt', 'ps-pen-paper' ) : __( 'nicht erfüllt', 'ps-pen-paper' ) );
	}
	return ( 'all' === $conditions['mode'] ? __( 'Alle Bedingungen (UND):', 'ps-pen-paper' ) : __( 'Mindestens eine Bedingung (ODER):', 'ps-pen-paper' ) ) .
		' ' . implode( '; ', $parts );
}

function ps_pen_paper_render_adventure_condition_row( $rules, $index, $edges = array(), $states = array() ) {
	?>
	<div data-condition-row>
		<label><?php esc_html_e( 'Feld und Wert', 'ps-pen-paper' ); ?><select name="adventure_edge[conditions][rows][<?php echo esc_attr( $index ); ?>][target]">
			<option value=""><?php esc_html_e( 'Keine Bedingung', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $rules['fields'] as $field ) :
				if ( ! in_array( $field['type'], array( 'number', 'attribute', 'resource', 'text', 'textarea' ), true ) ) { continue; }
				foreach ( 'resource' === $field['type'] ? array( 'current', 'maximum' ) : ( in_array( $field['type'], array( 'text', 'textarea' ), true ) ? array( 'text' ) : array( 'value' ) ) as $part ) : ?>
					<option value="<?php echo esc_attr( $field['id'] . ':' . $part ); ?>"><?php echo esc_html( $field['label'] . ' · ' . ps_pen_paper_adventure_condition_parts()[ $part ] ); ?></option>
				<?php endforeach; ?>
			<?php endforeach; ?>
				<?php foreach ( $edges as $edge ) : ?><option value="<?php echo esc_attr( $edge['id'] . ':history' ); ?>"><?php echo esc_html( __( 'Vorherige Entscheidung:', 'ps-pen-paper' ) . ' ' . $edge['choice'] ); ?></option><?php endforeach; ?>
				<?php foreach ( $states as $state ) : ?><option value="<?php echo esc_attr( $state['id'] . ':state' ); ?>"><?php echo esc_html( __( 'Abenteuerzustand:', 'ps-pen-paper' ) . ' ' . $state['label'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Vergleich', 'ps-pen-paper' ); ?><select name="adventure_edge[conditions][rows][<?php echo esc_attr( $index ); ?>][operator]">
			<?php foreach ( ps_pen_paper_adventure_condition_operators() as $operator => $label ) : ?><option value="<?php echo esc_attr( $operator ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Vergleichswert (ohne Bedingung: 0)', 'ps-pen-paper' ); ?><input type="text" name="adventure_edge[conditions][rows][<?php echo esc_attr( $index ); ?>][value]" maxlength="2000" value="0"></label>
		<button type="button" data-remove-condition hidden><?php esc_html_e( 'Bedingung entfernen', 'ps-pen-paper' ); ?></button>
	</div>
	<?php
}

function ps_pen_paper_render_adventure_condition_fields( $rules, $edges = array(), $states = array() ) {
	?>
	<fieldset data-condition-editor data-next-index="2"><legend><?php esc_html_e( 'Optionale Wertebedingungen', 'ps-pen-paper' ); ?></legend>
		<label><?php esc_html_e( 'Bedingungen verknüpfen', 'ps-pen-paper' ); ?><select name="adventure_edge[conditions][mode]">
			<option value="all"><?php esc_html_e( 'Alle müssen erfüllt sein (UND)', 'ps-pen-paper' ); ?></option>
			<option value="any"><?php esc_html_e( 'Mindestens eine muss erfüllt sein (ODER)', 'ps-pen-paper' ); ?></option>
		</select></label>
		<div data-condition-rows><?php ps_pen_paper_render_adventure_condition_row( $rules, 0, $edges, $states ); ps_pen_paper_render_adventure_condition_row( $rules, 1, $edges, $states ); ?></div>
		<template data-condition-template><?php ps_pen_paper_render_adventure_condition_row( $rules, '__INDEX__', $edges, $states ); ?></template>
		<button type="button" data-add-condition hidden><?php esc_html_e( 'Weitere Bedingung', 'ps-pen-paper' ); ?></button>
		<p><?php esc_html_e( 'Bis zu 20 Wertebedingungen je Entscheidung. Geprüft werden aktuelle freigegebene Werte einschließlich aktiver Ausrüstungsboni, nicht offene Anträge. Ressourcenbedingungen prüfen aktuellen Wert oder Maximum und verbrauchen nichts. Ohne Bedingung den Vergleichswert auf 0 lassen.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Textfelder unterstützen gleich, ungleich, enthält und enthält nicht (Groß-/Kleinschreibung beachten). Vorherige Entscheidungen prüfen ausschließlich diesen Durchlauf: 1 = ausgeführt, 0 = nicht ausgeführt. Zahlenwerte bleiben ganze Zahlen zwischen −1000000 und 1000000.', 'ps-pen-paper' ); ?></p>
	</fieldset>
	<?php
}

function ps_pen_paper_adventure_cost_timings() {
	return array( 'attempt' => __( 'Bei jedem ausgeführten Versuch, auch bei Misserfolg', 'ps-pen-paper' ),
		'success' => __( 'Nur bei Erfolg; ohne Würfelprobe immer', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_cost_label( $cost ) {
	/* translators: 1: resource name, 2: cost, 3: previous value, 4: remaining value. */
	return sprintf( __( 'Ressource: %1$s – Kosten %2$d, vorher %3$d, danach %4$d.', 'ps-pen-paper' ),
		$cost['label'], $cost['amount'], $cost['before'], $cost['after'] );
}

function ps_pen_paper_adventure_reward_timings() {
	return array( 'decision' => __( 'Nach jeder ausgeführten Entscheidung, auch bei Misserfolg', 'ps-pen-paper' ),
		'success' => __( 'Nur bei Erfolg; ohne Würfelprobe immer', 'ps-pen-paper' ) );
}

function ps_pen_paper_adventure_reward_label( $reward, $result = null ) {
	if ( null === $result && 'table' === $reward['source']['kind'] ) {
		return __( 'Belohnung aus Beutetabelle:', 'ps-pen-paper' ) . ' ' . $reward['source']['table']['table']['name'];
	}
	$row = ps_pen_paper_adventure_reward_rows( $reward )[ $result ? $result['row'] : 0 ];
	$components = $result ? ps_pen_paper_adventure_reward_result_components( $reward, $result )
		: array( 'items' => $row['item'] ? array( array( 'item' => $row['item'], 'quantity' => (int) $row['quantity'] ) ) : array(), 'money' => (int) $row['money'] );
	if ( is_wp_error( $components ) ) { return $components->get_error_message(); }
	$money = $components['money'];
	$parts = array();
	foreach ( $components['items'] as $component ) {
		if ( $component['quantity'] > 0 ) { $parts[] = $component['quantity'] . ' × ' . $component['item']['item']['name']; }
	}
	if ( $money > 0 ) {
		$currency = ps_pen_paper_adventure_reward_currency( $reward );
		$parts[] = $money . ' ' . ( $result ? $result['wallet']['after']['symbol'] : $currency['document']['symbol'] );
	}
	return __( 'Belohnung:', 'ps-pen-paper' ) . ' ' . ( $parts ? implode( ', ', $parts ) : __( 'Keine Beute ermittelt', 'ps-pen-paper' ) ) .
		( $result && $result['roll'] ? ' · ' . __( 'Tabellenwurf:', 'ps-pen-paper' ) . ' ' . $result['roll']['total'] : '' );
}

function ps_pen_paper_adventure_reward_history_label( $snapshot, $entry ) {
	if ( null === $entry['reward'] ) {
		return isset( $entry['scenario_attempt'] ) ? __( 'Keine Belohnung nach der festgelegten Erfolgs- und Wiederholungsregel', 'ps-pen-paper' )
			: __( 'Bei Misserfolg keine Belohnung', 'ps-pen-paper' );
	}
	foreach ( $snapshot['edges'] as $edge ) {
		if ( $edge['id'] === $entry['edge_id'] ) { return ps_pen_paper_adventure_reward_label( ps_pen_paper_scenario_reward( $snapshot, $edge, $entry['scenario_attempt'] ?? 0 ), $entry['reward'] ); }
	}
	return ps_pen_paper_adventure_reward_error()->get_error_message();
}

function ps_pen_paper_render_adventure_reward_fields( $campaign, $name = 'adventure_edge[reward]', $repeat = false ) {
	if ( ! ps_pen_paper_community_available() ) {
		echo '<p>' . esc_html__( 'Gegenstands- und Währungsbelohnungen benötigen PS Community. Entscheidungen ohne Belohnung bleiben unabhängig davon nutzbar.', 'ps-pen-paper' ) . '</p>'; return;
	}
	$catalogue = ps_pen_paper_item_catalogue( $campaign ); $tables = ps_pen_paper_loot_tables( $campaign );
	if ( is_wp_error( $catalogue ) || is_wp_error( $tables ) ) {
		echo '<p role="alert">' . esc_html( is_wp_error( $catalogue ) ? $catalogue->get_error_message() : $tables->get_error_message() ) . '</p>'; return;
	}
	?>
	<fieldset><legend><?php echo esc_html( $repeat ? __( 'Optionale Wiederholungsbelohnung', 'ps-pen-paper' ) : __( 'Optionale Abenteuerbelohnung', 'ps-pen-paper' ) ); ?></legend>
		<label><?php esc_html_e( 'Belohnungsart', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $name ); ?>[kind]">
			<option value="none"><?php esc_html_e( 'Keine Belohnung', 'ps-pen-paper' ); ?></option>
			<option value="fixed"><?php esc_html_e( 'Feste Gegenstände und/oder Währung', 'ps-pen-paper' ); ?></option>
			<option value="table"><?php esc_html_e( 'Vorhandene Beutetabelle', 'ps-pen-paper' ); ?></option>
		</select></label>
		<label><?php esc_html_e( 'Wann wird die Belohnung vergeben?', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $name ); ?>[timing]">
			<?php foreach ( ps_pen_paper_adventure_reward_timings() as $timing => $label ) : ?><option value="<?php echo esc_attr( $timing ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Beutetabelle (nur bei Tabellenbelohnung)', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $name ); ?>[table_id]">
			<option value="0"><?php esc_html_e( 'Keine Tabelle', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $tables as $id => $table ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $table['table']['name'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Gegenstand (nur bei fester Belohnung)', 'ps-pen-paper' ); ?><select name="<?php echo esc_attr( $name ); ?>[item_id]">
			<option value="0"><?php esc_html_e( 'Kein Gegenstand', 'ps-pen-paper' ); ?></option>
			<?php foreach ( $catalogue as $id => $item ) : ?><option value="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $item['item']['name'] ); ?></option><?php endforeach; ?>
		</select></label>
		<label><?php esc_html_e( 'Feste Anzahl (ohne Gegenstand: 0)', 'ps-pen-paper' ); ?><input type="number" name="<?php echo esc_attr( $name ); ?>[quantity]" min="0" max="100" value="0" required></label>
		<label><?php esc_html_e( 'Fester Währungsbetrag (ohne Geld: 0)', 'ps-pen-paper' ); ?><input type="number" name="<?php echo esc_attr( $name ); ?>[money]" min="0" max="999999999" value="0" required></label>
		<p><?php esc_html_e( 'Nicht verwendete Auswahlen und Beträge auf 0 lassen. Beim Speichern wird der gewählte Gegenstands- oder Tabellenstand eingefroren. Mit der Abenteuerfreigabe wird dieser Stand Teil des für berechtigte Spieler lesbaren Soloabenteuers; andere Beutetabellen bleiben privat.', 'ps-pen-paper' ); ?></p>
		<p><?php esc_html_e( 'Einmalige Vergabe je Entscheidung im Durchlauf; die Szenarioeinstellung bestimmt, ob Wiederholungen erneut oder anders belohnt werden. Für eine Abschlussbelohnung die letzte Entscheidung verwenden. Vor dem Würfeln müssen Inventar und Guthaben jedes mögliche Ergebnis aufnehmen können. Gemeinsame Guthaben benötigen die Freigabe der Universumsleitung.', 'ps-pen-paper' ); ?></p>
	</fieldset>
	<?php
}

function ps_pen_paper_render_adventure_editor( $campaign, $page_id ) {
	if ( ! ps_pen_paper_can_access_site() || ! ps_pen_paper_can_manage_campaign( $campaign ) ) { return; }
	$records = ps_pen_paper_adventure_records( $campaign );
	if ( is_wp_error( $records ) ) { echo '<p role="alert">' . esc_html( $records->get_error_message() ) . '</p>'; return; }
	$scenes = array();
	foreach ( get_posts( array( 'post_type' => 'pspp_scene', 'post_status' => 'private', 'post_parent' => $campaign->ID, 'posts_per_page' => -1, 'orderby' => 'ID', 'order' => 'ASC' ) ) as $post ) {
		$scene = ps_pen_paper_get_scene( $campaign, $post->ID );
		if ( is_wp_error( $scene ) ) { echo '<p role="alert">' . esc_html( $scene->get_error_message() ) . '</p>'; return; }
		if ( $scene['published'] ) { $scenes[ $post->ID ] = $scene['published']['title']; }
	}
	$id = ps_pen_paper_frontend_id( 'pspp_adventure' );
	$state = is_wp_error( $id ) ? $id : ( $id ? ps_pen_paper_get_adventure( $campaign, $id ) : ps_pen_paper_adventure_default() );
	if ( is_wp_error( $state ) ) { echo '<p role="alert">' . esc_html( $state->get_error_message() ) . '</p>'; return; }
	$draft = $state['draft']; $rules = ps_pen_paper_get_rules( $campaign->ID );
	?>
	<section class="pspp-adventure-editor"><h3><?php esc_html_e( 'Abenteuer und Szenarien vorbereiten', 'ps-pen-paper' ); ?></h3>
	<p><?php esc_html_e( 'Einzeldurchläufe zeigen nur freigegebene Szenentexte. Entscheidungen und Proben öffnen private Folgeszenen für genau diese Figur. Ressourcenverbrauch und Belohnungen erfolgen nur nach ausdrücklich freigegebenen Regeln; die Kampagnenszene bleibt unverändert.', 'ps-pen-paper' ); ?></p>
	<?php if ( ! $scenes ) : ?><p><?php esc_html_e( 'Veröffentliche zuerst mindestens zwei Szenen. Danach kannst du hier einen Pfad aus Entscheidung, optionaler Probe und Folgeszene vorbereiten.', 'ps-pen-paper' ); ?></p>
	<?php else : ?>
		<nav class="pspp-navigation"><a href="<?php echo esc_url( ps_pen_paper_game_url( $page_id, $campaign->ID ) . '#pspp-story' ); ?>"><?php esc_html_e( 'Neues Soloabenteuer', 'ps-pen-paper' ); ?></a>
			<?php foreach ( $records as $adventure_id => $record ) : ?><a href="<?php echo esc_url( add_query_arg( 'pspp_adventure', $adventure_id, ps_pen_paper_game_url( $page_id, $campaign->ID ) ) . '#pspp-story' ); ?>"><?php echo esc_html( $record['draft']['title'] . ' · ' . $record['status'] ); ?></a><?php endforeach; ?>
		</nav>
		<?php ps_pen_paper_form_start( 'save_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
			<label><?php esc_html_e( 'Abenteuertitel', 'ps-pen-paper' ); ?><input type="text" name="adventure_title" value="<?php echo esc_attr( $draft['title'] ); ?>" maxlength="200" required></label>
			<label><?php esc_html_e( 'Eröffnungsszene', 'ps-pen-paper' ); ?><select name="adventure_start_scene" required><?php ps_pen_paper_adventure_scene_options( $campaign, $draft['start_scene'] ); ?></select></label>
			<button type="submit"><?php esc_html_e( 'Abenteuerentwurf speichern', 'ps-pen-paper' ); ?></button>
		</form>
		<?php if ( $id ) : ?>
			<?php ps_pen_paper_render_scenario_editor( $campaign, $id, $state, $records, $rules, $page_id ); ?>
			<?php ps_pen_paper_render_adventure_world_editor( $campaign, $id, $state, $page_id ); ?>
			<h4><?php esc_html_e( 'Entscheidungen und Folgeszenen', 'ps-pen-paper' ); ?></h4>
			<?php if ( ! $draft['edges'] ) : ?><p><?php esc_html_e( 'Noch keine Entscheidung angelegt.', 'ps-pen-paper' ); ?></p><?php endif; ?>
			<ol><?php foreach ( $draft['edges'] as $edge ) : ?>
				<li><?php echo esc_html( ( $scenes[ $edge['from_scene'] ] ?? __( 'Ungültige Szene', 'ps-pen-paper' ) ) . ' · ' . $edge['choice'] ); ?>
					<?php if ( $edge['expression'] ) : ?> · <?php echo esc_html( $edge['expression'] . ' ≥ ' . $edge['threshold'] ); ?><?php endif; ?>
					<?php echo esc_html( ' → ' . ( $edge['success_scene'] ? ( $scenes[ $edge['success_scene'] ] ?? '' ) : __( 'Ende', 'ps-pen-paper' ) ) ); ?>
					<?php if ( $edge['expression'] ) { echo esc_html( ' / ' . ( $edge['failure_scene'] ? ( $scenes[ $edge['failure_scene'] ] ?? '' ) : __( 'Ende', 'ps-pen-paper' ) ) ); } ?>
					<?php if ( isset( $edge['requirement'] ) ) :
						$field_label = $edge['requirement']['attribute'];
						foreach ( $rules['fields'] as $field ) { if ( $field['id'] === $edge['requirement']['attribute'] ) { $field_label = $field['label']; break; } } ?>
						<p><?php echo esc_html( __( 'Mindestwert:', 'ps-pen-paper' ) . ' ' . $field_label . ' ≥ ' . $edge['requirement']['minimum'] ); ?></p>
					<?php endif; ?>
					<?php if ( isset( $edge['resource_cost'] ) ) :
						$resource_label = $edge['resource_cost']['resource'];
						foreach ( $rules['fields'] as $field ) { if ( $field['id'] === $edge['resource_cost']['resource'] ) { $resource_label = $field['label']; break; } } ?>
						<p><?php echo esc_html( __( 'Ressourcenkosten:', 'ps-pen-paper' ) . ' ' . $resource_label . ' ' . $edge['resource_cost']['amount'] . ' · ' . ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ); ?></p>
					<?php endif; ?>
					<?php if ( isset( $edge['reward'] ) ) : ?><p><?php echo esc_html( ps_pen_paper_adventure_reward_label( $edge['reward'] ) . ' · ' . ps_pen_paper_adventure_reward_timings()[ $edge['reward']['timing'] ] ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['repeat_reward'] ) ) : ?><p><?php echo esc_html( __( 'Bei Wiederholung:', 'ps-pen-paper' ) . ' ' . ps_pen_paper_adventure_reward_label( $edge['repeat_reward'] ) ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['outcomes'] ) ) : ?><p><?php echo esc_html( __( 'Bei Szenarioende:', 'ps-pen-paper' ) . ' ' . ps_pen_paper_scenario_outcomes()[ $edge['outcomes']['success'] ] .
						( $edge['expression'] ? ' / ' . ps_pen_paper_scenario_outcomes()[ $edge['outcomes']['failure'] ] : '' ) ); ?></p><?php endif; ?>
					<?php if ( isset( $edge['conditions'] ) ) : ?>
						<p><?php echo esc_html( 'all' === $edge['conditions']['mode'] ? __( 'Alle Bedingungen (UND):', 'ps-pen-paper' ) : __( 'Mindestens eine Bedingung (ODER):', 'ps-pen-paper' ) ); ?></p>
						<ul><?php foreach ( $edge['conditions']['rows'] as $condition ) :
							$field = ps_pen_paper_adventure_condition_field( $condition, $rules, $draft['edges'] ); ?>
							<li><?php echo esc_html( is_wp_error( $field ) ? $field->get_error_message() : $field['label'] . ' · ' .
								ps_pen_paper_adventure_condition_parts()[ $condition['part'] ] . ' ' . ps_pen_paper_adventure_condition_operators()[ $condition['operator'] ] . ' ' . $condition['value'] ); ?></li>
						<?php endforeach; ?></ul>
					<?php endif; ?>
					<?php if ( ! empty( $edge['gm_review'] ) ) : ?><p><?php esc_html_e( 'Diese Entscheidung benötigt ausdrücklich eine Spielleiterfreigabe', 'ps-pen-paper' ); ?></p><?php endif; ?>
					<?php ps_pen_paper_form_start( 'remove_adventure_edge', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
						<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>"><input type="hidden" name="edge_id" value="<?php echo esc_attr( $edge['id'] ); ?>">
						<button type="submit"><?php esc_html_e( 'Entscheidung entfernen', 'ps-pen-paper' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?></ol>
			<?php if ( $rules && ! is_wp_error( $rules ) ) : ?>
			<details class="pspp-fold"><summary><?php esc_html_e( 'Entscheidung oder Probe ergänzen', 'ps-pen-paper' ); ?></summary>
				<?php ps_pen_paper_form_start( 'save_adventure_edge', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
					<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
					<label><?php esc_html_e( 'Ausgangsszene', 'ps-pen-paper' ); ?><select name="adventure_edge[from_scene]" required><?php ps_pen_paper_adventure_scene_options( $campaign ); ?></select></label>
					<label><?php esc_html_e( 'Entscheidungstext', 'ps-pen-paper' ); ?><input type="text" name="adventure_edge[choice]" maxlength="120" required></label>
					<?php ps_pen_paper_render_adventure_condition_fields( $rules, $draft['edges'], $draft['states'] ?? array() ); ?>
					<?php ps_pen_paper_render_adventure_world_fields( $campaign, $draft ); ?>
					<label class="pspp-check"><input type="checkbox" name="adventure_edge[gm_review]" value="1"><?php esc_html_e( 'Diese Entscheidung benötigt ausdrücklich eine Spielleiterfreigabe', 'ps-pen-paper' ); ?></label>
					<p><?php esc_html_e( 'Der Besitzer fragt die Entscheidung an. Erst nach Freigabe kann er sie ausführen; bis dahin gibt es weder Würfelwurf noch Verbrauch oder Belohnung. Die Freigabe gilt einmalig für diese Entscheidung und diesen Durchlauf.', 'ps-pen-paper' ); ?></p>
					<label><?php esc_html_e( 'Optionale Ressourcenkosten', 'ps-pen-paper' ); ?><select name="adventure_edge[cost_resource]"><option value=""><?php esc_html_e( 'Keine Ressourcenkosten', 'ps-pen-paper' ); ?></option>
						<?php foreach ( $rules['fields'] as $field ) : if ( 'resource' !== $field['type'] ) { continue; } ?><option value="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></option><?php endforeach; ?>
					</select></label>
					<label><?php esc_html_e( 'Kosten in ganzen Punkten (ohne Ressource: 0)', 'ps-pen-paper' ); ?><input type="number" name="adventure_edge[cost_amount]" min="0" max="1000000" value="0" required></label>
					<label><?php esc_html_e( 'Wann werden Kosten abgezogen?', 'ps-pen-paper' ); ?><select name="adventure_edge[cost_timing]">
						<?php foreach ( ps_pen_paper_adventure_cost_timings() as $timing => $label ) : ?><option value="<?php echo esc_attr( $timing ); ?>"><?php echo esc_html( $label ); ?></option><?php endforeach; ?>
					</select></label>
					<p><?php esc_html_e( 'Nur lokale Bögen und unabhängige Kopien unterstützen derzeit Verbrauch. Offene Werteanträge sperren kostenpflichtige Versuche. Die Ressource muss vor dem Versuch reichen; ihr Maximum und andere Werte bleiben unverändert.', 'ps-pen-paper' ); ?></p>
					<?php ps_pen_paper_render_adventure_reward_fields( $campaign ); ?>
					<details><summary><?php esc_html_e( 'Andere Belohnung bei Wiederholung', 'ps-pen-paper' ); ?></summary>
						<?php ps_pen_paper_render_adventure_reward_fields( $campaign, 'adventure_edge[repeat_reward]', true ); ?>
					</details>
					<label><?php esc_html_e( 'Optionaler Würfelausdruck (leer = Entscheidung ohne Probe)', 'ps-pen-paper' ); ?><input type="text" name="adventure_edge[expression]" placeholder="2W6"></label>
					<label><?php esc_html_e( 'Optionaler freigegebener Zahlenwert', 'ps-pen-paper' ); ?><select name="adventure_edge[attribute]"><option value=""><?php esc_html_e( 'Kein Attribut addieren', 'ps-pen-paper' ); ?></option>
						<?php foreach ( $rules['fields'] as $field ) : if ( ! in_array( $field['type'], array( 'attribute', 'number' ), true ) ) { continue; } ?><option value="<?php echo esc_attr( $field['id'] ); ?>"><?php echo esc_html( $field['label'] ); ?></option><?php endforeach; ?>
					</select></label>
					<label><?php esc_html_e( 'Erfolg ab Gesamtwert (ohne Probe: 0)', 'ps-pen-paper' ); ?><input type="number" name="adventure_edge[threshold]" min="-1000000" max="1000000" value="0" required></label>
					<label><?php esc_html_e( 'Folgeszene bei Erfolg (oder bei Entscheidung ohne Probe)', 'ps-pen-paper' ); ?><select name="adventure_edge[success_scene]" required><?php ps_pen_paper_adventure_scene_options( $campaign, 0, true ); ?></select></label>
					<label><?php esc_html_e( 'Folgeszene bei Misserfolg', 'ps-pen-paper' ); ?><select name="adventure_edge[failure_scene]"><option value="0"><?php esc_html_e( 'Abenteuer beenden', 'ps-pen-paper' ); ?></option><?php ps_pen_paper_adventure_scene_options( $campaign ); ?></select></label>
					<?php foreach ( array( 'success' => __( 'Ergebnis bei Ende über den Erfolgsweg oder ohne Probe', 'ps-pen-paper' ), 'failure' => __( 'Ergebnis bei Ende über den Misserfolgsweg', 'ps-pen-paper' ) ) as $branch => $label ) : ?>
						<label><?php echo esc_html( $label ); ?><select name="adventure_edge[outcomes][<?php echo esc_attr( $branch ); ?>]">
							<?php foreach ( ps_pen_paper_scenario_outcomes() as $outcome => $outcome_label ) : ?><option value="<?php echo esc_attr( $outcome ); ?>"><?php echo esc_html( $outcome_label ); ?></option><?php endforeach; ?>
						</select></label>
					<?php endforeach; ?>
					<p><?php esc_html_e( 'Das Ergebnis zählt nur, wenn dieser Weg das Szenario beendet. Ein bestandener Würfelwurf ist nicht automatisch ein Szenariosieg.', 'ps-pen-paper' ); ?></p>
					<p><?php esc_html_e( 'Der Server würfelt einmal, addiert den gewählten freigegebenen Zahlenwert und vergleicht mit dem Zielwert. Optional festgelegte Kosten werden nach der gespeicherten Kostenregel zusammen mit dem Fortschritt abgerechnet.', 'ps-pen-paper' ); ?></p>
					<button type="submit"><?php esc_html_e( 'Entscheidung speichern', 'ps-pen-paper' ); ?></button>
				</form>
			</details>
			<?php endif; ?>
			<p><?php echo esc_html( __( 'Status:', 'ps-pen-paper' ) . ' ' . array( 'draft' => __( 'Entwurf', 'ps-pen-paper' ), 'published' => __( 'Für Einzelabenteuer freigegeben', 'ps-pen-paper' ), 'paused' => __( 'Pausiert – laufende Durchläufe können fortgesetzt werden', 'ps-pen-paper' ) )[ $state['status'] ] ); ?></p>
			<?php foreach ( array( 'publish_adventure' => __( 'Abenteuer ausdrücklich freigeben', 'ps-pen-paper' ), 'pause_adventure' => __( 'Neue Durchläufe pausieren', 'ps-pen-paper' ), 'resume_adventure' => __( 'Neue Durchläufe wieder erlauben', 'ps-pen-paper' ) ) as $operation => $label ) :
				if ( ( 'publish_adventure' === $operation && in_array( $state['status'], array( 'published', 'paused' ), true ) ) || ( 'pause_adventure' === $operation && 'published' !== $state['status'] ) ||
					( 'resume_adventure' === $operation && 'paused' !== $state['status'] ) ) { continue; }
				ps_pen_paper_form_start( $operation, 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="adventure_complete" value="complete"><input type="hidden" name="adventure_id" value="<?php echo esc_attr( $id ); ?>"><input type="hidden" name="adventure_revision" value="<?php echo esc_attr( $state['revision'] ); ?>">
				<label class="pspp-check"><input type="checkbox" name="adventure_confirmation" value="confirmed" required><?php echo esc_html( $label ); ?></label><button type="submit"><?php echo esc_html( $label ); ?></button>
			</form><?php endforeach; ?>
		<?php endif; ?>
	<?php endif; ?>
	<?php ps_pen_paper_render_adventure_reviews( $campaign, $page_id ); ?>
	</section>
	<?php
}

function ps_pen_paper_render_character_adventures( $campaign, $character, $page_id ) {
	if ( ! $campaign || 'pspp_campaign' !== $campaign->post_type || ! ps_pen_paper_can_access_site() ||
		! ps_pen_paper_get_character( $campaign, $character->ID ) ) { return; }
	$round = ps_pen_paper_play_round( $campaign->ID );
	if ( is_wp_error( $round ) ) { echo '<p role="alert">' . esc_html( $round->get_error_message() ) . '</p>'; return; }
	if ( $round && 'solo' !== $round['mode'] ) { return; }
	$adventures = ps_pen_paper_adventure_records( $campaign, false );
	$runs = ps_pen_paper_adventure_run_records( $campaign, $character->ID );
	$state = ps_pen_paper_character_state( $campaign, $character );
	if ( is_wp_error( $adventures ) || is_wp_error( $runs ) || is_wp_error( $state ) ) {
		echo '<p role="alert">' . esc_html( is_wp_error( $adventures ) ? $adventures->get_error_message() : ( is_wp_error( $runs ) ? $runs->get_error_message() : $state->get_error_message() ) ) . '</p>'; return;
	}
	$by_adventure = array(); foreach ( $runs as $run_id => $run ) { $by_adventure[ $run['adventure_id'] ] = array( 'id' => $run_id, 'state' => $run ); }
	ps_pen_paper_sort_scenarios( $adventures );
	?>
	<section id="pspp-adventures" class="pspp-solo-adventures"><h3><?php esc_html_e( 'Soloabenteuer', 'ps-pen-paper' ); ?></h3>
	<?php $game_page = ps_pen_paper_find_frontend_page( $page_id ); ?>
	<?php if ( $game_page && ! $round ) : ?>
		<p><a href="<?php echo esc_url( ps_pen_paper_play_url( $game_page->ID, $campaign->ID, 'solo' ) ); ?>"><?php esc_html_e( 'Neue Solo-Runde in der Game-Lounge auswählen', 'ps-pen-paper' ); ?></a></p>
	<?php elseif ( $game_page ) : ?>
	<?php else : ?><p role="alert"><?php esc_html_e( 'Es ist keine veröffentlichte Spielbereich-Seite eingerichtet. Bitte wende dich an den Website-Betreiber.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php if ( ! $adventures ) : ?><p><?php esc_html_e( 'Derzeit sind keine freigegebenen Soloabenteuer vorhanden.', 'ps-pen-paper' ); ?></p><?php endif; ?>
	<?php foreach ( $adventures as $adventure_id => $adventure ) :
		if ( $round && $adventure_id !== $round['adventure_id'] ) { continue; }
		$existing = isset( $by_adventure[ $adventure_id ] ) ? $by_adventure[ $adventure_id ] : null;
		$access = $state['active'] ? ps_pen_paper_scenario_access( $campaign, $adventure_id, $adventure['published'], array( 'post' => $character, 'state' => $state ), 'solo' ) : ps_pen_paper_scenario_error();
		$can_start = ! is_wp_error( $access ) && $access['met'] && ( ! $existing || ( 'completed' === $existing['state']['status'] && isset( $adventure['published']['scenario'] ) ) ); ?>
		<article class="pspp-solo-adventure" data-adventure-id="<?php echo esc_attr( $adventure_id ); ?>">
			<h4><?php echo esc_html( $adventure['published']['title'] ); ?></h4>
			<?php if ( ! $existing || 'completed' === $existing['state']['status'] ) { ps_pen_paper_render_scenario_access( $adventure['published'], $access ); } ?>
			<?php if ( $existing ) : ps_pen_paper_render_adventure_run( $campaign, $character, $page_id, $existing['id'], $existing['state'], $state ); ?>
			<?php elseif ( 'published' !== $adventure['status'] ) : ?><p><?php esc_html_e( 'Neue Durchläufe sind pausiert. Laufende Durchläufe bleiben unabhängig fortsetzbar.', 'ps-pen-paper' ); ?></p>
			<?php elseif ( (int) $character->post_author !== get_current_user_id() ) : ?><p><?php esc_html_e( 'Nur der Besitzer startet den eigenen Durchlauf.', 'ps-pen-paper' ); ?></p><?php endif; ?>
		</article>
	<?php endforeach; ?></section>
	<?php
}

function ps_pen_paper_render_adventure_run( $campaign, $character, $page_id, $run_id, $run, $character_state ) {
	$round = ps_pen_paper_play_round( $campaign->ID );
	$snapshot = $run['snapshot']; $scene = $run['scene_id'] ? $snapshot['scenes'][ $run['scene_id'] ] : null;
	echo '<div class="pspp-adventure-run" id="pspp-adventure-run-' . esc_attr( $run_id ) . '" data-run-id="' . esc_attr( $run_id ) . '">';
	if ( isset( $run['scenario_attempt'] ) ) {
		ps_pen_paper_render_scenario_access( $snapshot, array( 'messages' => array() ) );
		/* translators: %d: saved attempt number for this player. */
		echo '<p>' . esc_html( sprintf( __( 'Dein Durchlauf: %d', 'ps-pen-paper' ), $run['scenario_attempt'] ) ) . '</p>';
	}
	ps_pen_paper_render_adventure_world_progress( $snapshot, $run['scene_id'], $run['history'] );
	if ( isset( $run['approval'] ) ) {
		echo '<p role="status">' . esc_html( 'pending' === $run['approval']['status']
			? __( 'Spielleiterentscheidung ausstehend. Der Durchlauf und alle Ressourcen bleiben unverändert.', 'ps-pen-paper' )
			: __( 'Entscheidung freigegeben. Der Besitzer kann sie jetzt ausführen; Voraussetzungen werden erneut geprüft.', 'ps-pen-paper' ) ) . '</p>';
		if ( $run['owner_id'] === get_current_user_id() ) {
			ps_pen_paper_form_start( 'cancel_adventure_review', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
				<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="run_id" value="<?php echo esc_attr( $run_id ); ?>">
				<input type="hidden" name="run_revision" value="<?php echo esc_attr( $run['revision'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $run['approval']['edge_id'] ); ?>">
				<button type="submit"><?php esc_html_e( 'Anfrage oder Freigabe zurückziehen', 'ps-pen-paper' ); ?></button>
			</form><?php
		}
	}
	if ( $scene ) {
		echo '<h5>' . esc_html( $scene['title'] ) . '</h5><p class="pspp-multiline">' . esc_html( $scene['text'] ) . '</p>';
		ps_pen_paper_render_wiki_target_link( $campaign, 'scene:' . $run['scene_id'] );
	} elseif ( $run['history'] ) {
		$last = end( $run['history'] ); echo '<p>' . esc_html( $snapshot['title'] . ' · ' . __( 'Abenteuer beendet', 'ps-pen-paper' ) . ' · ' . $last['choice'] ) . '</p>';
	}
	if ( 'completed' === $run['status'] ) { echo '<p role="status">' . esc_html( __( 'Durchlauf abgeschlossen.', 'ps-pen-paper' ) . ' ' . ps_pen_paper_scenario_outcomes()[ ps_pen_paper_scenario_outcome( $snapshot, $run['history'] ) ] ) . '</p>'; }
	elseif ( (int) $character->post_author === get_current_user_id() && ( ! $round || 'active' === $round['status'] ) ) {
		$choices = array_values( array_filter( $snapshot['edges'], function ( $edge ) use ( $run ) { return $edge['from_scene'] === $run['scene_id']; } ) );
		$rules = ps_pen_paper_get_rules( $campaign->ID );
		foreach ( $choices as $edge ) {
			$requirement = ps_pen_paper_adventure_requirement_value( $edge, $rules, $character_state );
			$requirement_blocked = is_wp_error( $requirement ) || ( $requirement && $requirement['value'] < $requirement['minimum'] );
			$conditions = ps_pen_paper_evaluate_adventure_conditions( $edge, $rules, $character_state, $run['history'], $run['snapshot']['edges'], $snapshot['states'] ?? array() );
			$conditions_blocked = is_wp_error( $conditions ) || ( $conditions && ! $conditions['met'] );
			$cost = isset( $edge['resource_cost'] ) ? ps_pen_paper_character_resource_cost( $campaign, $character, $edge['resource_cost']['resource'], $edge['resource_cost']['amount'] ) : null;
			$cost_blocked = is_wp_error( $cost ) || ( $cost && $cost['after'] < $cost['minimum'] );
			$effective_reward = ps_pen_paper_scenario_reward( $snapshot, $edge, $run['scenario_attempt'] ?? 0 );
			$reward_context = $effective_reward ? ps_pen_paper_adventure_reward_context( $campaign, $character, $effective_reward ) : null;
			$items = isset( $edge['items'] ) ? ps_pen_paper_adventure_items_result( $edge['items'], (int) $character->ID ) : null;
			$blocked = $requirement_blocked || $conditions_blocked || $cost_blocked || is_wp_error( $reward_context ) || is_wp_error( $items ) || ( $items && ! $items['met'] );
			if ( $items ) { echo '<p>' . esc_html( is_wp_error( $items ) ? $items->get_error_message() : ps_pen_paper_adventure_items_label( $items ) ) . '</p>'; }
			$review_needed = ! empty( $edge['gm_review'] );
			$review_approved = $review_needed && isset( $run['approval'] ) && $run['approval']['edge_id'] === $edge['id'] &&
				'approved' === $run['approval']['status'] && ps_pen_paper_adventure_reviewer_authority( $campaign, $run['approval']['reviewer_id'] );
			$blocked = $blocked || ( $review_needed && ! $review_approved && isset( $run['approval'] ) );
			$hint_id = 'pspp-requirement-' . $run_id . '-' . $edge['id'];
			$cost_id = 'pspp-cost-' . $run_id . '-' . $edge['id'];
			$reward_id = 'pspp-reward-' . $run_id . '-' . $edge['id'];
			$conditions_id = 'pspp-conditions-' . $run_id . '-' . $edge['id'];
			$descriptions = array();
			if ( $requirement ) { $descriptions[] = $hint_id; }
			if ( $cost ) { $descriptions[] = $cost_id; }
			if ( $reward_context ) { $descriptions[] = $reward_id; }
			if ( $conditions ) { $descriptions[] = $conditions_id; }
			ps_pen_paper_form_start( $review_needed && ! $review_approved ? 'request_adventure_review' : 'advance_adventure', 'pspp_campaign_' . $campaign->ID, $campaign->ID, $page_id ); ?>
			<input type="hidden" name="character_id" value="<?php echo esc_attr( $character->ID ); ?>"><input type="hidden" name="run_id" value="<?php echo esc_attr( $run_id ); ?>">
			<input type="hidden" name="run_revision" value="<?php echo esc_attr( $run['revision'] ); ?>"><input type="hidden" name="adventure_edge" value="<?php echo esc_attr( $edge['id'] ); ?>">
			<button type="submit"<?php disabled( $blocked ); ?><?php if ( $descriptions ) : ?> aria-describedby="<?php echo esc_attr( implode( ' ', $descriptions ) ); ?>"<?php endif; ?>><?php echo esc_html( $edge['choice'] ); ?></button>
			<?php if ( $review_needed ) : ?><p><?php echo esc_html( $review_approved ? __( 'Spielleiterfreigabe vorhanden – Entscheidung ausführen.', 'ps-pen-paper' ) : __( 'Diese Auswahl fragt zuerst eine Spielleiterentscheidung an.', 'ps-pen-paper' ) ); ?></p><?php endif; ?>
			<?php if ( $requirement ) : ?>
				<p id="<?php echo esc_attr( $hint_id ); ?>"<?php if ( is_wp_error( $requirement ) ) : ?> role="alert"<?php endif; ?>><?php
					echo esc_html( is_wp_error( $requirement ) ? $requirement->get_error_message() : ps_pen_paper_adventure_requirement_label( $requirement ) );
					if ( $requirement_blocked && ! is_wp_error( $requirement ) ) { echo ' ' . esc_html__( 'Entscheidung gesperrt. Nach einer Wertefreigabe den Bogen neu laden.', 'ps-pen-paper' ); }
				?></p>
			<?php endif; ?>
			<?php if ( $conditions ) : ?><p id="<?php echo esc_attr( $conditions_id ); ?>"<?php if ( is_wp_error( $conditions ) ) : ?> role="alert"<?php endif; ?>><?php
				echo esc_html( is_wp_error( $conditions ) ? $conditions->get_error_message() : ps_pen_paper_adventure_conditions_label( $conditions ) );
				if ( $conditions_blocked && ! is_wp_error( $conditions ) ) { echo ' ' . esc_html__( 'Entscheidung gesperrt. Nach einer Wertefreigabe den Bogen neu laden.', 'ps-pen-paper' ); }
			?></p><?php endif; ?>
			<?php if ( $cost ) : ?>
				<p id="<?php echo esc_attr( $cost_id ); ?>"<?php if ( is_wp_error( $cost ) ) : ?> role="alert"<?php endif; ?>><?php
					if ( is_wp_error( $cost ) ) { echo esc_html( $cost->get_error_message() ); }
					else {
						/* translators: 1: resource name, 2: cost, 3: available points, 4: cost timing. */
						echo esc_html( sprintf( __( 'Kosten: %1$s %2$d; verfügbar %3$d. %4$s.', 'ps-pen-paper' ),
							$cost['label'], $cost['amount'], $cost['before'], ps_pen_paper_adventure_cost_timings()[ $edge['resource_cost']['timing'] ] ) );
						if ( $cost_blocked ) { echo ' ' . esc_html__( 'Ressource reicht nicht aus oder würde ihre Untergrenze unterschreiten. Entscheidung gesperrt.', 'ps-pen-paper' ); }
					}
				?></p>
			<?php endif; ?>
			<?php if ( $reward_context ) : ?><p id="<?php echo esc_attr( $reward_id ); ?>"<?php if ( is_wp_error( $reward_context ) ) : ?> role="alert"<?php endif; ?>><?php
				echo esc_html( ps_pen_paper_adventure_reward_label( $effective_reward ) . ' · ' . ps_pen_paper_adventure_reward_timings()[ $effective_reward['timing'] ] );
				if ( is_wp_error( $reward_context ) ) { echo ' ' . esc_html( $reward_context->get_error_message() ); }
			?></p><?php endif; ?>
			<?php if ( $edge['expression'] ) : ?><p><?php echo esc_html( __( 'Serverprobe:', 'ps-pen-paper' ) . ' ' . $edge['expression'] . ( $edge['attribute'] ? ' + freigegebener Wert' : '' ) . ' · ' . __( 'Erfolg ab', 'ps-pen-paper' ) . ' ' . $edge['threshold'] ); ?></p><?php endif; ?>
			</form><?php
		}
	} else { echo '<p>' . esc_html__( 'Der Durchlauf gehört dem Charakterbesitzer. Die Spielleitung kann ihn einsehen, aber nicht an seiner Stelle entscheiden.', 'ps-pen-paper' ) . '</p>'; }
	if ( $run['history'] ) {
		echo '<h6>' . esc_html__( 'Bisheriger Verlauf', 'ps-pen-paper' ) . '</h6><ol>';
		foreach ( $run['history'] as $entry ) {
			$detail = $entry['choice'];
			if ( isset( $entry['requirement'] ) ) { $detail .= ' · ' . ps_pen_paper_adventure_requirement_label( $entry['requirement'] ); }
			if ( isset( $entry['conditions'] ) ) { $detail .= ' · ' . ps_pen_paper_adventure_conditions_label( $entry['conditions'] ); }
			if ( array_key_exists( 'resource_cost', $entry ) ) {
				$detail .= ' · ' . ( $entry['resource_cost'] ? ps_pen_paper_adventure_cost_label( $entry['resource_cost'] ) : __( 'Bei Misserfolg keine Kosten', 'ps-pen-paper' ) );
			}
			if ( array_key_exists( 'reward', $entry ) ) { $detail .= ' · ' . ps_pen_paper_adventure_reward_history_label( $snapshot, $entry ); }
			if ( $entry['roll'] ) { $detail .= ' · ' . $entry['roll']['total'] . ' / ' . $entry['roll']['threshold'] . ' · ' . ( $entry['roll']['success'] ? __( 'Erfolg', 'ps-pen-paper' ) : __( 'Misserfolg', 'ps-pen-paper' ) ); }
			echo '<li>' . esc_html( $detail ) . '</li>';
		}
		echo '</ol>';
	}
	echo '</div>';
}
